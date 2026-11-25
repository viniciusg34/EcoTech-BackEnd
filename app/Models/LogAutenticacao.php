<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogAutenticacao extends Modelo
{
    protected $table = 'log_autenticacao';

    protected $casts = [
        'data_hora'   => 'datetime',
        'sucesso'     => 'boolean',
        'tentativa_n' => 'integer',
    ];

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    // O IP fica em binário (16 bytes); aqui vira texto de novo ("192.168.0.1")
    public function ipTexto(): ?string
    {
        if ($this->ip === null || $this->ip === '') {
            return null;
        }
        $texto = @inet_ntop($this->ip);
        return $texto === false ? null : $texto;
    }
}
