<?php
/*
 * LOGIN - ETAPA 1 (login + senha)
 * ---------------------------------------------------------------
 * POST api/login.php   { "login": "joaoss", "senha": "abcdefgh" }
 *
 * Se login e senha estiverem certos, o usuário AINDA NÃO está logado:
 * a API sorteia uma pergunta do 2FA e o front deve abrir a tela de 2FA
 * (que chama api/2fa.php). Só depois do 2FA a sessão fica completa.
 *
 * Empresas parceiras do EcoTech entram pelo e-mail e não passam pelo 2FA
 * (o 2FA do enunciado é para os usuários master e comum).
 */

require_once __DIR__ . '/../includes/base.php';
require_once __DIR__ . '/../includes/perguntas_2fa.php';

permitirMetodos(['POST']);

/*
 * Confere a senha digitada com a salva no banco.
 * Se o registro for antigo e a senha estiver em texto puro, aceita UMA vez
 * e já troca por um hash no banco (assim os dados antigos não quebram).
 */
function conferirSenha(PDO $pdo, string $digitada, string $salva, string $tabela, string $colunaSenha, string $colunaId, $id): bool
{
    if (password_get_info($salva)['algo'] !== null) {
        return password_verify($digitada, $salva);           // caso normal: senha com hash
    }
    if (hash_equals($salva, $digitada)) {                    // caso antigo: texto puro
        $stmt = $pdo->prepare("UPDATE $tabela SET $colunaSenha = ? WHERE $colunaId = ?");
        $stmt->execute([password_hash($digitada, PASSWORD_DEFAULT), $id]);
        return true;
    }
    return false;
}

try {
    // PARTE 1 - Ler os campos (o front pode mandar "login" ou "email")
    $d = lerJson();
    $login = trim($d['login'] ?? $d['email'] ?? '');
    $senha = $d['senha'] ?? $d['password'] ?? '';

    if ($login === '' || $senha === '') {
        erro("Preencha login e senha");
    }

    // Começa do zero: se havia alguém logado ou um 2FA pela metade, apaga.
    unset($_SESSION['usuario'], $_SESSION['2fa']);

    // PARTE 2 - Procurar o usuário (master ou comum) pelo login ou e-mail
    $stmt = $pdo->prepare("SELECT user_id, senha FROM usuario WHERE login = ? OR email = ?");
    $stmt->execute([$login, mb_strtolower($login, 'UTF-8')]);
    $usuario = $stmt->fetch();

    if ($usuario && conferirSenha($pdo, $senha, $usuario['senha'], 'usuario', 'senha', 'user_id', $usuario['user_id'])) {
        // PARTE 3 - Senha certa: sortear a pergunta do 2FA e guardar na sessão
        session_regenerate_id(true); // novo número de sessão (evita "sequestro de sessão")

        $chave = array_rand(PERGUNTAS_2FA);
        $_SESSION['2fa'] = [
            'user_id'    => (int) $usuario['user_id'],
            'pergunta'   => $chave,
            'tentativas' => 0,
            'criado_em'  => time(),
        ];

        error_log("Login (etapa 1) ok para: $login");
        responder([
            "etapa"    => "2fa",
            "pergunta" => PERGUNTAS_2FA[$chave],
        ]);
    }

    // PARTE 4 - Não é usuário: tentar como empresa parceira (pelo e-mail)
    $stmt = $pdo->prepare("SELECT empre_id, name, email, password FROM empresa WHERE email = ?");
    $stmt->execute([mb_strtolower($login, 'UTF-8')]);
    $empresa = $stmt->fetch();

    if ($empresa && !empty($empresa['password'])
        && conferirSenha($pdo, $senha, $empresa['password'], 'empresa', 'password', 'empre_id', $empresa['empre_id'])) {
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id'     => (int) $empresa['empre_id'],
            'nome'   => $empresa['name'],
            'login'  => $empresa['email'],
            'perfil' => 'empresa',
        ];
        responder(["etapa" => "concluido", "usuario" => $_SESSION['usuario']]);
    }

    // PARTE 5 - Nada bateu. Mensagem genérica de propósito
    // (não dizemos se o erro foi no login ou na senha, para não ajudar quem tenta adivinhar).
    error_log("Tentativa de login falhou para: $login");
    erro("Login ou senha incorretos", 401);

} catch (PDOException $e) {
    error_log("Erro PDO no login: " . $e->getMessage());
    erro("Erro no banco de dados", 500);
}
