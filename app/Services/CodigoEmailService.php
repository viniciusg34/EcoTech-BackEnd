<?php

namespace App\Services;

use App\Clients\EmailClient;
use App\Exceptions\ApiException;
use App\Models\CodigoEmail;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Support\Formato;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * CÓDIGO DE 6 DÍGITOS POR E-MAIL
 * ---------------------------------------------------------------
 * criar()      gera o código, guarda só o hash e manda o e-mail
 * verificar()  confere o código digitado (5 erros = bloqueado)
 * reenviar()   gera um código novo para o mesmo desafio (espera 60 s entre envios)
 * dados()      o que o front recebe: desafio_id, e-mail mascarado, prazos
 *
 * Finalidades: confirmar_email (cadastro), login, recuperar_senha.
 */
class CodigoEmailService
{
    public const MINUTOS_VALIDADE = 10;
    public const MAX_TENTATIVAS = 5;
    public const SEGUNDOS_REENVIO = 60;
    public const MAX_REENVIOS = 5;

    // Texto que aparece no log de autenticação (coluna metodo_2fa)
    public const METODO_LOG = 'Código por e-mail';

    private const MOTIVOS = [
        'confirmar_email' => ['Confirme seu e-mail', 'Use o código abaixo para confirmar o seu e-mail e ativar sua conta na EcoTech.'],
        'login'           => ['Seu código de acesso', 'Use o código abaixo para terminar de entrar na sua conta EcoTech.'],
        'recuperar_senha' => ['Recuperação de senha', 'Recebemos um pedido para redefinir a sua senha. Use o código abaixo para continuar.'],
    ];

    public function __construct(private EmailClient $email)
    {
    }

    /*
     * Cria o desafio e manda o e-mail.
     * Sem conta (recuperação de senha com e-mail que não existe): cria um desafio "falso",
     * que nunca aceita código, e não envia nada. Assim ninguém descobre quem tem conta.
     * Se o envio falhar, o desafio fica criado e "email_enviado" volta false
     * (a pessoa pode tocar em "Reenviar código").
     */
    public function criar(Pessoa|Empresa|null $conta, string $email, string $finalidade, bool $lembrar = false): CodigoEmail
    {
        $codigo = $this->gerarCodigo($email);

        $desafio = CodigoEmail::create([
            'uuid'           => (string) Str::uuid(),
            'pessoa_id'      => $conta instanceof Pessoa ? $conta->id : null,
            'empresa_id'     => $conta instanceof Empresa ? $conta->id : null,
            'email'          => mb_strtolower(trim($email)),
            'finalidade'     => $finalidade,
            'codigo_hash'    => $this->hash($codigo),
            'tentativas'     => 0,
            'max_tentativas' => self::MAX_TENTATIVAS,
            'reenvios'       => 0,
            'lembrar'        => $lembrar,
            'enviado_em'     => now(),
            'expira_em'      => now()->addMinutes(self::MINUTOS_VALIDADE),
        ]);

        $desafio->emailEnviado = ($conta && ! $this->ehDemo($email)) ? $this->enviarEmail($desafio, $conta, $codigo) : true;

        return $desafio;
    }

    // Igual ao criar(), mas responde 502 se o e-mail não saiu (login e recuperação)
    public function criarOuFalhar(Pessoa|Empresa|null $conta, string $email, string $finalidade, bool $lembrar = false): CodigoEmail
    {
        $desafio = $this->criar($conta, $email, $finalidade, $lembrar);
        if (! $desafio->emailEnviado) {
            throw $this->erroEnvio();
        }

        return $desafio;
    }

    // Resposta padrão para o front
    public function dados(CodigoEmail $desafio): array
    {
        return [
            'status'               => 'codigo_email',
            'finalidade'           => $desafio->finalidade,
            'desafio_id'           => $desafio->uuid,
            'email'                => $this->mascarar($desafio->email),
            'digitos'              => 6,
            'tentativas_restantes' => $desafio->tentativasRestantes(),
            'expira_em'            => Formato::iso($desafio->expira_em),
            'reenviar_em'          => Formato::iso($desafio->enviado_em->copy()->addSeconds(self::SEGUNDOS_REENVIO)),
            'email_enviado'        => $desafio->emailEnviado,
        ];
    }

