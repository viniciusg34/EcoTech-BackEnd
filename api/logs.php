<?php
/*
 * TELA DE LOG (só MASTER)
 * ---------------------------------------------------------------
 * GET api/logs.php                          -> todos os logs
 * GET api/logs.php?filtro=nome&valor=ana    -> por pedaço do nome
 * GET api/logs.php?filtro=cpf&valor=529982  -> por CPF (completo ou parte, com ou sem pontos)
 * GET api/logs.php?filtro=todos&valor=ana   -> procura no nome OU no CPF
 *
 * Sempre em ordem do mais recente para o mais antigo.
 */

require_once __DIR__ . '/../includes/base.php';
require_once __DIR__ . '/../includes/validacoes.php';

permitirMetodos(['GET']);
exigirMaster();

try {
    $filtro = $_GET['filtro'] ?? 'todos';
    $valor  = limparEspacos($_GET['valor'] ?? '');

    // Protege os caracteres especiais do LIKE (% e _)
    $valorSeguro = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $valor);
    $cpfSeguro   = somenteNumeros($valor);

    $where  = "";
    $params = [];

    if ($valor !== '') {
        if ($filtro === 'nome') {
            $where  = "WHERE nome LIKE ? ESCAPE '!'";
            $params = ["%$valorSeguro%"];
        } elseif ($filtro === 'cpf') {
            if ($cpfSeguro === '') {
                erro("Digite apenas números no CPF");
            }
            $where  = "WHERE cpf LIKE ?";
            $params = ["%$cpfSeguro%"];
        } else { // "todos": procura nos dois
            $where  = "WHERE nome LIKE ? ESCAPE '!'" . ($cpfSeguro !== '' ? " OR cpf LIKE ?" : "");
            $params = ["%$valorSeguro%"];
            if ($cpfSeguro !== '') $params[] = "%$cpfSeguro%";
        }
    }

    $stmt = $pdo->prepare(
        "SELECT log_id, user_id, nome, cpf, pergunta_2fa, data_hora
         FROM log_autenticacao $where
         ORDER BY data_hora DESC, log_id DESC"
    );
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Data já formatada para mostrar na tela (25/12/2026 14:30:05)
    foreach ($logs as &$log) {
        $log['data_hora_formatada'] = date('d/m/Y H:i:s', strtotime($log['data_hora']));
    }
    unset($log);

    responder($logs);

} catch (PDOException $e) {
    error_log("Erro PDO em logs.php: " . $e->getMessage());
    erro("Erro no banco de dados", 500);
}
