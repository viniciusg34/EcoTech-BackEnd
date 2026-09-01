<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once '../db/connection.php';

try {

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        echo json_encode(["erro" => "Método não permitido"]);
        exit;
    }

    $dados = json_decode(file_get_contents("php://input"), true);

    if (!$dados) {
        http_response_code(400);
        echo json_encode(["erro" => "JSON inválido"]);
        exit;
    }

    $email = trim($dados["email"] ?? "");
    $senha = $dados["password"] ?? "";

    if (empty($email) || empty($senha)) {
        http_response_code(400);
        echo json_encode(["erro" => "Email e senha são obrigatórios"]);
        exit;
    }

    function autenticarUsuario($pdo, $email, $senha, $tabela, $tipo = null) {
        $stmt = $pdo->prepare("SELECT * FROM $tabela WHERE email = ?");
        $stmt->execute([$email]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($resultado && $resultado["password"] == $senha) {
            $tipoRetorno = $tipo ?? ($resultado["tipo_usuario"] ?? "empresa");
            return [
                "tipo" => $tipoRetorno,
                "dados" => $resultado
            ];
        }
        return null;
    }

    $autenticacao = autenticarUsuario($pdo, $email, $senha, "usuario");

    if (!$autenticacao) {
        $autenticacao = autenticarUsuario($pdo, $email, $senha, "empresa", "empresa");
    }

    if ($autenticacao) {
        http_response_code(200);
        echo json_encode($autenticacao);
        error_log("Login bem-sucedido para: " . $email);
    } else {
        http_response_code(401);
        echo json_encode(["erro" => "Email ou senha incorretos"]);
        error_log("Tentativa de login falhou para: " . $email);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["erro" => "Erro no banco de dados"]);
    error_log("Erro PDO no login: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["erro" => "Erro no servidor"]);
    error_log("Erro geral no login: " . $e->getMessage());
}