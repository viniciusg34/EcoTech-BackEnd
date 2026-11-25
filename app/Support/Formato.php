<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/*
 * TEXTOS PRONTOS PARA O FRONT (padrão brasileiro).
 * O front não formata nada: a API já manda "1.250", "12,5 kg", "10/11/2026 às 14:32"...
 */
class Formato
{
    // 1250 -> "1.250"
    public static function inteiro(int|float|null $numero): string
    {
        return number_format((float) ($numero ?? 0), 0, ',', '.');
    }

    // 30 -> "+30" ; -80 -> "-80"
    public static function comSinal(int $numero): string
    {
        return ($numero > 0 ? '+' : ($numero < 0 ? '-' : '')) . self::inteiro(abs($numero));
    }

    // 12.5 -> "12,5 kg" ; 3 -> "3 kg" ; 0.02 -> "0,02 kg"
    public static function kg(float|int|string|null $kg): string
    {
        $kg = round((float) ($kg ?? 0), 2);
        $casas = $kg == floor($kg) ? 0 : (round($kg, 1) == $kg ? 1 : 2);
        return number_format($kg, $casas, ',', '.') . ' kg';
    }

    // Número sem unidade, com até 2 casas: 1150 -> "1.150" ; 0.52 -> "0,52"
    public static function numero(float|int|string|null $valor): string
    {
        $valor = round((float) ($valor ?? 0), 2);
        $casas = $valor == floor($valor) ? 0 : (round($valor, 1) == $valor ? 1 : 2);
        return number_format($valor, $casas, ',', '.');
    }

    // Quando foi, em texto curto (horário de Brasília):
    // "Hoje, 08h30" ; "Ontem, 16h40" ; "Há 3 dias" ; "02/11/2026"
    public static function quando(?CarbonInterface $data): string
    {
        if (! $data) {
            return 'Sem atividade ainda';
        }
        $fuso = 'America/Sao_Paulo';
        $data = $data->copy()->setTimezone($fuso);
        $dias = (int) Carbon::now($fuso)->startOfDay()->diffInDays($data->copy()->startOfDay(), true);

        return match (true) {
            $dias === 0 => 'Hoje, ' . $data->format('H\hi'),
            $dias === 1 => 'Ontem, ' . $data->format('H\hi'),
            $dias <= 6  => "Há {$dias} dias",
            default     => $data->format('d/m/Y'),
        };
    }

    // Data e hora no horário de Brasília: "10/11/2026 às 14:32"
    public static function dataHora(?CarbonInterface $data): ?string
    {
        return $data ? $data->copy()->setTimezone('America/Sao_Paulo')->format('d/m/Y \à\s H:i') : null;
    }

    // Data em ISO 8601 (UTC com Z): "2026-11-10T17:32:00Z"
    public static function iso(?CarbonInterface $data): ?string
    {
        return $data ? $data->copy()->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }

    public static function data(?CarbonInterface $data): ?string
    {
        return $data?->format('d/m/Y');
    }

    // "12345678909" -> "123.456.789-09"
    public static function cpf(?string $cpf): ?string
    {
        if (! $cpf || strlen($cpf) !== 11) {
            return $cpf;
        }
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }

    // LGPD: listas nunca mostram o CPF inteiro -> "***.456.789-**"
    public static function cpfMascarado(?string $cpf): ?string
    {
        if (! $cpf || strlen($cpf) !== 11) {
            return null;
        }
        return '***.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-**';
    }

    // "14.123.456/0001-99"
    public static function cnpj(?string $cnpj): ?string
    {
        if (! $cnpj || strlen($cnpj) !== 14) {
            return $cnpj;
        }
        return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/'
            . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    }

    // "21999991234" -> "(21) 99999-1234" ; "2133331234" -> "(21) 3333-1234"
    public static function telefone(?string $telefone): ?string
    {
        if (! $telefone) {
            return null;
        }
        $ddd = substr($telefone, 0, 2);
        $numero = substr($telefone, 2);
        $meio = strlen($numero) - 4;
        return "({$ddd}) " . substr($numero, 0, $meio) . '-' . substr($numero, $meio);
    }

    // "20040020" -> "20040-020"
    public static function cep(?string $cep): ?string
    {
        if (! $cep || strlen($cep) !== 8) {
            return $cep;
        }
        return substr($cep, 0, 5) . '-' . substr($cep, 5);
    }

    // "Ana Maria da Silva" -> "Ana M." (LGPD: a tela do Ecoponto não mostra o nome completo)
    public static function nomeAbreviado(?string $nome): ?string
    {
        $partes = preg_split('/\s+/u', trim((string) $nome)) ?: [];
        if (! $partes || $partes[0] === '') {
            return null;
        }
        $primeiro = $partes[0];
        $segundo = $partes[1] ?? null;
        return $segundo ? $primeiro . ' ' . mb_strtoupper(mb_substr($segundo, 0, 1)) . '.' : $primeiro;
    }

    // Termina a frase com ponto, sem duplicar ("Ana M." continua "Ana M.")
    public static function frase(string $texto): string
    {
        $texto = rtrim($texto);
        return preg_match('/[.!?]$/u', $texto) ? $texto : $texto . '.';
    }

    public static function agora(): Carbon
    {
        return Carbon::now();
    }
}
