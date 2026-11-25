<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalvarEcopontoRequest;
use App\Http\Resources\EcopontoResource;
use App\Models\CategoriaMaterial;
use App\Models\Ecoponto;
use App\Support\Banco;
use App\Support\Paginacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * MASTER: todos os Ecopontos (de todas as empresas)
 * ---------------------------------------------------------------
 * GET    /admin/ecopontos?busca=centro&empresa_id=1&status=ativo
 * PATCH  /admin/ecopontos/{id}   (edita: nome, endereço, status, materiais...)
 * DELETE /admin/ecopontos/{id}
 *
 * Excluir: se o Ecoponto já recebeu coletas, ele NÃO é apagado (o histórico
 * das pessoas e as toneladas do site dependem dele): vira "inativo" e some do mapa.
 * Sem nenhuma coleta, é apagado de verdade.
 */
class AdminEcopontoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $consulta = Ecoponto::query()->with(['categorias', 'empresa'])->comKgColetado()
            ->when($request->filled('busca'), function ($q) use ($request) {
                $termo = '%' . Banco::escaparLike(mb_strtolower(trim((string) $request->query('busca')))) . '%';
                $q->where(fn ($w) => $w->whereRaw("LOWER(nome) LIKE ? ESCAPE '!'", [$termo])
                    ->orWhereRaw("LOWER(codigo) LIKE ? ESCAPE '!'", [$termo])
                    ->orWhereRaw("LOWER(endereco) LIKE ? ESCAPE '!'", [$termo])
                    ->orWhereRaw("LOWER(cidade) LIKE ? ESCAPE '!'", [$termo]));
            })
            ->when($request->filled('empresa_id'), fn ($q) => $q->where('empresa_id', (int) $request->query('empresa_id')))
            ->when(in_array($request->query('status'), ['ativo', 'manutencao', 'inativo'], true),
                fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('codigo');

        return Paginacao::resposta(Paginacao::paginar($consulta, $request, 50),
            fn ($e) => (new EcopontoResource($e))->resolve($request));
    }

    // PATCH /admin/ecopontos/{id} — as mesmas regras de quando a empresa edita o próprio ponto
    public function update(SalvarEcopontoRequest $request, Ecoponto $ecoponto): JsonResponse
    {
        $dados = $request->validated();

        DB::transaction(function () use ($ecoponto, $dados) {
            $campos = array_intersect_key($dados, array_flip(['codigo', 'nome', 'descricao', 'endereco', 'cidade', 'estado', 'lat', 'lng', 'status']));
            if (array_key_exists('codigo', $campos) && $campos['codigo'] === null) {
                unset($campos['codigo']);
            }
            $ecoponto->update($campos);
            if (array_key_exists('categorias', $dados)) {
                $ids = CategoriaMaterial::query()->whereIn('chave', $dados['categorias'])->pluck('id')->all();
                $ecoponto->categorias()->sync($ids);
            }
        });
        Cache::forget('estatisticas_publicas');

        return response()->json($this->recurso($request, $ecoponto) + ['message' => 'Ecoponto atualizado.']);
    }

    // DELETE /admin/ecopontos/{id}
    public function destroy(Ecoponto $ecoponto): JsonResponse
    {
        $resultado = self::remover($ecoponto);
        Cache::forget('estatisticas_publicas');

        return response()->json($resultado);
    }

    /*
     * Apaga o Ecoponto, ou desativa se ele tiver histórico de coletas.
     * Devolve { acao: "excluido" | "desativado", message }.
     * (Também usado ao excluir uma empresa.)
     */
    public static function remover(Ecoponto $ecoponto): array
    {
        if ($ecoponto->coletas()->exists()) {
            $ecoponto->update(['status' => 'inativo']);
            $ecoponto->sessoes()->where('status', 'ativa')
                ->update(['status' => 'encerrada', 'encerrada_em' => now()]);

            return [
                'acao'    => 'desativado',
                'message' => "{$ecoponto->codigo} tem histórico de coletas: foi desativado (saiu do mapa) em vez de apagado.",
            ];
        }

        DB::transaction(function () use ($ecoponto) {
            $ecoponto->sessoes()->delete();   // conexões sem nenhuma coleta
            $ecoponto->categorias()->detach();
            $ecoponto->delete();              // leituras recusadas ficam, sem o Ecoponto
        });

        return ['acao' => 'excluido', 'message' => "{$ecoponto->codigo} excluído."];
    }

    private function recurso(Request $request, Ecoponto $ecoponto): array
    {
        $ecoponto = Ecoponto::query()->whereKey($ecoponto->id)->with(['categorias', 'empresa'])->comKgColetado()->first();
        return (new EcopontoResource($ecoponto))->resolve($request);
    }
}
