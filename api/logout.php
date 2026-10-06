<?php
/*
 * LOGOUT
 * ---------------------------------------------------------------
 * POST api/logout.php  -> encerra a sessão
 */

require_once __DIR__ . '/../includes/base.php';

permitirMetodos(['POST', 'GET']);

// PARTE 1 - Apaga os dados da sessão
$_SESSION = [];

// PARTE 2 - Manda o navegador apagar o cookie da sessão
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $p['path'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'],
    ]);
}

// PARTE 3 - Destrói a sessão no servidor
session_destroy();

responder(["mensagem" => "Logout realizado"]);
