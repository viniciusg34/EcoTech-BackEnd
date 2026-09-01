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
        if (isset($_GET["empresa"])) {
            $stmt = $pdo->prepare("SELECT * FROM ecopontos WHERE empre_id = ?");
            $stmt->execute([$_GET["empresa"]]);
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif (isset($_GET["id"])) {
            $stmt = $pdo->prepare("SELECT * FROM ecopontos WHERE eco_id = ?");
            $stmt->execute([$_GET["id"]]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resultado) {
                http_response_code(404);
                echo json_encode(["sucesso" => false, "erro" => "Ecoponto não encontrado"]);
                exit;
            }
        } else {
            $stmt = $pdo->query("SELECT * FROM ecopontos");
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(["sucesso" => true, "dados" => $resultado]);
        exit;
    }

    if ($metodo === "POST") {
        $dados = json_decode(file_get_contents("php://input"), true);

        if (!$dados || empty($dados["code"]) || empty($dados["empre_id"])) {
            http_response_code(400);
            echo json_encode(["sucesso" => false, "erro" => "code e empre_id são obrigatórios"]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO ecopontos (name, status, collected, code, lat, lng, adress, city, state, cats, logo, empre_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $dados["name"] ?? "",
            $dados["status"] ?? true,
            $dados["collected"] ?? 0,
            $dados["code"],
            $dados["lat"],
            $dados["lng"],
            $dados["adress"] ?? null,
            $dados["city"] ?? null,
            $dados["state"] ?? null,
            $dados["cats"] ?? null,
            $dados["logo"] ?? null,
            $dados["empre_id"]
        ]);

        http_response_code(201);
        echo json_encode(["sucesso" => true, "dados" => ["eco_id" => $pdo->lastInsertId()]]);
        exit;
    }

    if ($metodo === "PUT") {
        $dados = json_decode(file_get_contents("php://input"), true);

        if (!$dados || empty($dados["eco_id"])) {
            http_response_code(400);
            echo json_encode(["sucesso" => false, "erro" => "eco_id é obrigatório"]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE ecopontos SET status = ?, collected = ? WHERE eco_id = ?");
        $stmt->execute([$dados["status"], $dados["collected"], $dados["eco_id"]]);

        echo json_encode(["sucesso" => true, "dados" => ["mensagem" => "Ecoponto atualizado"]]);
        exit;
    }

    http_response_code(405);
    echo json_encode(["sucesso" => false, "erro" => "Método não permitido"]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["sucesso" => false, "erro" => "Erro no banco de dados"]);
    error_log("Erro PDO em ecopontos.php: " . $e->getMessage());
}