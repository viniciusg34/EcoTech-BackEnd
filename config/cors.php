<?php

/*
 * CORS - quais sites podem chamar a API pelo navegador.
 * ---------------------------------------------------------------
 * CORS_ORIGINS no .env, separados por vírgula (sem barra no fim):
 *   CORS_ORIGINS=https://eco-tech-blush.vercel.app
 * Localhost em qualquer porta (Live Server, php artisan serve) é liberado
 * enquanto CORS_LOCALHOST=true.
 * Não usamos cookie: o login vai no cabeçalho Authorization (Bearer).
 */
return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ORIGINS', 'https://eco-tech-blush.vercel.app'))
    ))),

    'allowed_origins_patterns' => filter_var(env('CORS_LOCALHOST', true), FILTER_VALIDATE_BOOL)
        ? ['#^https?://(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$#']
        : [],

    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'Idempotency-Key', 'X-Requested-With'],

    'exposed_headers' => ['Content-Disposition', 'Retry-After', 'Idempotency-Replayed'],

    'max_age' => 600,

    'supports_credentials' => false,
];
