<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StatusEmpresaRequest;
use App\Models\Empresa;
use App\Services\EmpresaService;
use App\Support\Banco;
use App\Support\Paginacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// MASTER: lista de empresas e suspensão/reativação
class EmpresaController extends Controller
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
            ->orderBy('nome_empresa');

        return Paginacao::resposta(Paginacao::paginar($consulta, $request, 50), fn ($e) => $this->empresas->dados($e));
    }

    // PATCH /admin/empresas/{id}  { status: "ativa" | "suspensa" }
    public function status(StatusEmpresaRequest $request, Empresa $empresa): JsonResponse
    {
        $empresa->forceFill(['status' => $request->input('status')])->save();
        if ($empresa->status === 'suspensa') {
            $empresa->tokens()->delete(); // sai na hora
        }

        return response()->json($this->empresas->dados($empresa) + [
            'message' => $empresa->status === 'suspensa' ? 'Empresa suspensa.' : 'Empresa reativada.',
        ]);
    }
}
