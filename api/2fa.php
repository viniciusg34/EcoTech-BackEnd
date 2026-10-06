<?php
/*
 * LOGIN - ETAPA 2 (Segundo Fator de Autenticação)
 * ---------------------------------------------------------------
 * GET  api/2fa.php                         -> mostra a pergunta sorteada no login
 * POST api/2fa.php  { "resposta": "..." }  -> confere a resposta
 *
 * Regras do enunciado:
 *  - a pergunta é sorteada (feito no login.php)
 *  - a resposta é comparada com o que está no banco
 *  - depois de 3 erros: "3 tentativas sem sucesso! Favor realizar Login novamente."
 *    e o usuário volta para a tela de login
 */

require_once __DIR__ . '/../includes/base.php';
require_once __DIR__ . '/../includes/validacoes.php';
require_once __DIR__ . '/../includes/perguntas_2fa.php';

$metodo = permitirMetodos(['GET', 'POST']);

// PARTE 1 - Precisa ter passado pelo login.php antes (e dentro do tempo limite)
$pendente = $_SESSION['2fa'] ?? null;
if (!$pendente || time() - $pendente['criado_em'] > TEMPO_LIMITE_2FA) {
    unset($_SESSION['2fa']);
    erro("Faça login novamente", 401, ["voltar_login" => true]);
}

if ($metodo === 'GET') {
    responder([
        "pergunta"             => PERGUNTAS_2FA[$pendente['pergunta']],
        "tentativas_restantes" => MAX_TENTATIVAS_2FA - $pendente['tentativas'],
    ]);
}

try {
    // PARTE 2 - Ler a resposta
    $resposta = trim(lerJson()['resposta'] ?? '');
    if ($resposta === '') {
        erro("Preencha a resposta");
    }

    $stmt = $pdo->prepare(
        "SELECT user_id, nome, login, perfil, cpf, nome_materno, data_nascimento, cep
         FROM usuario WHERE user_id = ?"
    );
    $stmt->execute([$pendente['user_id']]);
    $usuario = $stmt->fetch();

    if (!$usuario) { // usuário foi excluído no meio do caminho
        unset($_SESSION['2fa']);
        erro("Faça login novamente", 401, ["voltar_login" => true]);
    }

    // PARTE 3 - Comparar com o banco, conforme a pergunta sorteada
    // (ignoramos maiúsculas, acentos e formatação: "20000-000" = "20000000")
    switch ($pendente['pergunta']) {
        case 'mae':
            $acertou = normalizarTexto($resposta) === normalizarTexto($usuario['nome_materno']);
            break;
        case 'nascimento':
            $acertou = normalizarData($resposta) === substr($usuario['data_nascimento'], 0, 10);
            break;
        case 'cep':
            $acertou = somenteNumeros($resposta) === somenteNumeros($usuario['cep']);
            break;
        default:
            $acertou = false;
    }

    // PARTE 4 - Errou: conta a tentativa. Na 3ª, cancela o login.
    if (!$acertou) {
        $_SESSION['2fa']['tentativas']++;
        $restantes = MAX_TENTATIVAS_2FA - $_SESSION['2fa']['tentativas'];

        if ($restantes <= 0) {
            unset($_SESSION['2fa']);
            erro(MENSAGEM_3_TENTATIVAS, 401, ["voltar_login" => true]);
        }
        erro("Resposta incorreta", 401, ["tentativas_restantes" => $restantes]);
    }

    // PARTE 5 - Acertou: agora sim o usuário está logado
    unset($_SESSION['2fa']);
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id'     => (int) $usuario['user_id'],
        'nome'   => $usuario['nome'],
        'login'  => $usuario['login'],   // aparece no canto superior direito das telas
        'perfil' => $usuario['perfil'],  // "master" ou "comum"
    ];

    // PARTE 6 - Registrar no LOG (o enunciado pede o log das entradas do usuário comum)
    if ($usuario['perfil'] === 'comum') {
        $stmt = $pdo->prepare(
            "INSERT INTO log_autenticacao (user_id, nome, cpf, pergunta_2fa, data_hora)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $usuario['user_id'],
            $usuario['nome'],
            $usuario['cpf'],
            PERGUNTAS_2FA[$pendente['pergunta']],
            date('Y-m-d H:i:s'),
        ]);
    }

    responder(["etapa" => "concluido", "usuario" => $_SESSION['usuario']]);

} catch (PDOException $e) {
    error_log("Erro PDO no 2fa: " . $e->getMessage());
    erro("Erro no banco de dados", 500);
}
