<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AjusteRequest;
use App\Http\Resources\MovimentoResource;
use App\Http\Resources\PessoaAdminResource;
use App\Models\Pessoa;
use App\Services\CoinService;
use App\Services\PessoaService;
use App\Support\Banco;
use App\Support\Formato;
use App\Support\Paginacao;
use App\Support\PdfSimples;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/*
 * MASTER: consulta e exclusão (anonimização) de pessoas, lista em PDF e ajuste de saldo.
 */
class PessoaController extends Controller
{
    // GET /admin/pessoas?busca=ana  (só usuários comuns; busca por pedaço do nome)
    public function index(Request $request): JsonResponse
    {
        return Paginacao::resposta(Paginacao::paginar($this->consulta($request), $request),
            fn ($p) => (new PessoaAdminResource($p))->resolve($request));
    }

    // DELETE /admin/pessoas/{id} — anonimiza (LGPD): o histórico fica, os dados pessoais somem
    public function destroy(Pessoa $pessoa, PessoaService $pessoas): JsonResponse
    {
        if ($pessoa->ehMaster()) {
            throw ApiException::semPermissao('Não é possível excluir um usuário master.');
        }
        if ($pessoa->anonimizado_em) {
            throw new ApiException(409, 'ja_excluida', 'Esta pessoa já foi excluída.');
        }
        $pessoas->anonimizar($pessoa);

        return response()->json(['message' => 'Pessoa excluída (dados pessoais anonimizados).']);
    }

    // POST /admin/pessoas/{id}/ajustes  { valor, descricao } — corrige o saldo (fica no extrato como "ajuste")
    public function ajuste(AjusteRequest $request, Pessoa $pessoa, CoinService $coins): JsonResponse
    {
        $movimento = $coins->lancar(
            $pessoa->id, (int) $request->input('valor'), 'ajuste', $request->input('descricao'),
            'ajuste', $request->user()->id, 'Ajuste feito pelo administrador', '⚙️'
        );

        return response()->json([
            'message'   => 'Saldo ajustado.',
            'movimento' => (new MovimentoResource($movimento))->resolve($request),
        ] + $coins->saldoResposta($pessoa->id), 201);
    }

    // GET /admin/pessoas/pdf?busca= — desafio "plus" do enunciado: lista de usuários em PDF
    public function pdf(Request $request): Response
    {
        $pessoas = $this->consulta($request)->limit(2000)->get();
        $pdf = new PdfSimples();

        $colunas = [['Nome', 40, 34], ['CPF', 265, 16], ['E-mail', 360, 38], ['Login', 600, 8], ['Perfil', 655, 8], ['Moedas', 715, 10]];
        $cabecalho = function (float $y) use ($pdf, $colunas) {
            $pdf->faixa(35, $y - 5, PdfSimples::LARGURA - 70, 18);
            foreach ($colunas as [$titulo, $x]) {
                $pdf->texto($x, $y, $titulo, 10, true);
            }
        };

        $pdf->novaPagina();
        $pdf->texto(40, 550, 'EcoTech - Lista de Usuários', 16, true);
        $filtro = $request->filled('busca') ? ' - Filtro: "' . $request->query('busca') . '"' : '';
        $pdf->texto(40, 532, 'Gerado em ' . now()->format('d/m/Y H:i') . ' - Total: ' . $pessoas->count() . ' pessoa(s)' . $filtro . ' - CPF mascarado (LGPD)', 9);

        $y = 505;
        $cabecalho($y);
        $y -= 22;
        if ($pessoas->isEmpty()) {
            $pdf->texto(40, $y, 'Nenhuma pessoa encontrada.', 10);
        }

        foreach ($pessoas as $p) {
            if ($y < 50) {
                $pdf->novaPagina();
                $y = 550;
                $cabecalho($y);
                $y -= 22;
            }
            $valores = [$p->nome, Formato::cpfMascarado($p->cpf) ?? '-', $p->email, $p->login ?? '-', $p->perfil, Formato::inteiro($p->saldo_coins)];
            foreach ($colunas as $i => [, $x, $max]) {
                $texto = (string) $valores[$i];
                $pdf->texto($x, $y, mb_strlen($texto) > $max ? mb_substr($texto, 0, $max - 1) . '…' : $texto, 9);
            }
            $pdf->linha(35, $y - 5, PdfSimples::LARGURA - 35, $y - 5);
            $y -= 18;
        }

        return response($pdf->gerar(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="usuarios_ecotech_' . now()->format('Y-m-d') . '.pdf"',
        ]);
    }

    /*
     * Enunciado: a lista mostra só usuários COMUNS (o master não aparece), em ordem
     * alfabética, e a busca é por um pedaço do NOME.
     * ?perfil=master ou ?perfil=todos mostram os masters (uso interno, o front não precisa).
     */
    private function consulta(Request $request): Builder
    {
        $perfil = (string) $request->query('perfil', 'comum');

        return Pessoa::query()
            ->when($perfil !== 'todos', fn ($q) => $q->where('perfil', $perfil === 'master' ? 'master' : 'comum'))
            ->when(! $request->boolean('incluir_removidas'), fn ($q) => $q->whereNull('anonimizado_em'))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $termo = '%' . Banco::escaparLike(mb_strtolower(trim((string) $request->query('busca')))) . '%';
                $q->whereRaw("LOWER(nome) LIKE ? ESCAPE '!'", [$termo]);
            })
            ->orderBy('nome')->orderBy('id');
    }
}
