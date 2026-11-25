<?php

/*
 * AUTENTICAÇÃO
 * ---------------------------------------------------------------
 * A API usa o guard "sanctum": quem está logado é descoberto pelo token
 * (Authorization: Bearer ...). O token pode ser de uma Pessoa ou de uma Empresa.
 */
return [

    'defaults' => [
        'guard'     => 'sanctum',
        'passwords' => 'pessoas',
    ],

    'guards' => [
        // Não é usado pela API (fica porque o Laravel espera um guard "web")
        'web' => [
            'driver'   => 'session',
            'provider' => 'pessoas',
        ],

        // provider null = aceita token de Pessoa e de Empresa
        'sanctum' => [
            'driver'   => 'sanctum',
            'provider' => null,
        ],
    ],

    'providers' => [
        'pessoas' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Pessoa::class,
        ],
        'empresas' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Empresa::class,
        ],
    ],

    // A recuperação de senha é feita pelo desafio_verificacao (não usa esta tabela)
    'passwords' => [
        'pessoas' => [
            'provider' => 'pessoas',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];
