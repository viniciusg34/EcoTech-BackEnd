<?php

use App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Api\V1\AdminEcopontoController;
use App\Http\Controllers\Api\V1\AdminEmpresaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CheckinController;
use App\Http\Controllers\Api\V1\ColetaController;
use App\Http\Controllers\Api\V1\EcopontoController;
use App\Http\Controllers\Api\V1\EmpresaController;
use App\Http\Controllers\Api\V1\LeituraController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PessoaController;
use App\Http\Controllers\Api\V1\PublicoController;
use App\Http\Controllers\Api\V1\ResgateController;
use App\Http\Controllers\Api\V1\SessaoEcopontoController;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
 * ROTAS DA API — todas começam com /api/v1 (configurado no bootstrap/app.php)
 * ---------------------------------------------------------------
 * auth:sanctum   = precisa do cabeçalho  Authorization: Bearer <token>
 * papel:...      = pessoa | empresa | master
 * idempotente    = precisa do cabeçalho  Idempotency-Key: <uuid>
 * throttle:...   = limite de tentativas (ver AppServiceProvider)
 * Para ver a lista no terminal:  php artisan route:list --path=api
 */

/* ---------------- Público ---------------- */
Route::get('/saude', [PublicoController::class, 'saude'])->withoutMiddleware(ThrottleRequests::class);
Route::post('/pessoas', [PessoaController::class, 'store'])->middleware('throttle:cadastro');
Route::post('/empresas', [EmpresaController::class, 'store'])->middleware('throttle:cadastro');
Route::get('/ecopontos', [EcopontoController::class, 'index']);
Route::get('/ecopontos/{ecoponto}', [EcopontoController::class, 'show']);
Route::get('/categorias', [PublicoController::class, 'categorias']);
Route::get('/recompensas', [PublicoController::class, 'recompensas']);
Route::get('/estatisticas', [PublicoController::class, 'estatisticas']);
Route::get('/config', [PublicoController::class, 'config']);
Route::get('/cep/{cep}', [PublicoController::class, 'cep']);

/* ---------------- Autenticação ---------------- */
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/2fa', [AuthController::class, 'doisFatores'])->middleware('throttle:verificacao');
    Route::post('/codigo', [AuthController::class, 'codigo'])->middleware('throttle:verificacao');
    Route::post('/codigo/reenviar', [AuthController::class, 'reenviarCodigo'])->middleware('throttle:verificacao');
    Route::post('/confirmar-email', [AuthController::class, 'confirmarEmail'])->middleware('throttle:verificacao');
    Route::post('/google', [AuthController::class, 'google'])->middleware('throttle:login');
    Route::post('/esqueci-senha', [AuthController::class, 'esqueciSenha'])->middleware('throttle:verificacao');
    Route::post('/esqueci-senha/verificar', [AuthController::class, 'verificarRecuperacao'])->middleware('throttle:verificacao');
    Route::post('/redefinir-senha', [AuthController::class, 'redefinirSenha'])->middleware('throttle:verificacao');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

/* ---------------- Pessoa (comum ou master) ---------------- */
Route::middleware(['auth:sanctum', 'papel:pessoa'])->group(function () {
    Route::get('/me', [MeController::class, 'show']);
    Route::put('/me', [MeController::class, 'update']);
    Route::delete('/me', [MeController::class, 'destroy']);
    Route::put('/me/senha', [MeController::class, 'alterarSenha']);
    Route::get('/me/coins', [MeController::class, 'coins'])->middleware('throttle:coins');
    Route::get('/me/movimentos', [MeController::class, 'movimentos']);
    Route::get('/me/coletas', [MeController::class, 'coletas']);
    Route::get('/me/resgates', [MeController::class, 'resgates']);
    Route::get('/me/sessao', [MeController::class, 'sessao']);
    Route::delete('/me/sessao', [MeController::class, 'sairDaSessao']);
    Route::get('/me/preferencias', [MeController::class, 'preferencias']);
    Route::put('/me/preferencias', [MeController::class, 'salvarPreferencias']);

    Route::post('/ecopontos/conectar', [SessaoEcopontoController::class, 'conectar']);
    Route::post('/checkins', [CheckinController::class, 'store']);
    Route::post('/resgates', [ResgateController::class, 'store'])->middleware('idempotente');
});

