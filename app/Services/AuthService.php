<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\CodigoEmail;
use App\Models\DesafioVerificacao;
use App\Models\Empresa;
use App\Models\LogAutenticacao;
use App\Models\Pessoa;
use App\Support\Formato;
use Illuminate\Support\Facades\Hash;

/*
 * LOGIN, TOKEN E LOG DE AUTENTICAÇÃO
 * ---------------------------------------------------------------
 * Pessoa comum: senha -> pergunta de segurança -> código no e-mail -> token.
 * Master: senha -> token direto (sem 2FA).
 *   (se o e-mail do cadastro ainda não foi confirmado, a senha certa manda o código de confirmação)
 * Empresa: senha -> código no e-mail -> token.
 * O token (Sanctum) vale 8 horas, ou 30 dias com "lembrar de mim".
 */
class AuthService
{
    public function __construct(private TwoFactorService $doisFatores, private CodigoEmailService $codigos)
    {
    }

    /*
     * Etapa 1 do login. $identificador = e-mail (se tiver "@") ou login de 6 letras.
     */
    public function login(string $identificador, string $senha, bool $lembrar): array
    {
        $identificador = mb_strtolower(trim($identificador));
        $porEmail = str_contains($identificador, '@');

        $pessoa = Pessoa::query()->where($porEmail ? 'email' : 'login', $identificador)->first();
        $empresa = (! $pessoa && $porEmail) ? Empresa::query()->where('email', $identificador)->first() : null;

        $conta = $pessoa ?? $empresa;

        // Sem conta: confere um hash "de mentira" para a resposta demorar o mesmo tempo
        if (! $conta) {
            Hash::check($senha, '$2y$12$Jb3GfKosd6H2lyJJKPqY0.HImy1Bs92PsxrgRiUg1.qds4VjY6XPW');
            throw $this->credenciaisInvalidas();
        }

        if (! Hash::check($senha, $conta->senha_hash)) {
            $this->registrarLog($pessoa, $empresa, 'senha', false);
            throw $this->credenciaisInvalidas();
        }

        if ($empresa) {
            if ($empresa->status !== 'ativa') {
                $this->registrarLog(null, $empresa, 'senha', false);
                throw new ApiException(403, 'empresa_suspensa', 'Esta empresa está suspensa. Fale com o administrador do EcoTech.');
            }
            $this->registrarLog(null, $empresa, 'senha', true);
            if (! CodigoEmailService::ativo()) {
                return $this->emitirToken($empresa, $lembrar);
            }
            $desafio = $this->codigos->criarOuFalhar($empresa, $empresa->email, 'login', $lembrar);

            return $this->codigos->dados($desafio)
                + ['message' => "Enviamos um código de 6 dígitos para {$this->codigos->mascarar($empresa->email)}."];
        }

        if (! $pessoa->ativo) {
            throw $this->credenciaisInvalidas();
        }

        // Master (administrador) entra só com login e senha: sem pergunta e sem código
        if ($pessoa->ehMaster()) {
            $this->registrarLog($pessoa, null, 'senha', true);
            return $this->emitirToken($pessoa, $lembrar);
        }

        // Cadastro com e-mail ainda não confirmado: manda (de novo) o código de confirmação
        if (! $pessoa->email_confirmado && CodigoEmailService::ativo()) {
            $this->registrarLog($pessoa, null, 'senha', true);
            return $this->pedirConfirmacaoEmail($pessoa);
        }

        // Pessoa: a senha está certa, agora vem a pergunta de segurança
        $desafio = $this->doisFatores->criarParaLogin($pessoa, $lembrar);
        $this->registrarLog($pessoa, null, 'senha', true);

        return ['status' => '2fa'] + $this->doisFatores->dadosPergunta($desafio)
            + ['message' => 'Responda a pergunta de segurança para entrar.'];
    }

    // Etapa 2 do login (pessoa): confere a resposta da pergunta e manda o código por e-mail
    public function concluirDoisFatores(string $desafioId, string $resposta, ?bool $lembrar): array
    {
        $desafio = $this->doisFatores->verificar(
            $desafioId,
            'login',
            $resposta,
            aoErrar: fn (DesafioVerificacao $d) => $d->pessoa && $this->registrarLog(
                $d->pessoa, null, '2fa', false, TwoFactorService::PERGUNTAS[$d->pergunta], $d->tentativas
            ),
            aoAcertar: fn (DesafioVerificacao $d) => $this->registrarLog(
                $d->pessoa, null, '2fa', true, TwoFactorService::PERGUNTAS[$d->pergunta], $d->tentativas + 1
            ),
        );

        $pessoa = $desafio->pessoa;
        if (! CodigoEmailService::ativo()) {
            return $this->emitirToken($pessoa, $lembrar ?? $desafio->lembrar);
        }
        $codigo = $this->codigos->criarOuFalhar($pessoa, $pessoa->email, 'login', $lembrar ?? $desafio->lembrar);

        return $this->codigos->dados($codigo)
            + ['message' => "Resposta certa! Agora digite o código que enviamos para {$this->codigos->mascarar($pessoa->email)}."];
    }

