<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resgate extends Modelo
{
    protected $table = 'resgate';

    protected $casts = [
        'custo_coins' => 'integer',
    ];

    public function recompensa(): BelongsTo
    {
        return $this->belongsTo(Recompensa::class, 'recompensa_id');
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }
}
