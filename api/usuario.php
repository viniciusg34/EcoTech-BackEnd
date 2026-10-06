<?php
/*
 * USUÁRIOS
 * ---------------------------------------------------------------
 * POST   api/usuario.php              -> Cadastro de usuário comum (qualquer pessoa)
 * GET    api/usuario.php?busca=ana    -> Consulta de usuários comuns por pedaço do nome (só MASTER)
 * GET    api/usuario.php?id=5         -> Dados de um usuário (só MASTER)
 * DELETE api/usuario.php?id=5         -> Exclui usuário comum (só MASTER)
 * PUT    api/usuario.php              -> Atualiza moedas (coins) do EcoTech (logado)
 */

require_once __DIR__ . '/../includes/base.php';
require_once __DIR__ . '/../includes/validacoes.php';

// Colunas que podem sair na resposta. A SENHA NUNCA entra nesta lista.
const COLUNAS_PUBLICAS = "user_id, nome, data_nascimento, sexo, nome_materno, cpf, email,
                          telefone_celular, telefone_fixo, cep, endereco, login, perfil, coins, avatar, criado_em";

$metodo = permitirMetodos(['GET', 'POST', 'PUT', 'DELETE']);

try {

    /* ===========================================================
     * POST - CADASTRO
     * Só cria usuário COMUM. O master é criado pelo script SQL.
     * =========================================================== */
    if ($metodo === 'POST') {
        $d = lerJson();

        // PARTE 1 - Pegar e limpar os campos
        $nome          = limparEspacos($d['nome'] ?? '');
        $nascimento    = trim($d['data_nascimento'] ?? '');
        $sexo          = trim($d['sexo'] ?? '');
        $nomeMaterno   = limparEspacos($d['nome_materno'] ?? '');
        $cpf           = somenteNumeros($d['cpf'] ?? '');
        $email         = mb_strtolower(trim($d['email'] ?? ''), 'UTF-8');
        $celular       = trim($d['telefone_celular'] ?? '');
        $fixo          = trim($d['telefone_fixo'] ?? '');
        $cep           = trim($d['cep'] ?? '');
        $endereco      = limparEspacos($d['endereco'] ?? '');
        $login         = trim($d['login'] ?? '');
        $senha         = $d['senha'] ?? '';
        $confirmar     = $d['confirmar_senha'] ?? '';

        // PARTE 2 - Validar. Juntamos TODOS os erros para o front mostrar de uma vez.
        $erros = [];

        // As chaves dos erros são os mesmos nomes dos campos do JSON.
        $obrigatorios = [
            'nome' => $nome, 'data_nascimento' => $nascimento, 'sexo' => $sexo,
            'nome_materno' => $nomeMaterno, 'cpf' => $cpf, 'email' => $email,
            'telefone_celular' => $celular, 'telefone_fixo' => $fixo, 'cep' => $cep,
            'endereco' => $endereco, 'login' => $login, 'senha' => $senha,
        ];
        foreach ($obrigatorios as $campo => $valor) {
            if ($valor === '') {
                $erros[$campo] = "Campo obrigatório";
            }
        }

        if (!isset($erros['nome']) && !validarNome($nome))
            $erros['nome'] = "O nome deve ter de 15 a 80 caracteres, apenas letras";
        if (!isset($erros['data_nascimento']) && !validarDataNascimento($nascimento))
            $erros['data_nascimento'] = "Data de nascimento inválida";
        if (!isset($erros['sexo']) && normalizarSexo($sexo) === null)
            $erros['sexo'] = "Sexo inválido (use M, F ou O)";
        if (!isset($erros['nome_materno']) && !preg_match('/^[\p{L} ]+$/u', $nomeMaterno))
            $erros['nome_materno'] = "Nome materno deve ter apenas letras";
        if (!isset($erros['cpf']) && !validarCPF($cpf))
            $erros['cpf'] = "CPF inválido";
        if (!isset($erros['email']) && !validarEmail($email))
            $erros['email'] = "E-mail inválido";
        if (!isset($erros['telefone_celular']) && !validarTelefone($celular))
            $erros['telefone_celular'] = "Use o formato (+55)XX-XXXXXXXXX";
        if (!isset($erros['telefone_fixo']) && !validarTelefone($fixo))
            $erros['telefone_fixo'] = "Use o formato (+55)XX-XXXXXXXX";
        if (!isset($erros['cep']) && !validarCEP($cep))
            $erros['cep'] = "CEP inválido";
        if (!isset($erros['login']) && !validarLogin($login))
            $erros['login'] = "O login deve ter exatamente 6 letras";
        if (!isset($erros['senha']) && !validarSenha($senha))
            $erros['senha'] = "A senha deve ter exatamente 8 letras";
        if (!isset($erros['senha']) && $senha !== $confirmar)
            $erros['confirmar_senha'] = "As senhas não são iguais";

        if ($erros) {
            erro("Verifique os campos do cadastro", 422, ["campos" => $erros]);
        }

        // PARTE 3 - Ver se CPF, e-mail ou login já estão em uso
        $stmt = $pdo->prepare("SELECT cpf, email, login FROM usuario WHERE cpf = ? OR email = ? OR login = ?");
        $stmt->execute([$cpf, $email, $login]);
        foreach ($stmt->fetchAll() as $existente) {
            if ($existente['cpf'] === $cpf)     $erros['cpf']   = "CPF já cadastrado";
            if ($existente['email'] === $email) $erros['email'] = "E-mail já cadastrado";
            if (strcasecmp($existente['login'], $login) === 0) $erros['login'] = "Login já está em uso";
        }
        if ($erros) {
            erro("Dados já cadastrados", 409, ["campos" => $erros]);
        }

        // PARTE 4 - Salvar. A senha vai CRIPTOGRAFADA (password_hash), nunca em texto puro.
        $stmt = $pdo->prepare(
            "INSERT INTO usuario
                (nome, data_nascimento, sexo, nome_materno, cpf, email, telefone_celular,
                 telefone_fixo, cep, endereco, login, senha, perfil)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'comum')"
        );
        $stmt->execute([
            $nome,
            normalizarData($nascimento),
            normalizarSexo($sexo),
            $nomeMaterno,
            $cpf,
            $email,
            $celular,
            $fixo,
            somenteNumeros($cep),
            $endereco,
            $login,
            password_hash($senha, PASSWORD_DEFAULT),
        ]);

        // O front deve redirecionar para a tela de login depois disso.
        responder(["user_id" => (int) $pdo->lastInsertId(), "mensagem" => "Cadastro realizado! Faça login."], 201);
    }

    /* ===========================================================
     * GET - CONSULTA (só master)
     * =========================================================== */
    if ($metodo === 'GET') {
        exigirMaster();

        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT " . COLUNAS_PUBLICAS . " FROM usuario WHERE user_id = ?");
            $stmt->execute([$_GET['id']]);
            $usuario = $stmt->fetch();
            if (!$usuario) {
                erro("Usuário não encontrado", 404);
            }
            responder($usuario);
        }

        // Busca por substring do nome. Sem "busca", lista todos os comuns.
        // O ESCAPE serve para o usuário poder pesquisar "%" ou "_" literalmente.
        $busca = limparEspacos($_GET['busca'] ?? '');
        $buscaSegura = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $busca);

        $stmt = $pdo->prepare(
            "SELECT " . COLUNAS_PUBLICAS . " FROM usuario
             WHERE perfil = 'comum' AND nome LIKE ? ESCAPE '!'
             ORDER BY nome"
        );
        $stmt->execute(['%' . $buscaSegura . '%']);
        responder($stmt->fetchAll());
    }

    /* ===========================================================
     * DELETE - EXCLUSÃO (só master, e só de usuário comum)
     * A confirmação ("Tem certeza?") é feita no front, num modal.
     * =========================================================== */
    if ($metodo === 'DELETE') {
        exigirMaster();

        $id = $_GET['id'] ?? (lerJson()['user_id'] ?? null);
        if (!$id) {
            erro("Informe o id do usuário");
        }

        $stmt = $pdo->prepare("DELETE FROM usuario WHERE user_id = ? AND perfil = 'comum'");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            erro("Usuário comum não encontrado", 404);
        }
        responder(["mensagem" => "Usuário excluído"]);
    }

    /* ===========================================================
     * PUT - ATUALIZAR MOEDAS (recurso do EcoTech)
     * O usuário comum só pode mexer nas próprias moedas.
     * =========================================================== */
    if ($metodo === 'PUT') {
        $logado = exigirLogin();
        $d = lerJson();

        $id = $d['user_id'] ?? $logado['id'];
        if ($logado['perfil'] !== 'master' && (int) $id !== (int) $logado['id']) {
            erro("Você só pode alterar a sua própria conta", 403);
        }
        if (!isset($d['coins']) || !is_numeric($d['coins']) || $d['coins'] < 0) {
            erro("Informe um valor de coins válido");
        }

        $stmt = $pdo->prepare("UPDATE usuario SET coins = ? WHERE user_id = ?");
        $stmt->execute([$d['coins'], $id]);
        responder(["mensagem" => "Usuário atualizado"]);
    }

} catch (PDOException $e) {
    error_log("Erro PDO em usuario.php: " . $e->getMessage());
    erro("Erro no banco de dados", 500);
}
