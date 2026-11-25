<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SessaoRequest;
use App\Services\CheckinService;
use App\Services\SessaoEcopontoService;
use Illuminate\Http\JsonResponse;

class CheckinController extends Controller
{
    // POST /checkins  { sessao_id }  -> { coinsAwarded, balance, message }
    public function store(SessaoRequest $request, SessaoEcopontoService $sessoes, CheckinService $checkins): JsonResponse
    {
        $sessao = $sessoes->daPessoa($request->user(), (int) $request->input('sessao_id'));
        return response()->json($checkins->fazer($request->user(), $sessao));
    }
}