    /*
     * Confere o código. Devolve o desafio resolvido ou lança:
     *   410 inexistente/expirado/já usado | 423 bloqueado | 422 código errado
     * $finalidades: uma ou mais (ex.: ['login']).
     */
    public function verificar(string $uuid, string|array $finalidades, string $codigo, ?callable $aoErrar = null, ?callable $aoAcertar = null): CodigoEmail
    {
        $codigo = preg_replace('/\D/', '', $codigo);

        [$situacao, $desafio] = DB::transaction(function () use ($uuid, $finalidades, $codigo) {
            $desafio = CodigoEmail::query()
                ->where('uuid', $uuid)->whereIn('finalidade', (array) $finalidades)
                ->lockForUpdate()->first();

            if (! $desafio) {
                return ['inexistente', null];
            }
            if ($desafio->resolvido_em) {
                return ['usado', $desafio];
            }
            if ($desafio->bloqueado_em) {
                return ['bloqueado', $desafio];
            }
            if ($desafio->expira_em->isPast()) {
                return ['expirado', $desafio];
            }

            $conta = $desafio->conta();
            $contaAtiva = $conta instanceof Pessoa ? $conta->ativo : ($conta instanceof Empresa && $conta->status === 'ativa');

            if ($contaAtiva && strlen($codigo) === 6 && hash_equals($desafio->codigo_hash, $this->hash($codigo))) {
                $desafio->resolvido_em = now();
                $desafio->save();
                return ['ok', $desafio];
            }

            $desafio->tentativas++;
            if ($desafio->tentativas >= $desafio->max_tentativas) {
                $desafio->bloqueado_em = now();
            }
            $desafio->save();
            return [$desafio->bloqueado_em ? 'errou_bloqueou' : 'errou', $desafio];
        });

        if (in_array($situacao, ['errou', 'errou_bloqueou'], true) && $aoErrar) {
            $aoErrar($desafio);
        }
        if ($situacao === 'ok' && $aoAcertar) {
            $aoAcertar($desafio);
        }

        $fim = ['tentativas_restantes' => 0];
        $restantes = $desafio?->tentativasRestantes() ?? 0;

        return match ($situacao) {
            'ok'          => $desafio,
            'inexistente' => throw new ApiException(410, 'codigo_expirado', 'Código expirado ou inexistente. Comece de novo.', [], $fim),
            'usado'       => throw new ApiException(410, 'codigo_expirado', 'Este código já foi usado. Comece de novo.', [], $fim),
            'expirado'    => throw new ApiException(410, 'codigo_expirado', 'O código expirou. Peça um novo código.', [], $fim + ['pode_reenviar' => true]),
            'bloqueado', 'errou_bloqueou' => throw new ApiException(423, 'codigo_bloqueado', 'Muitas tentativas erradas. Comece de novo.', [], $fim),
            default       => throw new ApiException(422, 'codigo_invalido',
                'Código incorreto. ' . ($restantes === 1 ? 'Você tem mais 1 tentativa.' : "Você tem mais {$restantes} tentativas."),
                ['codigo' => ['Código incorreto.']], ['tentativas_restantes' => $restantes]),
        };
    }

