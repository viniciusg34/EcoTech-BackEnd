<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeituraCodigo extends Modelo
{
    protected $table = 'leitura_codigo';

    protected $casts = [
        'coins_creditados' => 'integer',
        'lido_em'          => 'datetime',
    ];

    public function ecoponto(): BelongsTo
    {
        return $this->belongsTo(Ecoponto::class, 'ecoponto_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(ProdutoCatalogo::class, 'produto_codigo', 'codigo_barras');
    }
}
