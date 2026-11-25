<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/*
 * Ajudas para diferenças entre bancos.
 * ---------------------------------------------------------------
 * Em produção o banco é MySQL 8 (Aiven) ou MariaDB (XAMPP).
 * Nos testes automáticos é SQLite em memória.
 * Algumas coisas (CHECK, views, funções de data) mudam de um para outro.
 */
class Banco
{
    // true quando o banco é MySQL ou MariaDB
    public static function ehMysql(?string $conexao = null): bool
    {
        return in_array(DB::connection($conexao)->getDriverName(), ['mysql', 'mariadb'], true);
    }

    /*
     * Cria uma regra CHECK (ex.: saldo nunca negativo).
     * Só no MySQL/MariaDB: o SQLite não aceita ALTER TABLE ... ADD CONSTRAINT,
     * e nos testes quem garante a regra é o próprio código (CoinService etc.).
     */
    public static function check(string $tabela, string $nome, string $expressao): void
    {
        if (self::ehMysql()) {
            DB::statement("ALTER TABLE `{$tabela}` ADD CONSTRAINT `{$nome}` CHECK ({$expressao})");
        }
    }

    // Expressão SQL que transforma uma data em "2026-11" (ano-mês), para agrupar por mês
    public static function anoMes(string $coluna): string
    {
        return self::ehMysql()
            ? "DATE_FORMAT({$coluna}, '%Y-%m')"
            : "strftime('%Y-%m', {$coluna})";
    }

    // Protege os caracteres especiais do LIKE (% e _). Usar com ESCAPE '!'
    public static function escaparLike(string $texto): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $texto);
    }
}
