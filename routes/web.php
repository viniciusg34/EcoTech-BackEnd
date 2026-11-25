<?php

use Illuminate\Support\Facades\Route;

// A raiz só informa que a API está no ar e onde ficam as rotas
Route::get('/', fn () => response()->json([
    'app'         => 'EcoTech API',
    'versao'      => 'v1',
    'base_url'    => url('/api/v1'),
    'saude'       => url('/up'),
    'documentacao' => 'docs/openapi.yaml (no repositório)',
]));
