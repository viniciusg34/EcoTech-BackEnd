<?php

/*
 * CONFIGURAÇÕES DO ECOTECH (lidas do .env)
 * Os valores que mudam com o sistema rodando (moedas do check-in etc.)
 * ficam na tabela "configuracao", não aqui.
 */
return [

    /*
     * Regra da senha:
     *   forte     = mínimo 8 caracteres com maiúscula, minúscula e número (PADRÃO: decisão do grupo, igual ao front)
     *   enunciado = exatamente 8 letras (regra do PDF da disciplina; o front teria de mudar junto)
     */
    'senha_regra' => env('SENHA_REGRA', 'forte'),

    // Segredo usado para assinar o QR dos Ecopontos (HMAC-SHA256). Sem ele, usa a APP_KEY.
    'qr_secret' => env('QR_SECRET') ?: env('APP_KEY'),

    // Duração do token de acesso
    'token_horas'        => (int) env('TOKEN_HORAS', 8),
    'token_lembrar_dias' => (int) env('TOKEN_LEMBRAR_DIAS', 30),

    // APIs externas
    'off_base_url' => rtrim(env('OFF_BASE_URL', 'https://world.openfoodfacts.org'), '/'),
    'off_timeout'  => (int) env('OFF_TIMEOUT', 4),
    'viacep_url'   => rtrim(env('VIACEP_URL', 'https://viacep.com.br/ws'), '/'),
    // ID público do OAuth (não é segredo). O GOOGLE_CLIENT_ID do .env, se preenchido, tem prioridade.
    'google_client_id' => env('GOOGLE_CLIENT_ID') ?: '547710554770-v2ofvchbeskjbm9d80pq12m3jncve5v7.apps.googleusercontent.com',

    // Pessoa master criada pelo seeder (a senha vem SÓ do .env)
    'master' => [
        'nome'       => env('MASTER_NOME', 'Administrador Master EcoTech'),
        'email'      => env('MASTER_EMAIL', 'master@ecotech.com'),
        'login'      => env('MASTER_LOGIN', 'MASTER'),
        'senha'      => env('MASTER_PASSWORD'),
        'nome_mae'   => env('MASTER_NOME_MAE', 'Maria da Silva'),
        'nascimento' => env('MASTER_NASCIMENTO', '2000-01-01'),
        'cep'        => env('MASTER_CEP', '20040020'),
        'cpf'        => env('MASTER_CPF', '52998224725'),
    ],
];
