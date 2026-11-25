<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\MovimentoCoins;
use App\Models\Pessoa;
use App\Support\Formato;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/*
 * MOEDAS (EcoCoins) — o ÚNICO lugar que muda o saldo de alguém.
 * ---------------------------------------------------------------
 * Cada crédito ou débito:
 *   1. abre uma transação no banco;
 *   2. trava a linha da pessoa (lockForUpdate): se dois pedidos chegarem juntos,
 *      o segundo espera o primeiro terminar (evita gastar o mesmo saldo duas vezes);
 *   3. confere que o saldo não fica negativo;
 *   4. grava a linha no livro-razão (movimento_coins) e atualiza o saldo.
 * O front nunca manda quantidade de moedas: quem calcula é o servidor.
 */
class CoinService
{
    public function creditar(int $pessoaId, int $valor, string $tipo, string $descricao, ?string $origemTipo = null, ?int $origemId = null, ?string $subtitulo = null, ?string $icone = null): MovimentoCoins
    {
        if ($valor <= 0) {
            throw new InvalidArgumentException('Crédito precisa ser positivo.');
        }
        return $this->lancar($pessoaId, $valor, $tipo, $descricao, $origemTipo, $origemId, $subtitulo, $icone);
    }

    public function debitar(int $pessoaId, int $valor, string $tipo, string $descricao, ?string $origemTipo = null, ?int $origemId = null, ?string $subtitulo = null, ?string $icone = null): MovimentoCoins
    {
        if ($valor <= 0) {
            throw new InvalidArgumentException('Débito precisa ser positivo.');
        }
        return $this->lancar($pessoaId, -$valor, $tipo, $descricao, $origemTipo, $origemId, $subtitulo, $icone);
    }

    // $valor positivo = entra, negativo = sai
    public function lancar(int $pessoaId, int $valor, string $tipo, string $descricao, ?string $origemTipo = null, ?int $origemId = null, ?string $subtitulo = null, ?string $icone = null): MovimentoCoins
    {
        if ($valor === 0) {
            throw new InvalidArgumentException('Movimento de valor zero.');
        }

        return DB::transaction(function () use ($pessoaId, $valor, $tipo, $descricao, $origemTipo, $origemId, $subtitulo, $icone) {
            $saldoAtual = (int) Pessoa::query()->whereKey($pessoaId)->lockForUpdate()->value('saldo_coins');
            $novoSaldo = $saldoAtual + $valor;

            if ($novoSaldo < 0) {
                throw new ApiException(409, 'saldo_insuficiente', 'Saldo insuficiente.', [], [
                    'balance'           => $saldoAtual,
                    'balance_formatado' => Formato::inteiro($saldoAtual),
                ]);
            }

            Pessoa::query()->whereKey($pessoaId)->update(['saldo_coins' => $novoSaldo]);

            return MovimentoCoins::create([
                'pessoa_id'   => $pessoaId,
                'tipo'        => $tipo,
                'valor'       => $valor,
                'saldo_apos'  => $novoSaldo,
                'origem_tipo' => $origemTipo,
                'origem_id'   => $origemId,
                'descricao'   => mb_substr($descricao, 0, 255),
                'subtitulo'   => $subtitulo !== null ? mb_substr($subtitulo, 0, 150) : null,
                'icone'       => $icone,
            ]);
        });
    }

    public function saldo(int $pessoaId): int
    {
        return (int) Pessoa::query()->whereKey($pessoaId)->value('saldo_coins');
    }

    public function saldoResposta(int $pessoaId): array
    {
        $saldo = $this->saldo($pessoaId);
        return ['balance' => $saldo, 'balance_formatado' => Formato::inteiro($saldo)];
    }
}
