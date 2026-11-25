<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken;

/*
 * Token do Sanctum guardado na tabela token_acesso (em vez de personal_access_tokens).
 * Registrado no AppServiceProvider com Sanctum::usePersonalAccessTokenModel().
 */
class TokenAcesso extends PersonalAccessToken
{
    const CREATED_AT = 'criado_em';
    const UPDATED_AT = 'atualizado_em';

    protected $table = 'token_acesso';
}
