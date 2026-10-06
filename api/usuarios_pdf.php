<?php
/*
 * DESAFIO PLUS - BAIXAR LISTA DE USUÁRIOS EM PDF (só MASTER)
 * ---------------------------------------------------------------
 * GET api/usuarios_pdf.php            -> PDF com todos os usuários comuns
 * GET api/usuarios_pdf.php?busca=ana  -> PDF só com quem tem "ana" no nome
 *
 * No front, o botão pode ser só um link:
 *   <a href="URL_DA_API/api/usuarios_pdf.php" target="_blank">Baixar PDF</a>
 * (o navegador manda o cookie da sessão sozinho)
 */

require_once __DIR__ . '/../includes/base.php';
require_once __DIR__ . '/../includes/validacoes.php';
require_once __DIR__ . '/../includes/pdf_simples.php';

permitirMetodos(['GET']);
exigirMaster();

// Corta textos compridos para caberem na coluna
function caber(string $texto, int $max): string
{
    return mb_strlen($texto) > $max ? mb_substr($texto, 0, $max - 1) . '…' : $texto;
}

function formatarCPF(string $cpf): string
{
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
}

try {
    // PARTE 1 - Buscar os usuários (mesma busca da tela de Consulta)
    $busca = limparEspacos($_GET['busca'] ?? '');
    $buscaSegura = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $busca);

    $stmt = $pdo->prepare(
        "SELECT nome, cpf, email, login, telefone_celular
         FROM usuario WHERE perfil = 'comum' AND nome LIKE ? ESCAPE '!'
         ORDER BY nome"
    );
    $stmt->execute(['%' . $buscaSegura . '%']);
    $usuarios = $stmt->fetchAll();

    // PARTE 2 - Desenhar o PDF
    // Colunas: [título, posição x, máximo de caracteres]
    $colunas = [
        ['Nome',    40,  38],
        ['CPF',     290, 14],
        ['E-mail',  385, 40],
        ['Login',   635, 8],
        ['Celular', 690, 20],
    ];
    $alturaLinha = 18;
    $pdf = new PdfSimples();

    $desenharCabecalho = function (float $y) use ($pdf, $colunas) {
        $pdf->faixa(35, $y - 5, PdfSimples::LARGURA - 70, 18);
        foreach ($colunas as [$titulo, $x]) {
            $pdf->texto($x, $y, $titulo, 10, true);
        }
    };

    $pdf->novaPagina();
    $pdf->texto(40, 550, 'EcoTech - Lista de Usuários', 16, true);
    $subtitulo = 'Gerado em ' . date('d/m/Y H:i') . ' - Total: ' . count($usuarios) . ' usuário(s)';
    if ($busca !== '') {
        $subtitulo .= " - Filtro: \"$busca\"";
    }
    $pdf->texto(40, 532, $subtitulo, 9);

    $y = 505;
    $desenharCabecalho($y);
    $y -= $alturaLinha + 4;

    if (!$usuarios) {
        $pdf->texto(40, $y, 'Nenhum usuário encontrado.', 10);
    }

    foreach ($usuarios as $u) {
        if ($y < 50) { // acabou o espaço: nova página com cabeçalho
            $pdf->novaPagina();
            $y = 550;
            $desenharCabecalho($y);
            $y -= $alturaLinha + 4;
        }
        $valores = [$u['nome'], formatarCPF($u['cpf']), $u['email'], $u['login'], $u['telefone_celular']];
        foreach ($colunas as $i => [, $x, $max]) {
            $pdf->texto($x, $y, caber((string) $valores[$i], $max), 9);
        }
        $pdf->linha(35, $y - 5, PdfSimples::LARGURA - 35, $y - 5);
        $y -= $alturaLinha;
    }

    // PARTE 3 - Enviar o arquivo para download (troca o cabeçalho JSON por PDF)
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="usuarios_ecotech_' . date('Y-m-d') . '.pdf"');
    echo $pdf->gerar();

} catch (PDOException $e) {
    error_log("Erro PDO em usuarios_pdf.php: " . $e->getMessage());
    erro("Erro no banco de dados", 500);
}
