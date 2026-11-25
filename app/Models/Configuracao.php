<?php

namespace App\Models;

/*
 * Tabela chave -> valor. Use Configuracao::valor('checkin_coins', 50).
 */
class Configuracao extends Modelo
{
    protected $table = 'configuracao';
    protected $primaryKey = 'chave';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'publico' => 'boolean',
    ];

    // Valores lidos nesta requisição (para não ir ao banco toda hora)
    private static array $memoria = [];

    public static function valor(string $chave, mixed $padrao = null): mixed
    {
        if (! array_key_exists($chave, self::$memoria)) {
            self::$memoria[$chave] = static::query()->whereKey($chave)->value('valor');
        }
        return self::$memoria[$chave] ?? $padrao;
    }

    public static function inteiro(string $chave, int $padrao): int
    {
        return (int) self::valor($chave, $padrao);
    }

    public static function decimal(string $chave, float $padrao): float
    {
        return (float) self::valor($chave, $padrao);
    }

    public static function limparMemoria(): void
    {
        self::$memoria = [];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::limparMemoria());
    }
}
