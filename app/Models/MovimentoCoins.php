<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/*
 * Livro-razão das moedas: SÓ INSERT.
 * Se alguém tentar alterar ou apagar uma linha, dá erro (o histórico não muda).
 * Quem cria as linhas é o CoinService.
 */
class MovimentoCoins extends Modelo
{
    protected $table = 'movimento_coins';

    protected $casts = [
        'valor'      => 'integer',
        'saldo_apos' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('movimento_coins é imutável: não pode ser alterado.'));
        static::deleting(fn () => throw new LogicException('movimento_coins é imutável: não pode ser apagado.'));
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }
}