/* ---------------- Empresa parceira ---------------- */
Route::middleware(['auth:sanctum', 'papel:empresa'])->group(function () {
    Route::get('/empresas/me', [EmpresaController::class, 'me']);
    Route::put('/empresas/me', [EmpresaController::class, 'update']);
    Route::put('/empresas/me/senha', [EmpresaController::class, 'alterarSenha']);
    Route::get('/empresas/me/dashboard', [EmpresaController::class, 'dashboard']);
    Route::get('/empresas/me/ecopontos', [EcopontoController::class, 'daEmpresa']);
    Route::get('/empresas/me/leituras', [EmpresaController::class, 'leituras']);

    Route::post('/ecopontos', [EcopontoController::class, 'store']);
    Route::put('/ecopontos/{ecoponto}', [EcopontoController::class, 'update']);
    Route::patch('/ecopontos/{ecoponto}', [EcopontoController::class, 'update']);
    Route::get('/ecopontos/{ecoponto}/qr', [EcopontoController::class, 'qr']);
    Route::get('/ecopontos/{ecoponto}/qr.svg', [EcopontoController::class, 'qrSvg']);
    Route::get('/ecopontos/{ecoponto}/sessao-ativa', [SessaoEcopontoController::class, 'ativa']);
    Route::delete('/ecopontos/{ecoponto}/sessao-ativa', [SessaoEcopontoController::class, 'encerrar']);
    Route::post('/ecopontos/{ecoponto}/leituras', [LeituraController::class, 'ecoponto'])
        ->middleware(['idempotente:opcional', 'throttle:leituras']);
    Route::post('/coletas', [ColetaController::class, 'store'])->middleware('idempotente:opcional');
});

/* ---------------- Master (administrador) ---------------- */
Route::middleware(['auth:sanctum', 'papel:master'])->group(function () {
    // Leitura avulsa, só para teste (o fluxo real é o Ecoponto lendo o produto)
    Route::post('/scans', [LeituraController::class, 'scan'])->middleware(['idempotente', 'throttle:leituras']);

    Route::prefix('admin')->group(function () {
        Route::get('/painel', Admin\PainelController::class);

        Route::get('/pessoas', [Admin\PessoaController::class, 'index']);
        Route::get('/pessoas/pdf', [Admin\PessoaController::class, 'pdf']);
        Route::delete('/pessoas/{pessoa}', [Admin\PessoaController::class, 'destroy']);
        Route::post('/pessoas/{pessoa}/ajustes', [Admin\PessoaController::class, 'ajuste']);

        Route::get('/logs-autenticacao', [Admin\LogController::class, 'index']);

        Route::get('/recompensas', [Admin\RecompensaController::class, 'index']);
        Route::post('/recompensas', [Admin\RecompensaController::class, 'store']);
        Route::match(['put', 'patch'], '/recompensas/{recompensa}', [Admin\RecompensaController::class, 'update']);
        Route::delete('/recompensas/{recompensa}', [Admin\RecompensaController::class, 'destroy']);

        Route::get('/produtos', [Admin\ProdutoController::class, 'index']);
        Route::post('/produtos', [Admin\ProdutoController::class, 'store']);
        Route::match(['put', 'patch'], '/produtos/{produto}', [Admin\ProdutoController::class, 'update']);
        Route::delete('/produtos/{produto}', [Admin\ProdutoController::class, 'destroy']);

        Route::get('/empresas', [AdminEmpresaController::class, 'index']);
        Route::match(['put', 'patch'], '/empresas/{empresa}', [AdminEmpresaController::class, 'update']);
        Route::delete('/empresas/{empresa}', [AdminEmpresaController::class, 'destroy']);

        Route::get('/ecopontos', [AdminEcopontoController::class, 'index']);
        Route::match(['put', 'patch'], '/ecopontos/{ecoponto}', [AdminEcopontoController::class, 'update']);
        Route::delete('/ecopontos/{ecoponto}', [AdminEcopontoController::class, 'destroy']);
    });
});

// Qualquer outra URL em /api/v1 -> 404 em JSON
Route::fallback(fn () => throw new NotFoundHttpException());
