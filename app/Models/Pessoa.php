<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/*
 * PESSOA (perfil comum ou master). Pode receber token do Sanctum (HasApiTokens).
 */
class Pessoa extends Authenticatable
{
    use HasApiTokens;

    const CREATED_AT = 'criado_em';
    const UPDATED_AT = 'atualizado_em';

    protected $table = 'pessoa';

    protected $guarded = ['id', 'saldo_coins', 'perfil'];

    // Nunca sai em JSON: o front não recebe a senha nem os dados do 2FA
    protected $hidden = ['senha_hash', 'nome_mae', 'nascimento', 'cep', 'cpf', 'remember_token'];

    protected $casts = [
        'nascimento'        => 'date',
        'saldo_coins'       => 'integer',
        'ativo'             => 'boolean',
        'email_confirmado'  => 'boolean',
        'termos_aceitos_em' => 'datetime',
        'anonimizado_em'    => 'datetime',
        'preferencias'      => 'array',
        'criado_em'         => 'datetime',
        'atualizado_em'     => 'datetime',
    ];

    // A coluna da senha se chama senha_hash (o Laravel esperaria "password")
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
        return null; // a API não usa "remember me" por cookie
    }

    public function ehMaster(): bool
    {
        return $this->perfil === 'master';
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(MovimentoCoins::class, 'pessoa_id');
    }

    public function coletas(): HasMany
    {
        return $this->hasMany(Coleta::class, 'pessoa_id');
    }

    public function resgates(): HasMany
    {
        return $this->hasMany(Resgate::class, 'pessoa_id');
    }

    public function sessoes(): HasMany
    {
        return $this->hasMany(SessaoEcoponto::class, 'pessoa_id');
    }
}
