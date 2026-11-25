<?php

namespace App\Support;
/*
 * GERADOR DE PDF SIMPLES (sem biblioteca externa)
 * ---------------------------------------------------------------
 * Um PDF é um arquivo de texto com "objetos" numerados:
 *   catálogo -> lista de páginas -> cada página -> conteúdo (comandos de desenho)
 * No final vem uma tabela (xref) dizendo em que byte começa cada objeto.
 *
 * Esta classe só sabe escrever texto e linhas, que é o que a lista precisa.
 * Uso:
 *   $pdf = new PdfSimples();
 *   $pdf->novaPagina();
 *   $pdf->texto(40, 550, "Olá", 12, true);
 *   echo $pdf->gerar();
 */
class PdfSimples
{
    // A4 deitado (paisagem), em pontos (1 ponto = 1/72 polegada)
    public const LARGURA = 842;
    public const ALTURA  = 595;

    private array $paginas = []; // cada item = comandos de desenho de uma página

    public function novaPagina(): void
    {
        $this->paginas[] = '';
    }

    // Escreve um texto. (x, y) contam a partir do canto INFERIOR esquerdo.
    public function texto(float $x, float $y, string $texto, float $tamanho = 10, bool $negrito = false): void
    {
        $fonte = $negrito ? 'F2' : 'F1';
        $this->desenhar(sprintf("BT /%s %.1f Tf %.1f %.1f Td (%s) Tj ET\n", $fonte, $tamanho, $x, $y, $this->escapar($texto)));
    }

    public function linha(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->desenhar(sprintf("0.6 w %.1f %.1f m %.1f %.1f l S\n", $x1, $y1, $x2, $y2));
    }

    // Retângulo preenchido de cinza claro (fundo do cabeçalho da tabela)
    public function faixa(float $x, float $y, float $largura, float $altura): void
    {
        $this->desenhar(sprintf("0.88 g %.1f %.1f %.1f %.1f re f 0 g\n", $x, $y, $largura, $altura));
    }

    private function desenhar(string $comando): void
    {
        $this->paginas[array_key_last($this->paginas)] .= $comando;
    }

    // O PDF usa a codificação Windows-1252 para acentos, e ( ) \ precisam de barra antes
    private function escapar(string $texto): string
    {
        $texto = mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $texto);
    }

    public function gerar(): string
    {
        $objetos = [];
        // 1 = catálogo, 2 = lista de páginas, 3 e 4 = fontes (Helvetica normal e negrito)
        $objetos[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objetos[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objetos[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        $idsPaginas = [];
        $proximo = 5;
        foreach ($this->paginas as $conteudo) {
            $idPagina = $proximo++;
            $idConteudo = $proximo++;
            $idsPaginas[] = "$idPagina 0 R";
            $objetos[$idPagina] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>",
                self::LARGURA, self::ALTURA, $idConteudo
            );
            $objetos[$idConteudo] = "<< /Length " . strlen($conteudo) . " >>\nstream\n" . $conteudo . "endstream";
        }
        $objetos[2] = "<< /Type /Pages /Kids [" . implode(' ', $idsPaginas) . "] /Count " . count($idsPaginas) . " >>";
        ksort($objetos);

        // Monta o arquivo guardando a posição (em bytes) de cada objeto
        $pdf = "%PDF-1.4\n";
        $posicoes = [];
        foreach ($objetos as $id => $corpo) {
            $posicoes[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$corpo\nendobj\n";
        }

        $inicioXref = strlen($pdf);
        $total = count($objetos) + 1;
        $pdf .= "xref\n0 $total\n0000000000 65535 f \n";
        foreach ($posicoes as $pos) {
            $pdf .= sprintf("%010d 00000 n \n", $pos);
        }
        $pdf .= "trailer\n<< /Size $total /Root 1 0 R >>\nstartxref\n$inicioXref\n%%EOF\n";
        return $pdf;
    }
}
