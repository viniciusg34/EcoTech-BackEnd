<?php

/*
 * LARAVEL SANCTUM (tokens de acesso)
 * A API é só por token (Authorization: Bearer ...), sem cookie.
 * A validade de cada token é definida na hora do login (8 h ou 30 dias).
 */
return [

    // Nenhum domínio usa login por cookie (o front manda o token no cabeçalho)
    'stateful' => [],

    // Não tenta achar o usuário na sessão: só pelo token
    'guard' => [],

    // null = cada token tem o próprio prazo (coluna expires_at)
    'expiration' => null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies'      => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token'  => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
