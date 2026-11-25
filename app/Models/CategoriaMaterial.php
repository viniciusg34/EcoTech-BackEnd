<?php

namespace App\Models;

class CategoriaMaterial extends Modelo
{
    protected $table = 'categoria_material';

    protected $casts = [
        'coins_por_unidade' => 'integer',
    ];
}
