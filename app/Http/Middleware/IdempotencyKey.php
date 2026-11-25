<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/*
 * IDEMPOTÊNCIA (cabeçalho Idempotency-Key, um UUID gerado pelo front).
 * ---------------------------------------------------------------
 * Se a internet cair e o front reenviar o MESMO pedido com a MESMA chave,
 * a API devolve a resposta guardada em vez de creditar/debitar de novo.
 * A chave vale por 24 h e é separada por conta (uma pessoa não "rouba" a chave de outra).
 *   ->middleware('idempotente')            chave obrigatória
 *   ->middleware('idempotente:opcional')   usa só se o front mandar
 */
class IdempotencyKey
{
    public function handle(Request $request, Closure $next, string $modo = 'obrigatoria'): Response
    {
        $chave = trim((string) $request->header('Idempotency-Key'));

        if ($chave === '') {
            if ($modo === 'opcional') {
                return $next($request);
            }
            throw ApiException::validacao('Idempotency-Key', 'Envie o cabeçalho Idempotency-Key (um UUID).');
        }
        if (! Str::isUuid($chave)) {
            throw ApiException::validacao('Idempotency-Key', 'O cabeçalho Idempotency-Key precisa ser um UUID.');
        }

        $conta = $request->user();
        $dono = $conta ? $conta->getMorphClass() . ':' . $conta->getKey() : 'anonimo:' . $request->ip();
        $base = 'idem:' . sha1($dono . '|' . $request->method() . '|' . $request->path() . '|' . strtolower($chave));

        // Já respondemos este pedido? Devolve igual.
        if ($guardada = Cache::get($base)) {
            return $this->reconstruir($guardada);
        }

        // Trava: dois pedidos iguais chegando juntos não rodam ao mesmo tempo
        $trava = Cache::lock($base . ':trava', 15);
        if (! $trava->get()) {
            throw new ApiException(409, 'pedido_em_andamento', 'Este pedido já está sendo processado. Aguarde um instante.');
        }

        try {
            if ($guardada = Cache::get($base)) {
                return $this->reconstruir($guardada);
            }

            $resposta = $next($request);

            // Só guarda respostas definitivas (sucesso e erros de negócio). Erro 5xx pode ser tentado de novo.
            if ($resposta->getStatusCode() < 500) {
                Cache::put($base, [
                    'status'  => $resposta->getStatusCode(),
                    'corpo'   => $resposta->getContent(),
                    'tipo'    => $resposta->headers->get('Content-Type', 'application/json'),
                ], now()->addDay());
            }

            return $resposta;
        } finally {
            $trava->release();
        }
    }

    private function reconstruir(array $guardada): Response
    {
        return response($guardada['corpo'], $guardada['status'], [
            'Content-Type'         => $guardada['tipo'],
            'Idempotency-Replayed' => 'true',
        ]);
    }
}
