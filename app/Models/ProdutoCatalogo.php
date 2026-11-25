<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 * Produto da campanha. A chave é o próprio código de barras (texto, não auto-incremento).
 */
class ProdutoCatalogo extends Modelo
{
    protected $table = 'produto_catalogo';
    protected $primaryKey = 'codigo_barras';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'coins'             => 'integer',
        'peso_kg'           => 'float',
        'ativo'             => 'boolean',
        'off_consultado_em' => 'datetime',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaMaterial::class, 'categoria_id');
    }

    // Participa da campanha = ativo, com categoria e valendo moedas
    public function participa(): bool
    {
        return $this->ativo && $this->categoria_id !== null && $this->coins > 0;
    }
}
