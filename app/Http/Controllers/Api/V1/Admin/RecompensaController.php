<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecompensaRequest;
use App\Http\Resources\RecompensaResource;
use App\Models\Recompensa;
use App\Models\Resgate;
use App\Support\Paginacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// MASTER: CRUD de recompensas
class RecompensaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $consulta = Recompensa::query()
            ->when($request->filled('cat') && $request->query('cat') !== 'todos', fn ($q) => $q->where('categoria', $request->query('cat')))
            ->orderBy('categoria')->orderBy('custo_coins');

        return Paginacao::resposta(Paginacao::paginar($consulta, $request, 50),
            fn ($r) => (new RecompensaResource($r))->resolve($request));
    }

    public function store(RecompensaRequest $request): JsonResponse
    {
        $recompensa = Recompensa::create($request->validated() + ['ativa' => true]);
        return response()->json((new RecompensaResource($recompensa->fresh()))->resolve($request) + ['message' => 'Recompensa criada.'], 201);
    }

    public function update(RecompensaRequest $request, Recompensa $recompensa): JsonResponse
    {
        $recompensa->update($request->validated());
        return response()->json((new RecompensaResource($recompensa))->resolve($request) + ['message' => 'Recompensa atualizada.']);
    }

    // Se já foi resgatada, só desativa (o histórico de resgates aponta para ela)
    public function destroy(Recompensa $recompensa): JsonResponse
    {
        if (Resgate::query()->where('recompensa_id', $recompensa->id)->exists()) {
            $recompensa->update(['ativa' => false]);
            return response()->json(['message' => 'A recompensa já tinha resgates, então foi desativada (não apagada).']);
        }
        $recompensa->delete();
        return response()->json(['message' => 'Recompensa apagada.']);
    }
}
