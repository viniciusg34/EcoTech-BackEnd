<?php
/*
 * ALTERAÇÃO DE SENHA (só usuário COMUM, e só a própria senha)
 * ---------------------------------------------------------------
 * POST api/alterar_senha.php
 *   { "senha_atual": "abcdefgh", "nova_senha": "ijklmnop", "confirmar_senha": "ijklmnop" }
 */

require_once __DIR__ . '/../includes/base.php';
require_once __DIR__ . '/../includes/validacoes.php';

permitirMetodos(['POST', 'PUT']);

// O id vem da SESSÃO, nunca do front. Assim ninguém troca a senha de outra pessoa.
$logado = exigirComum();

try {
    // PARTE 1 - Ler e validar
    $d = lerJson();
    $atual     = $d['senha_atual'] ?? '';
    $nova      = $d['nova_senha'] ?? '';
    $confirmar = $d['confirmar_senha'] ?? '';

    if ($atual === '' || $nova === '' || $confirmar === '') {
        erro("Preencha todos os campos");
    }
    if (!validarSenha($nova)) {
        erro("A nova senha deve ter exatamente 8 letras", 422, ["campos" => ["nova_senha" => "Exatamente 8 letras"]]);
    }
    if ($nova !== $confirmar) {
        erro("As senhas não são iguais", 422, ["campos" => ["confirmar_senha" => "As senhas não são iguais"]]);
    }

    // PARTE 2 - Conferir a senha atual
    $stmt = $pdo->prepare("SELECT senha FROM usuario WHERE user_id = ?");
    $stmt->execute([$logado['id']]);
    $salva = $stmt->fetchColumn();

    if (!$salva || !password_verify($atual, $salva)) {
        erro("Senha atual incorreta", 401, ["campos" => ["senha_atual" => "Senha atual incorreta"]]);
    }
    if (password_verify($nova, $salva)) {
        erro("A nova senha precisa ser diferente da atual", 422);
    }

    // PARTE 3 - Salvar a nova senha criptografada
    $stmt = $pdo->prepare("UPDATE usuario SET senha = ? WHERE user_id = ?");
    $stmt->execute([password_hash($nova, PASSWORD_DEFAULT), $logado['id']]);

    responder(["mensagem" => "Senha alterada com sucesso"]);

} catch (PDOException $e) {
    error_log("Erro PDO em alterar_senha.php: " . $e->getMessage());
    erro("Erro no banco de dados", 500);
}
