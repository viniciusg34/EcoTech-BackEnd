<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/*
 * LISTAS PAGINADAS no formato combinado com o front:
 *   { "data": [...], "meta": { "total", "pagina", "por_pagina", "ultima_pagina" }, "links": {...} }
 * Parâmetros da URL: ?pagina=1&por_pagina=20 (máximo 100).
 */
class Paginacao
{
    public const MAXIMO = 100;

    public static function porPagina(Request $request, int $padrao = 20): int
    {
        return max(1, min(self::MAXIMO, (int) $request->query('por_pagina', $padrao)));
    }

    public static function paginar(Builder|QueryBuilder|Relation $consulta, Request $request, int $padrao = 20): LengthAwarePaginator
    {
        $pagina = max(1, (int) $request->query('pagina', 1));
        return $consulta->paginate(self::porPagina($request, $padrao), ['*'], 'pagina', $pagina)
            ->withQueryString();
    }

    // $transformar: recebe cada item e devolve o array que vai para o front
    public static function resposta(LengthAwarePaginator $p, callable $transformar, array $metaExtra = []): JsonResponse
    {
        return response()->json([
            'data' => array_map($transformar, $p->items()),
            'meta' => [
                'total'         => $p->total(),
                'pagina'        => $p->currentPage(),
                'por_pagina'    => $p->perPage(),
                'ultima_pagina' => $p->lastPage(),
            ] + $metaExtra,
            'links' => [
                'primeira' => $p->url(1),
                'anterior' => $p->previousPageUrl(),
                'proxima'  => $p->nextPageUrl(),
                'ultima'   => $p->url($p->lastPage()),
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
