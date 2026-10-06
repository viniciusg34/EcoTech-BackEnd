<?php
/*
 * QUEM ESTÁ LOGADO?
 * ---------------------------------------------------------------
 * GET api/sessao.php
 *
 * O front chama isto ao abrir cada tela para:
 *  - mostrar o login no canto superior direito
 *  - montar o menu de acordo com o perfil (master vê Consulta e LOG; comum vê Alterar Senha)
 *  - mandar para a tela de login se não estiver logado
 */

require_once __DIR__ . '/../includes/base.php';

permitirMetodos(['GET']);

$usuario = usuarioLogado();
responder([
    "logado"  => $usuario !== null,
    "usuario" => $usuario,
]);
