<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\DesafioVerificacao;
use App\Models\Empresa;
use App\Models\LogAutenticacao;
use App\Models\Pessoa;
use App\Support\Formato;
use Illuminate\Support\Facades\Hash;

/*
 * LOGIN, TOKEN E LOG DE AUTENTICAÇÃO
 * ---------------------------------------------------------------
 * Pessoa (comum ou master): senha certa -> pergunta do 2FA -> token.
 * Empresa: senha certa -> token direto.
 * O token (Sanctum) vale 8 horas, ou 30 dias com "lembrar de mim".
 */
class AuthService
{
    public function __construct(private TwoFactorService $doisFatores)
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
            return $this->emitirToken($empresa, $lembrar);
        }

        if (! $pessoa->ativo) {
            throw $this->credenciaisInvalidas();
        }

        // Pessoa: a senha está certa, agora vem a pergunta de segurança
        $desafio = $this->doisFatores->criarParaLogin($pessoa, $lembrar);
        $this->registrarLog($pessoa, null, 'senha', true);

        return ['status' => '2fa'] + $this->doisFatores->dadosPergunta($desafio)
            + ['message' => 'Responda a pergunta de segurança para entrar.'];
    }

    // Etapa 2 do login (pessoa): confere a resposta do 2FA e entrega o token
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

        return $this->emitirToken($desafio->pessoa, $lembrar ?? $desafio->lembrar);
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
