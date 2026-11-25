<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/*
 * EMPRESA parceira (dona dos Ecopontos). Também recebe token do Sanctum.
 */
class Empresa extends Authenticatable
{
    use HasApiTokens;

    const CREATED_AT = 'criado_em';
    const UPDATED_AT = 'atualizado_em';

    protected $table = 'empresa';

    protected $guarded = ['id', 'status'];

    protected $hidden = ['senha_hash', 'remember_token'];

    protected $casts = [
        'criado_em'     => 'datetime',
        'atualizado_em' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return (string) $this->senha_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'senha_hash';
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }

    public function ecopontos(): HasMany
    {
        return $this->hasMany(Ecoponto::class, 'empresa_id');
    }
}
