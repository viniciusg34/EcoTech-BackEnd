<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\DesafioVerificacao;
use App\Models\Pessoa;
use App\Support\Texto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * SEGUNDO FATOR (2FA) E RECUPERAÇÃO DE SENHA
 * ---------------------------------------------------------------
 * Cria a pergunta (desafio) e confere a resposta NO SERVIDOR.
 * Regras: no máximo 3 tentativas, vale por 5 minutos, depois fica bloqueado.
 * A pergunta de um desafio não muda a cada erro.
 */
class TwoFactorService
{
    public const PERGUNTAS = [
        'nome_mae'   => 'Qual o nome da sua mãe?',
        'nascimento' => 'Qual a data do seu nascimento?',
        'cep'        => 'Qual o CEP do seu endereço?',
    ];

    // Mensagem pedida pelo enunciado do projeto
    public const MENSAGEM_BLOQUEIO = '3 tentativas sem sucesso! Favor realizar Login novamente.';

    public const MINUTOS_VALIDADE = 5;

    // Perguntas que esta pessoa consegue responder (só as que têm dado no cadastro)
    public function perguntasDisponiveis(Pessoa $pessoa): array
    {
        return array_values(array_filter([
            filled($pessoa->nome_mae) ? 'nome_mae' : null,
            $pessoa->nascimento ? 'nascimento' : null,
            filled($pessoa->cep) ? 'cep' : null,
        ]));
    }

    // 2FA do login: pergunta sorteada
    public function criarParaLogin(Pessoa $pessoa, bool $lembrar): DesafioVerificacao
    {
        $opcoes = $this->perguntasDisponiveis($pessoa);
        if (! $opcoes) {
            throw new ApiException(409, 'cadastro_incompleto', 'Seu cadastro não tem dados para a pergunta de segurança. Procure o administrador.');
        }

        return $this->novo($pessoa, 'login', $opcoes[random_int(0, count($opcoes) - 1)], $lembrar);
    }

    /*
     * Recuperação de senha: SEMPRE devolve um desafio, exista ou não a conta.
     * Para e-mail que não existe, o desafio é "falso" (sem pessoa) e nunca aceita resposta.
     * A pergunta é sempre a mesma para o mesmo e-mail (real ou não), para ninguém
     * descobrir quais e-mails têm conta pedindo várias vezes.
     */
    public function criarParaRecuperacao(string $email, ?Pessoa $pessoa): DesafioVerificacao
    {
        $todas = array_keys(self::PERGUNTAS);
        $opcoes = $pessoa ? ($this->perguntasDisponiveis($pessoa) ?: $todas) : $todas;
        $indice = hexdec(substr(hash_hmac('sha256', mb_strtolower(trim($email)), (string) config('app.key')), 0, 8));

        return $this->novo($pessoa, 'recuperar_senha', $opcoes[$indice % count($opcoes)], false);
    }

    private function novo(?Pessoa $pessoa, string $finalidade, string $pergunta, bool $lembrar): DesafioVerificacao
    {
        return DesafioVerificacao::create([
            'uuid'           => (string) Str::uuid(),
            'conta_tipo'     => 'pessoa',
            'pessoa_id'      => $pessoa?->id,
            'finalidade'     => $finalidade,
            'pergunta'       => $pergunta,
            'tentativas'     => 0,
            'max_tentativas' => 3,
            'lembrar'        => $lembrar,
            'expira_em'      => now()->addMinutes(self::MINUTOS_VALIDADE),
        ]);
    }

    public function dadosPergunta(DesafioVerificacao $desafio): array
    {
        return [
            'desafio_id'           => $desafio->uuid,
            'pergunta'             => self::PERGUNTAS[$desafio->pergunta],
            'pergunta_tipo'        => $desafio->pergunta,
            'tentativas_restantes' => $desafio->tentativasRestantes(),
            'expira_em'            => \App\Support\Formato::iso($desafio->expira_em),
        ];
    }

    /*
     * Confere a resposta. Devolve o desafio resolvido ou lança o erro certo:
     *   410 desafio inexistente/expirado/já usado | 423 bloqueado | 422 resposta errada
     * $aoErrar e $aoAcertar servem para gravar o log de autenticação.
     */
    public function verificar(string $uuid, string $finalidade, string $resposta, ?callable $aoErrar = null, ?callable $aoAcertar = null): DesafioVerificacao
    {
        // A tentativa é gravada ANTES de lançar o erro (a transação precisa terminar primeiro)
        [$situacao, $desafio] = DB::transaction(function () use ($uuid, $finalidade, $resposta) {
            $desafio = DesafioVerificacao::query()
                ->where('uuid', $uuid)->where('finalidade', $finalidade)
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

            if ($desafio->pessoa && $desafio->pessoa->ativo && $this->confere($desafio->pergunta, $resposta, $desafio->pessoa)) {
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

        $semTentativas = ['tentativas_restantes' => 0];

        return match ($situacao) {
            'ok'             => $desafio,
            'inexistente'    => throw new ApiException(410, 'desafio_expirado', 'Desafio expirado ou inexistente. Faça login de novo.', [], $semTentativas),
            'usado'          => throw new ApiException(410, 'desafio_expirado', 'Este desafio já foi usado (expirado). Faça login de novo.', [], $semTentativas),
            'expirado'       => throw new ApiException(410, 'desafio_expirado', 'Desafio expirado: o tempo para responder acabou. Faça login de novo.', [], $semTentativas),
            'bloqueado', 'errou_bloqueou' => throw new ApiException(423, 'desafio_bloqueado', self::MENSAGEM_BLOQUEIO, [], $semTentativas),
            default          => throw new ApiException(422, 'resposta_invalida',
                'Resposta incorreta. ' . ($desafio->tentativasRestantes() === 1 ? 'Você tem mais 1 tentativa.' : "Você tem mais {$desafio->tentativasRestantes()} tentativas."),
                [], ['tentativas_restantes' => $desafio->tentativasRestantes()]),
        };
    }

    /*
     * Compara a resposta com o cadastro:
     *   nome da mãe: sem acento e sem diferença de maiúsculas ("MARIA JOSÉ" = "maria jose")
     *   nascimento: dd/mm/aaaa (também aceita aaaa-mm-dd)
     *   CEP: só os dígitos ("20040-020" = "20040020")
     */
    public function confere(string $pergunta, string $resposta, Pessoa $pessoa): bool
    {
        return match ($pergunta) {
            'nome_mae'   => filled($pessoa->nome_mae) && Texto::normalizar($resposta) === Texto::normalizar($pessoa->nome_mae),
            'nascimento' => $pessoa->nascimento !== null && Texto::data($resposta) === $pessoa->nascimento->format('Y-m-d'),
            'cep'        => filled($pessoa->cep) && Texto::digitos($resposta) === $pessoa->cep,
            default      => false,
        };
    }
}
