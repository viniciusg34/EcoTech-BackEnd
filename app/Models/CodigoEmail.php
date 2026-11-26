<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Código de 6 dígitos enviado por e-mail (ver migration create_codigo_email_table)
class CodigoEmail extends Modelo
{
    protected $table = 'codigo_email';

    // Só em memória (não é coluna): o e-mail saiu? Preenchido pelo CodigoEmailService
    public bool $emailEnviado = true;

    protected $hidden = ['codigo_hash', 'token_reset_hash'];

    protected $casts = [
        'tentativas'            => 'integer',
        'max_tentativas'        => 'integer',
        'reenvios'              => 'integer',
        'lembrar'               => 'boolean',
        'enviado_em'            => 'datetime',
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

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    // Pessoa ou empresa dona do código (null no desafio "falso" da recuperação)
    public function conta(): Pessoa|Empresa|null
    {
        return $this->pessoa ?? $this->empresa;
    }

    public function tentativasRestantes(): int
    {
        return max(0, $this->max_tentativas - $this->tentativas);
    }
}