    // Última etapa do login (pessoa ou empresa): confere o código do e-mail e entrega o token
    public function concluirCodigo(string $desafioId, string $codigo, ?bool $lembrar): array
    {
        $log = fn (CodigoEmail $d, bool $ok) => $this->registrarLog(
            $d->pessoa, $d->pessoa ? null : $d->empresa, 'codigo_email', $ok, CodigoEmailService::METODO_LOG, $d->tentativas + ($ok ? 1 : 0)
        );

        $desafio = $this->codigos->verificar(
            $desafioId,
            'login',
            $codigo,
            aoErrar: fn (CodigoEmail $d) => $log($d, false),
            aoAcertar: fn (CodigoEmail $d) => $log($d, true),
        );

        return $this->emitirToken($desafio->conta(), $lembrar ?? $desafio->lembrar);
    }

    // Cadastro novo (ou login de quem não confirmou): manda o código de confirmação do e-mail
    public function pedirConfirmacaoEmail(Pessoa $pessoa): array
    {
        $desafio = $this->codigos->criar($pessoa, $pessoa->email, 'confirmar_email');
        $email = $this->codigos->mascarar($pessoa->email);

        return $this->codigos->dados($desafio) + ['message' => $desafio->emailEnviado
            ? "Confirme seu e-mail: enviamos um código de 6 dígitos para {$email}."
            : 'Não conseguimos enviar o código agora. Toque em "Reenviar código".'];
    }

    // POST /auth/confirmar-email: confere o código e marca o e-mail como confirmado
    public function confirmarEmail(string $desafioId, string $codigo): Pessoa
    {
        $desafio = $this->codigos->verificar(
            $desafioId,
            'confirmar_email',
            $codigo,
            aoErrar: fn (CodigoEmail $d) => $this->registrarLog($d->pessoa, null, 'confirmar_email', false, CodigoEmailService::METODO_LOG, $d->tentativas),
        );

        $pessoa = $desafio->pessoa;
        $pessoa->forceFill(['email_confirmado' => true])->save();
        $this->registrarLog($pessoa, null, 'confirmar_email', true, CodigoEmailService::METODO_LOG, $desafio->tentativas + 1);

        return $pessoa;
    }

    public function emitirToken(Pessoa|Empresa $conta, bool $lembrar = false): array
    {
        $abilities = $conta instanceof Empresa
            ? ['empresa']
            : ($conta->ehMaster() ? ['pessoa', 'master'] : ['pessoa']);

        $expira = $lembrar
            ? now()->addDays(config('ecotech.token_lembrar_dias', 30))
            : now()->addHours(config('ecotech.token_horas', 8));

        $novo = $conta->createToken('api', $abilities, $expira);

        return [
            'token'      => $novo->plainTextToken,
            'token_tipo' => 'Bearer',
            'expira_em'  => Formato::iso($expira),
            'usuario'    => $this->usuario($conta->refresh()),
            'message'    => 'Login realizado.',
        ];
    }

    // Quem está logado, no formato combinado com o front (empresa tem "cnpj")
    public function usuario(Pessoa|Empresa $conta): array
    {
        if ($conta instanceof Empresa) {
            return [
                'id'             => $conta->id,
                'nome'           => $conta->nome_empresa,
                'email'          => $conta->email,
                'tipo'           => 'empresa',
                'perfil'         => 'empresa',
                'avatar'         => null,
                'coins'          => null,
                'cnpj'           => $conta->cnpj,
                'cnpj_formatado' => Formato::cnpj($conta->cnpj),
            ];
        }

        return [
            'id'              => $conta->id,
            'nome'            => $conta->nome,
            'email'           => $conta->email,
            'login'           => $conta->login,
            'tipo'            => 'pessoa',
            'perfil'          => $conta->perfil,
            'avatar'          => $conta->avatar_url,
            'coins'           => (int) $conta->saldo_coins,
            'coins_formatado' => Formato::inteiro($conta->saldo_coins),
        ];
    }

    public function logout(Pessoa|Empresa $conta): void
    {
        $conta->currentAccessToken()?->delete();
    }

    // Grava uma linha no log de autenticação (precisa de uma pessoa OU uma empresa)
    public function registrarLog(?Pessoa $pessoa, ?Empresa $empresa, string $evento, bool $sucesso, ?string $metodo2fa = null, ?int $tentativa = null): void
    {
        if (! $pessoa && ! $empresa) {
            return;
        }

        $request = request();
        $ip = $request?->ip();

        LogAutenticacao::create([
            'pessoa_id'   => $pessoa?->id,
            'empresa_id'  => $pessoa ? null : $empresa?->id,
            'evento'      => $evento,
            'data_hora'   => now(),
            'metodo_2fa'  => $metodo2fa,
            'sucesso'     => $sucesso,
            'tentativa_n' => $tentativa,
            'ip'          => $ip ? (@inet_pton($ip) ?: null) : null,
            'user_agent'  => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }

    private function credenciaisInvalidas(): ApiException
    {
        return new ApiException(401, 'credenciais_invalidas', 'E-mail/login ou senha incorretos.');
    }
}
