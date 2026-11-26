<?php

namespace App\Support;

/*
 * LIMPEZA DE TEXTO E CONTAS DE VALIDAÇÃO (CPF, CNPJ, código de barras).
 */
class Texto
{
    // "  Ana   Maria " -> "Ana Maria"
    public static function limparEspacos(?string $texto): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $texto));
    }

    // "123.456.789-09" -> "12345678909"
    public static function digitos(?string $texto): string
    {
        return preg_replace('/\D/', '', (string) $texto);
    }

    /*
     * Para comparar respostas sem ligar para maiúscula, acento, hífen, apóstrofo ou espaços:
     *   "MARIA-JOSÉ  D'Ávila" = "maria jose d’avila" = "Maria Jose D Avila"
     * Também funciona com acento "separado" (alguns teclados de celular mandam o
     * "é" como "e" + acento) e com letras como ç, ñ, ü.
     */
    public static function normalizar(?string $texto): string
    {
        $texto = (string) $texto;
        if (class_exists(\Normalizer::class)) {
            // separa letra e acento ("é" -> "e" + "´") para remover só o acento
            $texto = \Normalizer::normalize($texto, \Normalizer::FORM_D) ?: $texto;
            $texto = preg_replace('/\p{Mn}+/u', '', $texto);
        }
        $mapa = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
        ];
        $texto = strtr(mb_strtolower($texto, 'UTF-8'), $mapa);
        // hífen, apóstrofo, ponto etc. viram espaço; sobra só letra, número e um espaço entre palavras
        $texto = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $texto);

        return trim($texto);
    }

    /*
     * Data em vários formatos -> "aaaa-mm-dd" (ou null se não for uma data real).
     * Aceita: "25/12/2000", "5/3/2000" (sem zero), "25-12-2000", "25.12.2000",
     * "25122000", "25/12/00" (ano com 2 dígitos), "2000-12-25" e "2000-1-5".
     * Ano com 2 dígitos: até o ano atual vira 20xx, acima vira 19xx ("95" -> 1995).
     */
    public static function data(?string $data): ?string
    {
        $data = trim((string) $data);

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $data, $m)) {
            [$ano, $mes, $dia] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})\s*[/.\-]\s*(\d{1,2})\s*[/.\-]\s*(\d{4}|\d{2})$#', $data, $m)
            || preg_match('/^(\d{2})(\d{2})(\d{4}|\d{2})$/', $data, $m)) {
            [$dia, $mes, $ano] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            if (strlen($m[3]) === 2) {
                $ano += $ano > (int) date('y') ? 1900 : 2000;
            }
        } else {
            return null;
        }

        return checkdate($mes, $dia, $ano) ? sprintf('%04d-%02d-%02d', $ano, $mes, $dia) : null;
    }

    // CPF com dígito verificador
    public static function cpfValido(?string $cpf): bool
    {
        $cpf = self::digitos($cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($posicao = 9; $posicao < 11; $posicao++) {
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $cpf[$i] * (($posicao + 1) - $i);
            }
            if ((int) $cpf[$posicao] !== ((10 * $soma) % 11) % 10) {
                return false;
            }
        }
        return true;
    }

    // CNPJ com os 2 dígitos verificadores
    public static function cnpjValido(?string $cnpj): bool
    {
        $cnpj = self::digitos($cnpj);
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }
        $pesos = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        foreach ([12, 13] as $posicao) {
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $cnpj[$i] * $pesos[$i + (13 - $posicao)];
            }
            $resto = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;
            if ((int) $cnpj[$posicao] !== $digito) {
                return false;
            }
        }
        return true;
    }

    /*
     * Código de barras (GTIN): EAN-8 (8 dígitos), UPC-A (12) ou EAN-13 (13).
     * O último dígito é o verificador: pesos 3 e 1 alternados, da direita para a esquerda.
     */
    public static function codigoBarrasValido(?string $codigo): bool
    {
        $codigo = (string) $codigo;
        if (! preg_match('/^\d+$/', $codigo) || ! in_array(strlen($codigo), [8, 12, 13], true)) {
            return false;
        }
        $corpo = substr($codigo, 0, -1);
        $soma = 0;
        $peso = 3;
        for ($i = strlen($corpo) - 1; $i >= 0; $i--) {
            $soma += (int) $corpo[$i] * $peso;
            $peso = $peso === 3 ? 1 : 3;
        }
        return (int) substr($codigo, -1) === (10 - $soma % 10) % 10;
    }
}
