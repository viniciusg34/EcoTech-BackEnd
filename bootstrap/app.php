<?php

use App\Exceptions\ApiException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
 * CONFIGURAÇÃO GERAL DA API
 * ---------------------------------------------------------------
 *  1. Rotas: routes/api.php com o prefixo /api/v1
 *  2. Middlewares: JSON sempre, apelidos "papel" e "idempotente"
 *  3. Erros: QUALQUER erro em /api/* sai no formato combinado com o front:
 *       { "erro": "codigo_curto", "message": "texto pronto", "campos": { "campo": ["msg"] } }
 */

// Monta a resposta de erro padrão
$erroJson = function (int $status, string $erro, string $message, array $extra = [], array $headers = []) {
    return response()->json(['erro' => $erro, 'message' => $message] + $extra, $status, $headers, JSON_UNESCAPED_UNICODE);
};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \App\Http\Middleware\ForcarJson::class,
        ]);

        $middleware->alias([
            'papel'       => \App\Http\Middleware\VerificarPapel::class,
            'idempotente' => \App\Http\Middleware\IdempotencyKey::class,
        ]);

        // Limite geral: 120 pedidos por minuto por conta (ou por IP, sem login)
        $middleware->throttleApi('api');
    })
    ->withExceptions(function (Exceptions $exceptions) use ($erroJson): void {
        $exceptions->dontReport([ApiException::class]);

        $naApi = fn (Request $request) => $request->is('api/*');

        // Nossos erros de negócio (já trazem o formato pronto)
        $exceptions->render(fn (ApiException $e) => $e->render());

        // 422: dados inválidos -> campos com as mensagens de cada campo
        $exceptions->render(function (ValidationException $e, Request $request) use ($naApi, $erroJson) {
            if (! $naApi($request)) {
                return null;
            }
            $campos = $e->errors();
            $primeira = collect($campos)->flatten()->first() ?: 'Verifique os dados enviados.';
            return $erroJson(422, 'validacao', $primeira, ['campos' => $campos]);
        });

        // 401: sem token, token vencido ou revogado
        $exceptions->render(function (AuthenticationException $e, Request $request) use ($naApi, $erroJson) {
            if ($naApi($request)) {
                return $erroJson(401, 'nao_autenticado', 'Sua sessão expirou ou você não está logado. Faça login de novo.');
            }
        });

        // 403: logado, mas sem permissão
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) use ($naApi, $erroJson) {
            if ($naApi($request)) {
                // Usa a mensagem da Policy (ex.: "Este Ecoponto não é da sua empresa.") quando houver
                $mensagem = $e->getMessage();
                if ($mensagem === '' || $mensagem === 'This action is unauthorized.') {
                    $mensagem = 'Você não tem permissão para isso.';
                }
                return $erroJson(403, 'sem_permissao', $mensagem);
            }
        });

        // 404: registro ou rota inexistente
        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($naApi, $erroJson) {
            if ($naApi($request)) {
                $registro = $e->getPrevious() instanceof ModelNotFoundException;
                return $erroJson(404, 'nao_encontrado', $registro ? 'Não encontrado.' : 'Rota não encontrada.');
            }
        });

        // 405: método errado (ex.: GET onde só existe POST)
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) use ($naApi, $erroJson) {
            if ($naApi($request)) {
                return $erroJson(405, 'metodo_nao_permitido', 'Método não permitido nesta rota.', [], $e->getHeaders());
            }
        });

        // 429: muitas tentativas
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($naApi, $erroJson) {
            if ($naApi($request)) {
                $segundos = (int) ($e->getHeaders()['Retry-After'] ?? 60);
                $espera = $segundos >= 120 ? ceil($segundos / 60) . ' minutos' : $segundos . ' segundos';
                return $erroJson(429, 'muitas_tentativas', "Muitas tentativas. Tente de novo em {$espera}.", [], $e->getHeaders());
            }
        });

        // 500 do banco: o erro real vai para storage/logs/laravel.log, o front recebe a mensagem genérica
        $exceptions->render(function (QueryException $e, Request $request) use ($naApi, $erroJson) {
            if ($naApi($request)) {
                return $erroJson(500, 'erro_banco', 'Erro no banco de dados. Tente novamente em instantes.');
            }
        });

        // Outros erros HTTP (ex.: 413, 415)
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($naApi, $erroJson) {
            if ($naApi($request)) {
                return $erroJson($e->getStatusCode(), 'erro_http', $e->getMessage() ?: 'Não foi possível atender o pedido.', [], $e->getHeaders());
            }
        });

        // Qualquer outro erro: 500 genérico
        $exceptions->render(function (Throwable $e, Request $request) use ($naApi, $erroJson) {
            if ($naApi($request)) {
                $extra = config('app.debug') ? ['detalhe' => $e->getMessage()] : [];
                return $erroJson(500, 'erro_interno', 'Erro inesperado. Tente novamente em instantes.', $extra);
            }
        });
    })->create();
