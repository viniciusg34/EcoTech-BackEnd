<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coleta extends Modelo
{
    protected $table = 'coleta';

    protected $casts = [
        'total_coins' => 'integer',
        'total_kg'    => 'float',
    ];

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function ecoponto(): BelongsTo
    {
        return $this->belongsTo(Ecoponto::class, 'ecoponto_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ColetaItem::class, 'coleta_id');
    }
}
