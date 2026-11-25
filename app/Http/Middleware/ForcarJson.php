<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 * Toda chamada à API é tratada como pedido de JSON.
 * Assim, até um erro de login (401) volta em JSON, nunca como página HTML ou redirecionamento.
 */
class ForcarJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');
        return $next($request);
    }
}