    /*
     * Gera um código NOVO para o mesmo desafio (o anterior deixa de valer) e reenvia.
     * Espera 60 s entre envios e no máximo 5 reenvios. As tentativas erradas continuam contando.
     */
    public function reenviar(string $uuid): CodigoEmail
    {
        $desafio = DB::transaction(function () use ($uuid, &$novoCodigo) {
            $desafio = CodigoEmail::query()->where('uuid', $uuid)->lockForUpdate()->first();

            if (! $desafio || $desafio->resolvido_em) {
                throw new ApiException(410, 'codigo_expirado', 'Este código não pode mais ser reenviado. Comece de novo.');
            }
            if ($desafio->bloqueado_em) {
                throw new ApiException(423, 'codigo_bloqueado', 'Muitas tentativas erradas. Comece de novo.');
            }
            if ($desafio->reenvios >= self::MAX_REENVIOS) {
                throw new ApiException(429, 'limite_reenvios', 'Você já pediu muitos códigos. Comece de novo.');
            }

            $espera = (int) ceil(now()->diffInSeconds($desafio->enviado_em->copy()->addSeconds(self::SEGUNDOS_REENVIO), false));
            if ($espera > 0) {
                throw new ApiException(429, 'aguarde_reenvio', "Aguarde {$espera} segundos para pedir outro código.", [], ['aguarde_segundos' => $espera]);
            }

            $novoCodigo = $this->gerarCodigo($desafio->email);
            $desafio->forceFill([
                'codigo_hash' => $this->hash($novoCodigo),
                'reenvios'    => $desafio->reenvios + 1,
                'enviado_em'  => now(),
                'expira_em'   => now()->addMinutes(self::MINUTOS_VALIDADE),
            ])->save();

            return $desafio;
        });

        $conta = $desafio->conta();
        if ($conta && ! $this->ehDemo($desafio->email) && ! $this->enviarEmail($desafio, $conta, $novoCodigo)) {
            throw $this->erroEnvio();
        }

        return $desafio;
    }

    // "joao.silva@gmail.com" -> "jo********@gmail.com"
    public function mascarar(string $email): string
    {
        [$usuario, $dominio] = array_pad(explode('@', $email, 2), 2, '');
        $visivel = mb_substr($usuario, 0, min(2, max(1, mb_strlen($usuario) - 1)));

        return $visivel . str_repeat('*', max(3, mb_strlen($usuario) - mb_strlen($visivel))) . '@' . $dominio;
    }

    private function enviarEmail(CodigoEmail $desafio, Pessoa|Empresa $conta, string $codigo): bool
    {
        [$assunto, $motivo] = self::MOTIVOS[$desafio->finalidade];
        $nome = $conta instanceof Empresa ? $conta->nome_empresa : Str::before((string) $conta->nome, ' ');
        $assunto = "{$codigo} é o seu código EcoTech — {$assunto}";

        $html = view('emails.codigo', [
            'nome'    => $nome,
            'motivo'  => $motivo,
            'codigo'  => $codigo,
            'minutos' => self::MINUTOS_VALIDADE,
        ])->render();

        $texto = "Olá, {$nome}!\n\n{$motivo}\n\nCódigo: {$codigo}\n\nO código vale por " . self::MINUTOS_VALIDADE
            . " minutos. Se não foi você, ignore este e-mail.";

        return $this->email->enviar($desafio->email, $assunto, $html, $texto);
    }

    /*
     * Contas de exemplo (seed) usam e-mails "@....example", domínio reservado que nunca
     * recebe e-mail. Para elas não enviamos nada: o código é o CODIGO_DEMO do .env.
     * Sem CODIGO_DEMO, o código é aleatório e só aparece no log do servidor.
     */
    public function ehDemo(string $email): bool
    {
        return str_ends_with(mb_strtolower(trim($email)), '.example');
    }

    private function gerarCodigo(string $email): string
    {
        $demo = preg_replace('/\D/', '', (string) config('ecotech.codigo_demo'));
        if ($this->ehDemo($email)) {
            $codigo = strlen($demo) === 6 ? $demo : str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            \Illuminate\Support\Facades\Log::info("[conta de exemplo] código para {$email}: {$codigo}");

            return $codigo;
        }

        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    // HMAC com a APP_KEY: mesmo vendo o banco, não dá para descobrir o código
    private function hash(string $codigo): string
    {
        return hash_hmac('sha256', $codigo, (string) config('app.key'));
    }

    private function erroEnvio(): ApiException
    {
        return new ApiException(502, 'email_nao_enviado', 'Não conseguimos enviar o e-mail com o código agora. Tente de novo em instantes.');
    }
}
