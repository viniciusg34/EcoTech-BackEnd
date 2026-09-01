<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once '../db/connection.php';

try {

    $metodo = $_SERVER["REQUEST_METHOD"];

    if ($metodo === "GET") {
        if (isset($_GET["id"])) {
            $stmt = $pdo->prepare("SELECT * FROM usuario WHERE user_id = ?");
            $stmt->execute([$_GET["id"]]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resultado) {
                http_response_code(404);
                echo json_encode(["sucesso" => false, "erro" => "Usuário não encontrado"]);
                exit;
            }

            echo json_encode(["sucesso" => true, "dados" => $resultado]);
        } else {
            $stmt = $pdo->query("SELECT * FROM usuario");
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["sucesso" => true, "dados" => $resultado]);
        }
        exit;
    }

    if ($metodo === "POST") {
        $dados = json_decode(file_get_contents("php://input"), true);

        if (!$dados || empty($dados["email"]) || empty($dados["password"])) {
            http_response_code(400);
            echo json_encode(["sucesso" => false, "erro" => "Email e senha são obrigatórios"]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO usuario (first_name, last_name, email, password, coins, avatar) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $dados["first_name"] ?? "",
            $dados["last_name"] ?? null,
            $dados["email"],
            $dados["password"],
            $dados["coins"] ?? 0,
            $dados["avatar"] ?? null
        ]);

        http_response_code(201);
        echo json_encode(["sucesso" => true, "dados" => ["user_id" => $pdo->lastInsertId()]]);
        exit;
    }

    if ($metodo === "PUT") {
        $dados = json_decode(file_get_contents("php://input"), true);

        if (!$dados || empty($dados["user_id"])) {
            http_response_code(400);
            echo json_encode(["sucesso" => false, "erro" => "user_id é obrigatório"]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE usuario SET coins = ? WHERE user_id = ?");
        $stmt->execute([$dados["coins"], $dados["user_id"]]);

        echo json_encode(["sucesso" => true, "dados" => ["mensagem" => "Usuário atualizado"]]);
        exit;
    }

    http_response_code(405);
    echo json_encode(["sucesso" => false, "erro" => "Método não permitido"]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["sucesso" => false, "erro" => "Erro no banco de dados"]);
    error_log("Erro PDO em usuario.php: " . $e->getMessage());
}