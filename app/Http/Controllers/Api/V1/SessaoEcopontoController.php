<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConectarRequest;
use App\Models\Ecoponto;
use App\Services\EcopontoQrService;
use App\Services\SessaoEcopontoService;
use App\Support\Formato;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/*
 * SESSÃO NO ECOPONTO
 *   Pessoa:  POST /ecopontos/conectar (lê o QR da placa)
 *   Empresa: GET e DELETE /ecopontos/{id}/sessao-ativa (a tela do Ecoponto)
 */
class SessaoEcopontoController extends Controller
{
    public function __construct(private SessaoEcopontoService $sessoes)
    {
    }

    // POST /ecopontos/conectar  { qr }
    public function conectar(ConectarRequest $request, EcopontoQrService $qr): JsonResponse
    {
        $ecoponto = $qr->validar($request->input('qr'));
        $sessao = $this->sessoes->conectar($request->user(), $ecoponto);

        return response()->json([
            'sessao_id'           => $sessao->id,
            'ecoponto'            => ['id' => $ecoponto->id, 'codigo' => $ecoponto->codigo, 'nome' => $ecoponto->nome],
            'expira_em'           => Formato::iso($sessao->expira_em),
            'expira_em_formatado' => $sessao->expira_em->copy()->setTimezone('America/Sao_Paulo')->format('H:i'),
            'message'             => "Conectado ao {$ecoponto->nome}.",
        ]);
    }

    // GET /ecopontos/{id}/sessao-ativa — quem está conectado agora (nome abreviado, sem CPF/e-mail)
    public function ativa(Ecoponto $ecoponto): JsonResponse
    {
        $this->authorize('gerenciar', $ecoponto);
        $sessao = $this->sessoes->ativaDoEcoponto($ecoponto);

        if (! $sessao) {
            throw new ApiException(404, 'sem_sessao', 'Nenhuma pessoa conectada.');
        }

        return response()->json([
            'sessao_id'  => $sessao->id,
            'pessoa'     => [
                'nome'   => Formato::nomeAbreviado($sessao->pessoa->nome),
                'avatar' => $sessao->pessoa->avatar_url,
            ],
            'iniciada_em' => Formato::iso($sessao->iniciada_em),
            'expira_em'   => Formato::iso($sessao->expira_em),
        ]);
    }

    // DELETE /ecopontos/{id}/sessao-ativa — a empresa encerra a sessão
    public function encerrar(Ecoponto $ecoponto): JsonResponse
    {
        $this->authorize('gerenciar', $ecoponto);
        $sessao = $this->sessoes->ativaDoEcoponto($ecoponto);

        if (! $sessao) {
            throw new ApiException(404, 'sem_sessao', 'Nenhuma pessoa conectada.');
        }
        $this->sessoes->encerrar($sessao);

        return response()->json(['message' => 'Sessão encerrada.']);
    }
}
