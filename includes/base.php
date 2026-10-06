<?php
/*
 * BASE DE TODOS OS ENDPOINTS
 * ---------------------------------------------------------------
 * Todo arquivo da pasta api/ começa com:
 *     require_once __DIR__ . '/../includes/base.php';
 *
 * Este arquivo faz 4 coisas, sempre na mesma ordem:
 *   PARTE 1 - CORS: deixa o front-end (Vercel/localhost) chamar a API
 *   PARTE 2 - SESSÃO: inicia o session_start() com cookie seguro
 *   PARTE 3 - FUNÇÕES DE RESPOSTA: responder(), erro(), lerJson()
 *   PARTE 4 - CONTROLE DE PERFIL: exigirLogin(), exigirMaster(), exigirComum()
 * E no fim conecta no banco ($pdo).
 */

// Horário de Brasília para as datas do LOG
date_default_timezone_set('America/Sao_Paulo');

/* ===============================================================
 * PARTE 1 - CORS
 * O navegador só manda o cookie da sessão para outro domínio se:
 *   - a API responder com o endereço EXATO do site (não pode ser "*")
 *   - e com Access-Control-Allow-Credentials: true
 * No front-end, todo fetch precisa de:  credentials: "include"
 * =============================================================== */
$configBase = file_exists(__DIR__ . '/../db/config.php')
    ? require __DIR__ . '/../db/config.php'
    : [];

$origensPermitidas = $configBase['origens_permitidas'] ?? [
    'https://eco-tech-blush.vercel.app',
    'http://localhost',
    'http://127.0.0.1:5500',
];

$origem = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origem !== '' && in_array($origem, $origensPermitidas, true)) {
    header("Access-Control-Allow-Origin: $origem");
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
}
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// O navegador manda um "OPTIONS" antes de alguns pedidos para perguntar se pode.
// Respondemos "pode" e paramos aqui.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* ===============================================================
 * PARTE 2 - SESSÃO
 * A sessão guarda no SERVIDOR quem está logado. O navegador só guarda
 * um cookie com o número da sessão (por isso o front não consegue "fingir"
 * que é master editando o sessionStorage).
 * =============================================================== */
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,          // cookie some quando fecha o navegador
        'path'     => '/',
        'httponly' => true,       // JavaScript não consegue ler o cookie (protege contra roubo)
        'secure'   => $https,     // só manda o cookie por HTTPS quando o site é HTTPS
        'samesite' => $https ? 'None' : 'Lax', // "None" permite front e API em domínios diferentes
    ]);
    session_name('ECOTECHSESSID');
    session_start();
}

/* ===============================================================
 * PARTE 3 - FUNÇÕES DE RESPOSTA
 * Toda resposta da API tem o mesmo formato, para o front tratar igual:
 *   sucesso: { "sucesso": true,  "dados": ... }
 *   erro:    { "sucesso": false, "erro": "mensagem" }
 * =============================================================== */
header("Content-Type: application/json; charset=utf-8");

function responder($dados = null, int $codigo = 200): never
{
    http_response_code($codigo);
    echo json_encode(["sucesso" => true, "dados" => $dados], JSON_UNESCAPED_UNICODE);
    exit;
}

function erro(string $mensagem, int $codigo = 400, array $extra = []): never
{
    http_response_code($codigo);
    echo json_encode(["sucesso" => false, "erro" => $mensagem] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

// Lê o corpo JSON enviado pelo fetch do front-end. Se vier vazio, devolve [].
function lerJson(): array
{
    $dados = json_decode(file_get_contents("php://input"), true);
    return is_array($dados) ? $dados : [];
}

// Garante que só os métodos listados são aceitos (ex.: ['GET', 'POST']).
function permitirMetodos(array $metodos): string
{
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($metodo, $metodos, true)) {
        erro("Método não permitido", 405);
    }
    return $metodo;
}

/* ===============================================================
 * PARTE 4 - CONTROLE DE PERFIL (master x comum)
 * Depois do login + 2FA, guardamos em $_SESSION['usuario']:
 *   id, nome, login, perfil ("master" ou "comum")
 * =============================================================== */
function usuarioLogado(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function exigirLogin(): array
{
    $usuario = usuarioLogado();
    if (!$usuario) {
        erro("Você precisa fazer login", 401);
    }
    return $usuario;
}

function exigirMaster(): array
{
    $usuario = exigirLogin();
    if ($usuario['perfil'] !== 'master') {
        erro("Acesso permitido apenas para o usuário master", 403);
    }
    return $usuario;
}

function exigirComum(): array
{
    $usuario = exigirLogin();
    if ($usuario['perfil'] !== 'comum') {
        erro("Acesso permitido apenas para o usuário comum", 403);
    }
    return $usuario;
}

// Por último, conecta no banco. Depois desta linha, $pdo está disponível.
require_once __DIR__ . '/../db/connection.php';
