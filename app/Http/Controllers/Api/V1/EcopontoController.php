<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SalvarEcopontoRequest;
use App\Http\Resources\EcopontoResource;
use App\Models\CategoriaMaterial;
use App\Models\Ecoponto;
use App\Services\EcopontoQrService;
use App\Support\Banco;
use App\Support\Formato;
use App\Support\Paginacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/*
 * ECOPONTOS
 *   Público:  GET /ecopontos (mapa) e GET /ecopontos/{id}
 *   Empresa:  POST, PUT, PATCH (só os próprios), QR assinado e imagem do QR
 */
class EcopontoController extends Controller
{
    // GET /ecopontos?empresa=&categoria=&status=&busca=&pagina=&por_pagina= (até 100)
    public function index(Request $request): JsonResponse
    {
        $consulta = Ecoponto::query()
            ->with(['categorias', 'empresa'])->comKgColetado()
            ->when($request->filled('empresa'), fn ($q) => $q->where('empresa_id', (int) $request->query('empresa')))
            ->when($request->filled('categoria') && $request->query('categoria') !== 'todos', fn ($q) => $q->whereHas(
                'categorias', fn ($c) => $c->where('chave', $request->query('categoria'))
            ))
            ->when($request->filled('status'),
                fn ($q) => $q->where('status', $request->query('status')),
                fn ($q) => $q->where('status', '!=', 'inativo'))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $termo = '%' . Banco::escaparLike((string) $request->query('busca')) . '%';
                $q->where(fn ($w) => $w->whereRaw("nome LIKE ? ESCAPE '!'", [$termo])
                    ->orWhereRaw("codigo LIKE ? ESCAPE '!'", [$termo])
                    ->orWhereRaw("cidade LIKE ? ESCAPE '!'", [$termo]));
            })
            ->orderBy('codigo');

        return Paginacao::resposta(Paginacao::paginar($consulta, $request),
            fn ($e) => (new EcopontoResource($e))->resolve($request));
    }

    // GET /ecopontos/{id ou codigo}
    public function show(Request $request, Ecoponto $ecoponto): JsonResponse
    {
        $ecoponto = Ecoponto::query()->whereKey($ecoponto->id)->with(['categorias', 'empresa'])->comKgColetado()->first();
        return response()->json((new EcopontoResource($ecoponto))->resolve($request));
    }

    // GET /empresas/me/ecopontos — os Ecopontos da empresa logada (todas as situações)
    public function daEmpresa(Request $request): JsonResponse
    {
        $consulta = Ecoponto::query()->where('empresa_id', $request->user()->id)
            ->with(['categorias', 'empresa'])->comKgColetado()->orderBy('codigo');

        return Paginacao::resposta(Paginacao::paginar($consulta, $request, 50),
            fn ($e) => (new EcopontoResource($e))->resolve($request));
    }

    // POST /ecopontos (empresa)
    public function store(SalvarEcopontoRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $empresa = $request->user();

        $ecoponto = DB::transaction(function () use ($dados, $empresa) {
            $ecoponto = Ecoponto::create([
                'empresa_id' => $empresa->id,
                'codigo'     => $dados['codigo'] ?? $this->proximoCodigo(),
                'nome'       => $dados['nome'],
                'descricao'  => $dados['descricao'] ?? null,
                'endereco'   => $dados['endereco'] ?? null,
                'cidade'     => $dados['cidade'] ?? null,
                'estado'     => $dados['estado'] ?? null,
                'lat'        => $dados['lat'],
                'lng'        => $dados['lng'],
                'status'     => $dados['status'] ?? 'ativo',
                'segredo_qr' => bin2hex(random_bytes(32)),
            ]);
            $this->sincronizarCategorias($ecoponto, $dados['categorias']);
            return $ecoponto;
        });

        return response()->json($this->recurso($request, $ecoponto) + ['message' => 'Ecoponto criado.'], 201);
    }

    // PUT e PATCH /ecopontos/{id} (empresa dona)
    public function update(SalvarEcopontoRequest $request, Ecoponto $ecoponto): JsonResponse
    {
        $this->authorize('gerenciar', $ecoponto);
        $dados = $request->validated();

        DB::transaction(function () use ($ecoponto, $dados) {
            $campos = array_intersect_key($dados, array_flip(['codigo', 'nome', 'descricao', 'endereco', 'cidade', 'estado', 'lat', 'lng', 'status']));
            if (array_key_exists('codigo', $campos) && $campos['codigo'] === null) {
                unset($campos['codigo']);
            }
            $ecoponto->update($campos);
            if (array_key_exists('categorias', $dados)) {
                $this->sincronizarCategorias($ecoponto, $dados['categorias']);
            }
        });

        return response()->json($this->recurso($request, $ecoponto) + ['message' => 'Ecoponto atualizado.']);
    }

    // GET /ecopontos/{id}/qr  (?dinamico=1 -> QR que vale 60 segundos)
    public function qr(Request $request, Ecoponto $ecoponto, EcopontoQrService $qr): JsonResponse
    {
        $this->authorize('gerenciar', $ecoponto);
        $dinamico = $request->boolean('dinamico');
        $dados = $qr->payload($ecoponto, $dinamico);

        return response()->json([
            'ecoponto'  => ['id' => $ecoponto->id, 'codigo' => $ecoponto->codigo, 'nome' => $ecoponto->nome],
            'payload'   => $dados['payload'],
            'dinamico'  => $dinamico,
            'expira_em' => Formato::iso($dados['expira_em']),
            'svg_url'   => url("/api/v1/ecopontos/{$ecoponto->id}/qr.svg" . ($dinamico ? '?dinamico=1' : '')),
        ]);
    }

    // GET /ecopontos/{id}/qr.svg — imagem do QR gerada no servidor
    public function qrSvg(Request $request, Ecoponto $ecoponto, EcopontoQrService $qr): Response
    {
        $this->authorize('gerenciar', $ecoponto);
        $dados = $qr->payload($ecoponto, $request->boolean('dinamico'));
        $tamanho = max(120, min(1024, (int) $request->query('tamanho', 320)));

        return response($qr->svg($dados['payload'], $tamanho), 200, [
            'Content-Type'        => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="qr-' . $ecoponto->codigo . '.svg"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    private function sincronizarCategorias(Ecoponto $ecoponto, array $chaves): void
    {
        $ids = CategoriaMaterial::query()->whereIn('chave', $chaves)->pluck('id')->all();
        $ecoponto->categorias()->sync($ids);
    }

    // Próximo código livre: ECO-23, ECO-24...
    private function proximoCodigo(): string
    {
        $maior = Ecoponto::query()->where('codigo', 'like', 'ECO-%')->pluck('codigo')
            ->map(fn ($c) => (int) substr($c, 4))->max() ?? 0;
        do {
            $maior++;
            $codigo = 'ECO-' . str_pad((string) $maior, 2, '0', STR_PAD_LEFT);
        } while (Ecoponto::query()->where('codigo', $codigo)->exists());
        return $codigo;
    }

    private function recurso(Request $request, Ecoponto $ecoponto): array
    {
        $ecoponto = Ecoponto::query()->whereKey($ecoponto->id)->with(['categorias', 'empresa'])->comKgColetado()->first();
        return (new EcopontoResource($ecoponto))->resolve($request);
    }
}
