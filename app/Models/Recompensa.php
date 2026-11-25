<?php

namespace App\Models;

class Recompensa extends Modelo
{
    protected $table = 'recompensa';

    protected $casts = [
        'custo_coins' => 'integer',
        'estoque'     => 'integer',
        'ativa'       => 'boolean',
    ];
}
