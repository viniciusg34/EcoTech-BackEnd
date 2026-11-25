<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\EstatisticaService;
use Illuminate\Http\JsonResponse;

// MASTER: painel geral (resumo, kg por mês, kg por categoria e todos os Ecopontos)
class PainelController extends Controller
{
    public function __invoke(EstatisticaService $estatisticas): JsonResponse
    {
        return response()->json($estatisticas->painelMaster());
    }
}
