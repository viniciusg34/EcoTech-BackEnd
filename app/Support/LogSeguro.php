<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/*
 * Log que NUNCA derruba a requisição.
 * O canal "stack" está com ignore_exceptions = false: se o servidor não conseguir
 * escrever em storage/logs (comum no Railway), Log::info() lança erro e vira 500.
 * Aqui, se o log falhar, a linha é só descartada.
 * Dica: no Railway use LOG_CHANNEL=stderr para ver os logs no painel.
 */
class LogSeguro
{
    public static function __callStatic(string $nivel, array $argumentos): void
    {
        try {
            Log::{$nivel}(...$argumentos);
        } catch (Throwable) {
            // sem log, mas a resposta segue normal
        }
    }
}
