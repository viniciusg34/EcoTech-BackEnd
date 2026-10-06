<?php
/*
 * FERRAMENTA: VER O BANCO DO AIVEN
 * ---------------------------------------------------------------
 * Testa a conexão e lista todas as tabelas, colunas e quantas linhas cada uma tem.
 * NÃO mostra senha nem dados dos usuários, só a ESTRUTURA.
 *
 * Como usar (com o XAMPP ligado e o db/config.php preenchido):
 *   abra no navegador:  http://localhost/EcoTech-BackEnd/ferramentas/ver_banco.php
 *   (troque "EcoTech-BackEnd" pelo nome da pasta dentro do htdocs)
 *
 * Por segurança, só funciona no seu próprio computador (localhost).
 */

$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (PHP_SAPI !== 'cli' && !in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Esta ferramenta só pode ser usada no localhost.');
}

header('Content-Type: text/plain; charset=utf-8');

require __DIR__ . '/../db/connection.php'; // se falhar, ele mesmo mostra o erro

echo "CONECTOU COM SUCESSO!\n";
echo "Servidor: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
echo "Banco:    " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "\n";
echo str_repeat('=', 70) . "\n";

$tabelas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

if (!$tabelas) {
    exit("O banco está vazio (nenhuma tabela criada ainda).\n");
}

foreach ($tabelas as $tabela) {
    $total = $pdo->query("SELECT COUNT(*) FROM `$tabela`")->fetchColumn();
    echo "\nTABELA: $tabela  ($total linhas)\n";
    echo str_repeat('-', 70) . "\n";
    printf("%-22s %-28s %-5s %-5s %s\n", 'COLUNA', 'TIPO', 'NULO', 'CHAVE', 'PADRÃO');

    foreach ($pdo->query("SHOW COLUMNS FROM `$tabela`") as $c) {
        printf("%-22s %-28s %-5s %-5s %s\n",
            $c['Field'], $c['Type'], $c['Null'], $c['Key'], $c['Default'] ?? '');
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "Copie tudo acima e mande para o Claude.\n";
