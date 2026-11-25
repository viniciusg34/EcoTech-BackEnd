<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResgatarRequest;
use App\Http\Resources\ResgateResource;
use App\Services\CoinService;
use App\Services\ResgateService;
use Illuminate\Http\JsonResponse;

class ResgateController extends Controller
{
    // POST /resgates  { recompensa_id }  + Idempotency-Key  -> 201 { message, codigo_cupom }
    public function store(ResgatarRequest $request, ResgateService $resgates, CoinService $coins): JsonResponse
    {
        $resgate = $resgates->resgatar($request->user(), (int) $request->input('recompensa_id'));

        return response()->json([
            'message'      => "Resgate realizado! Seu cupom: {$resgate->codigo_cupom}",
            'codigo_cupom' => $resgate->codigo_cupom,
            'resgate'      => (new ResgateResource($resgate))->resolve($request),
        ] + $coins->saldoResposta($request->user()->id), 201);
    }
}
