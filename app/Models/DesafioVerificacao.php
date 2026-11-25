<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesafioVerificacao extends Modelo
{
    protected $table = 'desafio_verificacao';

    protected $hidden = ['token_reset_hash'];

    protected $casts = [
        'tentativas'            => 'integer',
        'max_tentativas'        => 'integer',
        'lembrar'               => 'boolean',
        'expira_em'             => 'datetime',
        'resolvido_em'          => 'datetime',
        'bloqueado_em'          => 'datetime',
        'token_reset_expira_em' => 'datetime',
        'token_reset_usado_em'  => 'datetime',
    ];

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function tentativasRestantes(): int
    {
        return max(0, $this->max_tentativas - $this->tentativas);
    }
}
