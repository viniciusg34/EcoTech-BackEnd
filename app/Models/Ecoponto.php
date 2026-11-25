<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ecoponto extends Modelo
{
    protected $table = 'ecoponto';

    // segredo_qr nunca sai em JSON (é com ele que o QR é assinado)
    protected $hidden = ['segredo_qr'];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(CategoriaMaterial::class, 'ecoponto_categoria', 'ecoponto_id', 'categoria_id')
            ->withTimestamps('criado_em', 'atualizado_em');
    }

    public function coletas(): HasMany
    {
        return $this->hasMany(Coleta::class, 'ecoponto_id');
    }

    public function sessoes(): HasMany
    {
        return $this->hasMany(SessaoEcoponto::class, 'ecoponto_id');
    }

    // Na URL pode vir o id (3) ou o código (ECO-01)
    public function resolveRouteBinding($value, $field = null)
    {
        $coluna = $field ?? (ctype_digit((string) $value) ? 'id' : 'codigo');
        return $this->where($coluna, $value)->firstOrFail();
    }

    // Soma de kg coletado (calculado na consulta: o total não fica guardado no ecoponto)
    public function scopeComKgColetado(Builder $query): Builder
    {
        return $query->withSum('coletas as kg_coletado', 'total_kg')
            ->withMax('coletas as ultima_atividade', 'criado_em');
    }
}
