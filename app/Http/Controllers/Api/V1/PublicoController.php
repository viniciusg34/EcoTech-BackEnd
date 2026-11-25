<?php

namespace App\Http\Controllers\Api\V1;

use App\Clients\ViaCepClient;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoriaResource;
use App\Http\Resources\RecompensaResource;
use App\Models\CategoriaMaterial;
use App\Models\Configuracao;
use App\Models\Recompensa;
use App\Services\EstatisticaService;
use App\Support\Paginacao;
use App\Support\Texto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/*
 * Rotas públicas (não precisam de token).
 */
class PublicoController extends Controller
{
    /*
     * GET /saude — o front usa para saber se este ambiente (local ou servidor) está no ar.
     * Testa o banco de verdade: se o MySQL cair, responde 503 e o front troca de ambiente.
     * "modo" é sempre "real" ("demo" é só do servidor de demonstração do front).
     * Sem token e sem limite de requisições.
     */
    public function saude(): JsonResponse
    {
        try {
            DB::select('select 1');
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'status'  => 'erro',
                'banco'   => 'erro',
                'modo'    => 'real',
                'message' => 'Banco de dados indisponível.',
            ], 503);
        }

        return response()->json(['status' => 'ok', 'banco' => 'ok', 'modo' => 'real', 'versao' => '1.0.0']);
    }

    // GET /estatisticas — números do hero da página inicial
    public function estatisticas(EstatisticaService $estatisticas): JsonResponse
    {
        return response()->json($estatisticas->publicas());
    }

    // GET /categorias — materiais (rótulo, cor e moedas por kg/unidade)
    public function categorias(Request $request): JsonResponse
    {
        return Paginacao::resposta(Paginacao::paginar(CategoriaMaterial::query()->orderBy('id'), $request, 100),
            fn ($c) => (new CategoriaResource($c))->resolve($request));
    }

    // GET /config — configurações públicas (moedas do check-in, minutos da sessão...)
    public function config(): JsonResponse
    {
        $config = Configuracao::query()->where('publico', true)->orderBy('chave')->get()
            ->mapWithKeys(fn (Configuracao $c) => [$c->chave => is_numeric($c->valor) ? $c->valor + 0 : $c->valor]);

        return response()->json($config);
    }

    // GET /cep/{cep} — endereço pelo CEP (ViaCEP com cache)
    public function cep(string $cep, ViaCepClient $viaCep): JsonResponse
    {
        $cep = Texto::digitos($cep);
        if (strlen($cep) !== 8) {
            throw ApiException::validacao('cep', 'O CEP deve ter 8 dígitos.');
        }

        $endereco = $viaCep->buscar($cep);
        if (! $endereco) {
            throw new ApiException(404, 'cep_nao_encontrado', 'CEP não encontrado. Confira os números.');
        }

        return response()->json($endereco);
    }

    // GET /recompensas?cat=food — catálogo de recompensas (cat=todos ou sem cat = tudo)
    public function recompensas(Request $request): JsonResponse
    {
        $cat = (string) $request->query('cat', 'todos');
        $consulta = Recompensa::query()->where('ativa', true)
            ->when($cat !== '' && $cat !== 'todos', fn ($q) => $q->where('categoria', $cat))
            ->orderBy('custo_coins')->orderBy('id');

        return Paginacao::resposta(Paginacao::paginar($consulta, $request, 50),
            fn ($r) => (new RecompensaResource($r))->resolve($request));
    }
}
