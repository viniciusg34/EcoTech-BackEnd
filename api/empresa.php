<?php

// Base: CORS, sessão, funções de resposta e conexão ($pdo)
require_once __DIR__ . '/../includes/base.php';

try {

    $metodo = $_SERVER["REQUEST_METHOD"];

    if ($metodo === "GET") {
        if (isset($_GET["id"])) {
            $stmt = $pdo->prepare("SELECT empre_id, name, email, colected, cnpj, logo, phone, contact, adress FROM empresa WHERE empre_id = ?");
            $stmt->execute([$_GET["id"]]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resultado) {
                http_response_code(404);
                echo json_encode(["sucesso" => false, "erro" => "Empresa não encontrada"]);
                exit;
            }

            echo json_encode(["sucesso" => true, "dados" => $resultado]);
        } else {
            $stmt = $pdo->query("SELECT empre_id, name, email, colected, cnpj, logo, phone, contact, adress FROM empresa");
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["sucesso" => true, "dados" => $resultado]);
        }
        exit;
    }

    if ($metodo === "POST") {
        $dados = json_decode(file_get_contents("php://input"), true);

        if (!$dados || empty($dados["email"]) || empty($dados["cnpj"])) {
            http_response_code(400);
            echo json_encode(["sucesso" => false, "erro" => "Email e cnpj são obrigatórios"]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO empresa (name, email, password, colected, cnpj, logo, phone, contact, adress) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $dados["name"] ?? "",
            mb_strtolower(trim($dados["email"]), "UTF-8"),
            // senha criptografada (nunca em texto puro)
            !empty($dados["password"]) ? password_hash($dados["password"], PASSWORD_DEFAULT) : null,
            $dados["colected"] ?? 0,
            $dados["cnpj"],
            $dados["logo"] ?? null,
            $dados["phone"] ?? null,
            $dados["contact"] ?? null,
            $dados["adress"] ?? null
        ]);

        http_response_code(201);
        echo json_encode(["sucesso" => true, "dados" => ["empre_id" => $pdo->lastInsertId()]]);
        exit;
    }

    if ($metodo === "PUT") {
        $dados = json_decode(file_get_contents("php://input"), true);

        if (!$dados || empty($dados["empre_id"])) {
            http_response_code(400);
            echo json_encode(["sucesso" => false, "erro" => "empre_id é obrigatório"]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE empresa SET colected = ? WHERE empre_id = ?");
        $stmt->execute([$dados["colected"], $dados["empre_id"]]);

        echo json_encode(["sucesso" => true, "dados" => ["mensagem" => "Empresa atualizada"]]);
        exit;
    }

    http_response_code(405);
    echo json_encode(["sucesso" => false, "erro" => "Método não permitido"]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["sucesso" => false, "erro" => "Erro no banco de dados"]);
    error_log("Erro PDO em empresa.php: " . $e->getMessage());
}
