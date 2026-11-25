<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ColetaRequest;
use App\Http\Resources\ColetaResource;
use App\Models\SessaoEcoponto;
use App\Services\ColetaService;
use App\Services\SessaoEcopontoService;
use Illuminate\Http\JsonResponse;

class ColetaController extends Controller
{
    /*
     * POST /coletas  { sessao_id, itens: [ { categoria, quantidade } ] }
     * Feito pela empresa dona do Ecoponto da sessão (quem pesa o material é o Ecoponto).
     */
    public function store(ColetaRequest $request, SessaoEcopontoService $sessoes, ColetaService $coletas): JsonResponse
    {
        $sessao = SessaoEcoponto::query()->with('ecoponto')->find((int) $request->input('sessao_id'));
        if (! $sessao) {
            throw new ApiException(404, 'sessao_nao_encontrada', 'Sessão não encontrada. Peça para a pessoa ler o QR Code de novo.');
        }
        $this->authorize('gerenciar', $sessao->ecoponto);
        $sessao = $sessoes->doEcoponto($sessao->ecoponto, $sessao->id); // confere se ainda está valendo

        $resultado = $coletas->registrarEntrega($sessao->ecoponto, $sessao, $request->input('itens'));
        $resultado['coleta'] = (new ColetaResource($resultado['coleta']))->resolve($request);

        return response()->json($resultado, 201);
    }
}
