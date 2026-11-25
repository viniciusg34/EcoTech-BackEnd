<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeituraRequest;
use App\Models\Ecoponto;
use App\Services\ScanService;
use App\Services\SessaoEcopontoService;
use Illuminate\Http\JsonResponse;

/*
 * LEITURA DE CÓDIGO DE BARRAS
 *   POST /ecopontos/{id}/leituras  — o Ecoponto (empresa dona) lê o produto; as moedas vão para a pessoa da sessão
 *   POST /scans                    — só master, para teste (credita o próprio master)
 */
class LeituraController extends Controller
{
    public function __construct(private ScanService $scans)
    {
    }

    public function ecoponto(LeituraRequest $request, Ecoponto $ecoponto, SessaoEcopontoService $sessoes): JsonResponse
    {
        $this->authorize('gerenciar', $ecoponto);
        if ($ecoponto->status !== 'ativo') {
            throw new ApiException(409, 'ecoponto_inativo', 'Este Ecoponto não está ativo.');
        }

        $sessao = $sessoes->doEcoponto($ecoponto, (int) $request->input('sessao_id'));

        return response()->json($this->scans->processar(
            $sessao->pessoa, (string) $request->input('code'), $ecoponto, $sessao, $request->header('Idempotency-Key')
        ));
    }

    public function scan(LeituraRequest $request): JsonResponse
    {
        return response()->json($this->scans->processar(
            $request->user(), (string) $request->input('code'), null, null, $request->header('Idempotency-Key')
        ));
    }
}
