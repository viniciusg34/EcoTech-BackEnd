<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProdutoRequest;
use App\Http\Resources\ProdutoResource;
use App\Models\CategoriaMaterial;
use App\Models\ColetaItem;
use App\Models\LeituraCodigo;
use App\Models\ProdutoCatalogo;
use App\Support\Banco;
use App\Support\Paginacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// MASTER: CRUD dos produtos da campanha (código de barras -> material e moedas)
class ProdutoController extends Controller
{
    // GET /admin/produtos?busca=&participa=1
    public function index(Request $request): JsonResponse
    {
        $consulta = ProdutoCatalogo::query()->with('categoria')
            ->when($request->filled('busca'), function ($q) use ($request) {
                $termo = '%' . Banco::escaparLike(mb_strtolower((string) $request->query('busca'))) . '%';
                $q->where(fn ($w) => $w->whereRaw("LOWER(nome) LIKE ? ESCAPE '!'", [$termo])->orWhere('codigo_barras', 'like', $termo));
            })
            ->when($request->filled('participa'), fn ($q) => $request->boolean('participa')
                ? $q->where('ativo', true)->whereNotNull('categoria_id')->where('coins', '>', 0)
                : $q->where(fn ($w) => $w->where('ativo', false)->orWhereNull('categoria_id')->orWhere('coins', 0)))
            ->orderBy('nome');

        return Paginacao::resposta(Paginacao::paginar($consulta, $request, 50),
            fn ($p) => (new ProdutoResource($p))->resolve($request));
    }

    public function store(ProdutoRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $produto = ProdutoCatalogo::create([
            'codigo_barras' => $dados['codigo_barras'],
            'nome'          => $dados['nome'],
            'categoria_id'  => CategoriaMaterial::query()->where('chave', $dados['categoria'])->value('id'),
            'coins'         => $dados['coins'],
            'icone'         => $dados['icone'] ?? null,
            'peso_kg'       => $dados['peso_kg'] ?? null,
            'ativo'         => $dados['ativo'] ?? true,
        ]);

        return response()->json((new ProdutoResource($produto->load('categoria')))->resolve($request) + ['message' => 'Produto cadastrado.'], 201);
    }

    public function update(ProdutoRequest $request, ProdutoCatalogo $produto): JsonResponse
    {
        $dados = $request->validated();
        if (isset($dados['categoria'])) {
            $dados['categoria_id'] = CategoriaMaterial::query()->where('chave', $dados['categoria'])->value('id');
            unset($dados['categoria']);
        }
        $produto->update($dados);

        return response()->json((new ProdutoResource($produto->load('categoria')))->resolve($request) + ['message' => 'Produto atualizado.']);
    }

    // Se já foi lido alguma vez, só desativa (as leituras antigas apontam para ele)
    public function destroy(ProdutoCatalogo $produto): JsonResponse
    {
        $usado = LeituraCodigo::query()->where('produto_codigo', $produto->codigo_barras)->exists()
            || ColetaItem::query()->where('produto_codigo', $produto->codigo_barras)->exists();

        if ($usado) {
            $produto->update(['ativo' => false]);
            return response()->json(['message' => 'O produto já tinha leituras, então foi desativado (não apagado).']);
        }
        $produto->delete();
        return response()->json(['message' => 'Produto apagado.']);
    }
}
