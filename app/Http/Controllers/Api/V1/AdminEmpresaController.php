<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminEmpresaRequest;
use App\Models\Coleta;
use App\Models\Empresa;
use App\Models\LogAutenticacao;
use App\Services\EmpresaService;
use App\Support\Banco;
use App\Support\Paginacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * MASTER: empresas parceiras
 * ---------------------------------------------------------------
 * GET    /admin/empresas?busca=recicla
 * PATCH  /admin/empresas/{id}   { nome_empresa?, razao_social?, email?, endereco?, status? }
 *                              status: "ativa" | "suspensa" (suspensa = não entra mais)
 * DELETE /admin/empresas/{id}
 *
 * Excluir: se algum Ecoponto da empresa já recebeu coletas, a empresa NÃO é
 * apagada (o histórico das pessoas depende dela): fica "suspensa" e os
 * Ecopontos viram "inativo". Sem nenhuma coleta, tudo é apagado de verdade.
 */
class AdminEmpresaController extends Controller
{
    public function __construct(private EmpresaService $empresas)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $consulta = Empresa::query()
            ->when($request->filled('busca'), function ($q) use ($request) {
                $termo = '%' . Banco::escaparLike(mb_strtolower((string) $request->query('busca'))) . '%';
                $q->where(fn ($w) => $w->whereRaw("LOWER(nome_empresa) LIKE ? ESCAPE '!'", [$termo])
                    ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$termo])
                    ->orWhere('cnpj', 'like', $termo));
            })
            ->when(in_array($request->query('status'), ['ativa', 'suspensa'], true),
                fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('nome_empresa');

        return Paginacao::resposta(Paginacao::paginar($consulta, $request, 50), fn ($e) => $this->empresas->dados($e));
    }

    // PATCH /admin/empresas/{id}
    public function update(AdminEmpresaRequest $request, Empresa $empresa): JsonResponse
    {
        $dados = $request->validated();
        $statusAntes = $empresa->status;

        $empresa->forceFill($dados)->save();
        if ($empresa->status === 'suspensa') {
            $empresa->tokens()->delete(); // sai na hora
        }
        Cache::forget('estatisticas_publicas');

        $message = match (true) {
            $statusAntes !== $empresa->status && $empresa->status === 'suspensa' => 'Empresa suspensa.',
            $statusAntes !== $empresa->status => 'Empresa reativada.',
            default => 'Empresa atualizada.',
        };

        return response()->json($this->empresas->dados($empresa->fresh()) + ['message' => $message]);
    }

    // DELETE /admin/empresas/{id}
    public function destroy(Empresa $empresa): JsonResponse
    {
        $ids = $empresa->ecopontos()->pluck('id');
        $temHistorico = $ids->isNotEmpty() && Coleta::query()->whereIn('ecoponto_id', $ids)->exists();

        if ($temHistorico) {
            DB::transaction(function () use ($empresa) {
                $empresa->forceFill(['status' => 'suspensa'])->save();
                $empresa->tokens()->delete();
                foreach ($empresa->ecopontos as $ecoponto) {
                    AdminEcopontoController::remover($ecoponto); // com histórico: inativo; sem: apagado
                }
            });
            Cache::forget('estatisticas_publicas');

            return response()->json([
                'acao'    => 'desativada',
                'message' => "{$empresa->nome_empresa} tem histórico de coletas: foi suspensa e os Ecopontos desativados, em vez de apagados.",
            ]);
        }

        DB::transaction(function () use ($empresa) {
            foreach ($empresa->ecopontos as $ecoponto) {
                AdminEcopontoController::remover($ecoponto);
            }
            $empresa->tokens()->delete();
            LogAutenticacao::query()->where('empresa_id', $empresa->id)->delete(); // logins dela (não aparecem no log do master)
            $empresa->delete();
        });
        Cache::forget('estatisticas_publicas');

        return response()->json(['acao' => 'excluida', 'message' => "{$empresa->nome_empresa} excluída."]);
    }
}
