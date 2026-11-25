<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Pessoa;
use App\Models\Recompensa;
use App\Models\Resgate;
use Illuminate\Support\Facades\DB;

/*
 * RESGATE: troca moedas por uma recompensa e gera o código do cupom.
 * Tudo numa transação: se o saldo não der, nada é gravado.
 * Ordem das travas: pessoa, depois recompensa (sempre a mesma ordem evita "deadlock").
 */
class ResgateService
{
    public function __construct(private CoinService $coins)
    {
    }

    public function resgatar(Pessoa $pessoa, int $recompensaId): Resgate
    {
        return DB::transaction(function () use ($pessoa, $recompensaId) {
            Pessoa::query()->whereKey($pessoa->id)->lockForUpdate()->first();
            $recompensa = Recompensa::query()->whereKey($recompensaId)->lockForUpdate()->first();

            if (! $recompensa || ! $recompensa->ativa) {
                throw new ApiException(404, 'recompensa_indisponivel', 'Esta recompensa não está disponível.');
            }
            if ($recompensa->estoque !== null && $recompensa->estoque <= 0) {
                throw new ApiException(409, 'sem_estoque', 'Esta recompensa esgotou.');
            }

            $resgate = Resgate::create([
                'pessoa_id'     => $pessoa->id,
                'recompensa_id' => $recompensa->id,
                'custo_coins'   => $recompensa->custo_coins,
                'codigo_cupom'  => $this->novoCupom(),
                'status'        => 'emitido',
            ]);

            // Se o saldo não der, o CoinService lança 409 "saldo_insuficiente" e a transação desfaz o resgate
            $this->coins->debitar(
                $pessoa->id, $recompensa->custo_coins, 'resgate', $recompensa->nome,
                'resgate', $resgate->id, 'Cupom ' . $resgate->codigo_cupom, $recompensa->icone
            );

            if ($recompensa->estoque !== null) {
                $recompensa->decrement('estoque');
            }

            return $resgate->load('recompensa');
        });
    }

    // "CUP-7KQ2XM" (sem letras que confundem, como O/0 e I/1)
    private function novoCupom(): string
    {
        $letras = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $codigo = 'CUP-';
            for ($i = 0; $i < 6; $i++) {
                $codigo .= $letras[random_int(0, strlen($letras) - 1)];
            }
        } while (Resgate::query()->where('codigo_cupom', $codigo)->exists());

        return $codigo;
    }
}
