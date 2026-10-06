<?php
/*
 * CONEXÃO COM O BANCO (MySQL no Aiven)
 * ---------------------------------------------------------------
 * Lê os dados de db/config.php (que NÃO vai para o GitHub).
 * Se o config.php não existir, usa o MySQL local do XAMPP (localhost/root).
 * No final, a variável $pdo fica pronta para os outros arquivos usarem.
 */

$config = file_exists(__DIR__ . '/config.php')
    ? require __DIR__ . '/config.php'
    : [];

$host     = $config['host']     ?? 'localhost';
$port     = $config['port']     ?? '3306';
$dbname   = $config['dbname']   ?? 'ecotech';
$username = $config['username'] ?? 'root';
$password = $config['password'] ?? '';
$sslCa    = $config['ssl_ca']   ?? null;

// "dsn" só é usado nos testes automáticos (banco SQLite). No dia a dia fica vazio.
$dsn = $config['dsn'] ?? "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

$opcoes = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // erro vira exceção (try/catch)
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // resultados como array associativo
];

// O Aiven exige conexão segura (SSL).
if (str_starts_with($dsn, 'mysql:')) {
    if ($sslCa && file_exists($sslCa)) {
        // Melhor caso: com o certificado do Aiven (db/ca.pem), conferimos que o servidor é mesmo o Aiven.
        $opcoes[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
        $opcoes[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    } elseif (!empty($config['ssl_obrigatorio'])) {
        // Sem o ca.pem: a conexão continua criptografada, mas sem conferir o certificado.
        // Serve para testar; baixe o ca.pem no painel do Aiven assim que puder.
        $opcoes[PDO::MYSQL_ATTR_SSL_CIPHER] = 'DEFAULT';
        $opcoes[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
}

try {
    $pdo = new PDO($dsn, $username, $password, $opcoes);
} catch (PDOException $e) {
    // Não mostramos o erro real para o usuário (pode ter dados do servidor).
    // Ele vai para o log do PHP, onde só você vê.
    error_log("Falha na conexão com o banco: " . $e->getMessage());
    http_response_code(500);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["sucesso" => false, "erro" => "Não foi possível conectar ao banco de dados"]);
    exit;
}
