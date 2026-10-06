<?php
/*
 * MODELO DE CONFIGURAÇÃO DO BANCO
 * ---------------------------------------------------------------
 * 1. Copie este arquivo e renomeie a cópia para:  db/config.php
 * 2. Preencha com os dados do painel do Aiven
 *    (Aiven > seu serviço MySQL > aba "Overview" > "Connection information")
 * 3. Baixe o "CA certificate" no mesmo painel e salve como:  db/ca.pem
 *
 * IMPORTANTE: o config.php tem a SENHA do banco.
 * Ele está no .gitignore para NÃO ir para o GitHub. Nunca envie esse arquivo.
 */

return [
    'host'     => 'mysql-xxxxxxx-seuprojeto.aivencloud.com', // "Host" no Aiven
    'port'     => '12345',                                   // "Port" no Aiven
    'dbname'   => 'defaultdb',                               // "Database name"
    'username' => 'avnadmin',                                // "User"
    'password' => 'COLE_A_SENHA_AQUI',                       // "Password"
    'ssl_ca'   => __DIR__ . '/ca.pem',                       // certificado do Aiven
    'ssl_obrigatorio' => true,                               // o Aiven só aceita conexão com SSL

    // Endereços do front-end que podem chamar esta API (CORS).
    // Coloque aqui o link do site na Vercel e o endereço local de testes.
    'origens_permitidas' => [
        'https://eco-tech-blush.vercel.app',
        'http://localhost',
        'http://127.0.0.1:5500', // Live Server do VS Code
    ],
];
