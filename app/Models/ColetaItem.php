<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColetaItem extends Modelo
{
    protected $table = 'coleta_item';

    protected $casts = [
        'quantidade' => 'float',
        'peso_kg'    => 'float',
        'coins'      => 'integer',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaMaterial::class, 'categoria_id');
    }
}
