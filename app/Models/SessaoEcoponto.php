<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessaoEcoponto extends Modelo
{
    protected $table = 'sessao_ecoponto';

    protected $hidden = ['token'];

    protected $casts = [
        'iniciada_em'  => 'datetime',
        'expira_em'    => 'datetime',
        'encerrada_em' => 'datetime',
    ];

    public function ecoponto(): BelongsTo
    {
        return $this->belongsTo(Ecoponto::class, 'ecoponto_id');
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    // Só as sessões ativas e ainda dentro do prazo
    public function scopeValidas(Builder $query): Builder
    {
        return $query->where('status', 'ativa')->where('expira_em', '>', now());
    }

    public function estaValida(): bool
    {
        return $this->status === 'ativa' && $this->expira_em->isFuture();
    }
}
