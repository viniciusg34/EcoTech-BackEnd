<?php

namespace App\Support;

use DateTime;

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

    // Para comparar respostas sem ligar para acento e maiúscula: "  José  " = "jose"
    public static function normalizar(?string $texto): string
    {
        $mapa = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
        ];
        return strtr(mb_strtolower(self::limparEspacos($texto), 'UTF-8'), $mapa);
    }

    // Aceita "25/12/2000", "25-12-2000", "25122000" ou "2000-12-25" e devolve "2000-12-25" (ou null)
    public static function data(?string $data): ?string
    {
        $data = trim((string) $data);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'dmY'] as $formato) {
            $d = DateTime::createFromFormat('!' . $formato, $data);
            if ($d && $d->format($formato) === $data) {
                return $d->format('Y-m-d');
            }
        }
        return null;
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
