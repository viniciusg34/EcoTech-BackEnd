<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AlterarSenhaRequest;
use App\Http\Requests\AtualizarEmpresaRequest;
use App\Http\Requests\CadastrarEmpresaRequest;
use App\Exceptions\ApiException;
use App\Http\Resources\LeituraEmpresaResource;
use App\Models\Ecoponto;
use App\Models\LeituraCodigo;
use App\Services\EmpresaService;
use App\Services\EstatisticaService;
use App\Support\Formato;
use App\Support\Paginacao;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/*
 * EMPRESA PARCEIRA
 *   Público: POST /empresas (cadastro)
 *   Empresa: GET/PUT /empresas/me, PUT /empresas/me/senha, GET /empresas/me/dashboard,
 *            GET /empresas/me/leituras (histórico para o CSV)
 */
class EmpresaController extends Controller
{
    public function __construct(private EmpresaService $empresas)
    {
    }

    public function store(CadastrarEmpresaRequest $request): JsonResponse
    {
        $empresa = $this->empresas->cadastrar($request->validated());

        return response()->json([
            'message' => 'Empresa cadastrada! Faça login com o e-mail e a senha.',
            'empresa' => $this->empresas->dados($empresa),
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->empresas->dados($request->user()));
    }

    public function update(AtualizarEmpresaRequest $request): JsonResponse
    {
        $empresa = $this->empresas->atualizar($request->user(), $request->validated());
        return response()->json($this->empresas->dados($empresa) + ['message' => 'Dados da empresa atualizados.']);
    }

    public function alterarSenha(AlterarSenhaRequest $request): JsonResponse
    {
        $empresa = $request->user();
        if (! Hash::check($request->input('senha_atual'), $empresa->senha_hash)) {
            throw ApiException::validacao('senha_atual', 'A senha atual está incorreta.');
        }
        $empresa->forceFill(['senha_hash' => Hash::make($request->input('nova_senha'))])->save();
        $empresa->tokens()->whereKeyNot($empresa->currentAccessToken()->getKey())->delete();

        return response()->json(['message' => 'Senha alterada com sucesso.']);
    }

    // Painel: resumo, kg por mês (6 meses), kg por categoria e lista de Ecopontos
    public function dashboard(Request $request, EstatisticaService $estatisticas): JsonResponse
    {
        return response()->json($estatisticas->painelEmpresa($request->user()));
    }

    /*
     * GET /empresas/me/leituras?periodo=mes|3m|6m|tudo&ecoponto_id=3&pagina=1&por_pagina=100
     * Histórico de leituras dos Ecopontos da empresa (o front gera o CSV com isto).
     * Inclui as leituras recusadas. Do mais recente para o mais antigo. Sem dado pessoal (LGPD).
     */
    public function leituras(Request $request): JsonResponse
    {
        $empresa = $request->user();

        // 1. Período: o servidor calcula as datas (o front não faz conta)
        $periodo = (string) ($request->query('periodo') ?: 'mes');
        if (! array_key_exists($periodo, self::PERIODOS)) {
            throw ApiException::validacao('periodo', 'Período inválido. Use mes, 3m, 6m ou tudo.');
        }
        $desde = $this->inicioDoPeriodo($periodo);

        // 2. Ecoponto (opcional): só da própria empresa
        $ecopontos = Ecoponto::query()->where('empresa_id', $empresa->id);
        if ($request->filled('ecoponto_id')) {
            $id = $request->query('ecoponto_id');
            if (! ctype_digit((string) $id)) {
                throw ApiException::validacao('ecoponto_id', 'Ecoponto inválido.');
            }
            $ecoponto = Ecoponto::query()->find((int) $id);
            if (! $ecoponto) {
                throw ApiException::naoEncontrado('Ecoponto não encontrado.');
            }
            if ($ecoponto->empresa_id !== $empresa->id) {
                throw ApiException::semPermissao('Este Ecoponto não é da sua empresa.');
            }
            $ecopontos->whereKey($ecoponto->id);
        }

        // 3. Consulta
        $consulta = LeituraCodigo::query()
            ->with(['ecoponto', 'produto.categoria'])
            ->whereIn('ecoponto_id', $ecopontos->select('id'))
            ->when($desde, fn ($q) => $q->where('lido_em', '>=', $desde))
            ->orderByDesc('lido_em')->orderByDesc('id');

        return Paginacao::resposta(
            Paginacao::paginar($consulta, $request, 100),
            fn ($l) => (new LeituraEmpresaResource($l))->resolve($request),
            [
                'periodo'        => $periodo,
                'periodo_rotulo' => self::PERIODOS[$periodo],
                'desde'          => Formato::iso($desde),
            ]
        );
    }

    private const PERIODOS = [
        'mes'  => 'Este mês',
        '3m'   => 'Últimos 3 meses',
        '6m'   => 'Últimos 6 meses',
        'tudo' => 'Todo o período',
    ];

    // mes = desde o dia 1º deste mês (horário de Brasília); 3m e 6m = últimos 3 e 6 meses; tudo = sem limite
    private function inicioDoPeriodo(string $periodo): ?Carbon
    {
        return match ($periodo) {
            'mes'   => EstatisticaService::inicioDoMes(),
            '3m'    => Carbon::now()->subMonths(3),
            '6m'    => Carbon::now()->subMonths(6),
            default => null,
        };
    }
}
