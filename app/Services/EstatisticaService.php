<?php

namespace App\Services;

use App\Models\CategoriaMaterial;
use App\Models\Coleta;
use App\Models\Ecoponto;
use App\Models\Empresa;
use App\Models\LeituraCodigo;
use App\Models\Pessoa;
use App\Models\Resgate;
use App\Models\SessaoEcoponto;
use App\Support\Banco;
use App\Support\Formato;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * NÚMEROS E GRÁFICOS (página inicial, painel da empresa e painel do master).
 * Tudo vem das coletas reais. Os gráficos já saem prontos: { labels: [...], valores: [...] }.
 */
class EstatisticaService
{
    private const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    // GET /estatisticas (hero da página inicial). Guardado em cache por 5 minutos.
    public function publicas(): array
    {
        return Cache::remember('estatisticas_publicas', now()->addMinutes(5), function () {
            $kg = (float) Coleta::query()->sum('total_kg');
            $toneladas = round($kg / 1000, 1);
            $membros = Pessoa::query()->where('perfil', 'comum')->where('ativo', true)->count();
            $ecopontos = Ecoponto::query()->where('status', '!=', 'inativo')->count();
            $empresas = Empresa::query()->where('status', 'ativa')->count();

            return [
                'toneladas'           => $toneladas,
                'toneladas_formatado' => number_format($toneladas, 1, ',', '.'),
                'kg'                  => round($kg, 2),
                'kg_formatado'        => Formato::kg($kg),
                'membros'             => $membros,
                'membros_formatado'   => Formato::inteiro($membros),
                'ecopontos'           => $ecopontos,
                'empresas'            => $empresas,
            ];
        });
    }

    // kg por mês nos últimos 6 meses: { labels: ["jun/26", ...], valores: [12.5, ...] }
    public function serieMensal(?int $empresaId = null, int $meses = 6): array
    {
        $inicio = Carbon::now()->startOfMonth()->subMonths($meses - 1);
        $expressao = Banco::anoMes('coleta.criado_em');

        $porMes = Coleta::query()
            ->when($empresaId, fn ($q) => $q->join('ecoponto', 'ecoponto.id', '=', 'coleta.ecoponto_id')
                ->where('ecoponto.empresa_id', $empresaId))
            ->where('coleta.criado_em', '>=', $inicio)
            ->selectRaw("{$expressao} as mes, SUM(coleta.total_kg) as kg")
            ->groupBy(DB::raw($expressao))
            ->pluck('kg', 'mes');

        $labels = [];
        $valores = [];
        for ($i = 0; $i < $meses; $i++) {
            $mes = $inicio->copy()->addMonths($i);
            $labels[] = self::MESES[$mes->month - 1] . '/' . $mes->format('y');
            $valores[] = round((float) ($porMes[$mes->format('Y-m')] ?? 0), 2);
        }

        return ['labels' => $labels, 'valores' => $valores, 'unidade' => 'kg'];
    }

    // kg por categoria, com o percentual já calculado (inteiros que somam 100)
    public function porCategoria(?int $empresaId = null): array
    {
        $kgPorCategoria = DB::table('coleta_item')
            ->join('coleta', 'coleta.id', '=', 'coleta_item.coleta_id')
            ->when($empresaId, fn ($q) => $q->join('ecoponto', 'ecoponto.id', '=', 'coleta.ecoponto_id')
                ->where('ecoponto.empresa_id', $empresaId))
            ->groupBy('coleta_item.categoria_id')
            ->selectRaw('coleta_item.categoria_id, SUM(coleta_item.peso_kg) as kg, SUM(coleta_item.quantidade) as quantidade')
            ->get()->keyBy('categoria_id');

        $categorias = CategoriaMaterial::query()->orderBy('id')->get();
        $kgs = $categorias->mapWithKeys(fn (CategoriaMaterial $c) => [$c->id => round((float) ($kgPorCategoria[$c->id]->kg ?? 0), 2)]);
        $percentuais = self::percentuaisInteiros($kgs->all());

        return $categorias
            ->map(function (CategoriaMaterial $c) use ($kgs, $percentuais) {
                $kg = $kgs[$c->id];
                return [
                    'chave'                => $c->chave,
                    'rotulo'               => $c->rotulo,
                    'cor_hex'              => $c->cor_hex,
                    'kg'                   => $kg,
                    'kg_formatado'         => Formato::kg($kg),
                    'percentual'           => $percentuais[$c->id],
                    'percentual_formatado' => $percentuais[$c->id] . '%',
                ];
            })
            ->sortByDesc('kg')->values()->all();
    }

    /*
     * Transforma valores em percentuais INTEIROS que somam exatamente 100
     * (método do maior resto: arredonda para baixo e dá +1 para quem tem a maior sobra).
     * Ex.: [1, 1, 1] -> [34, 33, 33]. Tudo zero -> tudo 0.
     */
    public static function percentuaisInteiros(array $valores): array
    {
        $total = array_sum($valores);
        if ($total <= 0) {
            return array_map(fn () => 0, $valores);
        }

        $base = [];
        $sobras = [];
        foreach ($valores as $chave => $valor) {
            $exato = $valor / $total * 100;
            $base[$chave] = (int) floor($exato);
            $sobras[$chave] = $exato - $base[$chave];
        }

        arsort($sobras);
        $faltam = 100 - array_sum($base);
        foreach (array_keys($sobras) as $chave) {
            if ($faltam-- <= 0) {
                break;
            }
            $base[$chave]++;
        }

        return $base;
    }

    // Lista de Ecopontos com kg, kg do mês, última atividade e situação (painéis da empresa e do master)
    public function ecopontos(?int $empresaId = null): array
    {
        $comSessao = SessaoEcoponto::query()->validas()->pluck('ecoponto_id')->flip();
        $inicioDoMes = self::inicioDoMes();

        return Ecoponto::query()
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->comKgColetado()
            ->withSum(['coletas as kg_mes' => fn ($q) => $q->where('criado_em', '>=', $inicioDoMes)], 'total_kg')
            ->with('categorias')
            ->orderBy('codigo')
            ->get()
            ->map(function (Ecoponto $e) use ($comSessao) {
                $ultima = $e->ultima_atividade ? Carbon::parse($e->ultima_atividade) : null;
                $status = \App\Http\Resources\EcopontoResource::STATUS[$e->status];
                return [
                    'id'                         => $e->id,
                    'codigo'                     => $e->codigo,
                    'nome'                       => $e->nome,
                    'endereco'                   => implode(', ', array_filter([$e->endereco, $e->cidade])) ?: null,
                    'status'                     => $e->status,
                    'status_rotulo'              => $status['rotulo'],
                    'status_cor'                 => $status['cor'],
                    'categorias'                 => $e->categorias->map(fn ($c) => [
                        'chave' => $c->chave, 'rotulo' => $c->rotulo, 'cor_hex' => $c->cor_hex,
                    ])->values()->all(),
                    'kg_coletado'                => round((float) $e->kg_coletado, 2),
                    'kg_coletado_formatado'      => Formato::kg($e->kg_coletado),
                    'kg_mes'                     => round((float) $e->kg_mes, 2),
                    'kg_mes_formatado'           => Formato::kg($e->kg_mes),
                    'ultima_atividade'           => Formato::quando($ultima),     // "Hoje, 08h30", "Ontem, 16h40", "Há 2 dias"
                    'ultima_atividade_iso'       => Formato::iso($ultima),
                    'ultima_atividade_formatada' => $ultima ? Formato::dataHora($ultima) : 'Sem atividade ainda',
                    'sessao_ativa'               => $comSessao->has($e->id),
                ];
            })->all();
    }

    // 1º dia do mês corrente, 00:00 no horário de Brasília
    public static function inicioDoMes(): Carbon
    {
        return Carbon::now('America/Sao_Paulo')->startOfMonth()->setTimezone(config('app.timezone'));
    }

    public function resumo(?int $empresaId = null): array
    {
        $coletas = Coleta::query()->when($empresaId, fn ($q) => $q->whereIn(
            'ecoponto_id', Ecoponto::query()->where('empresa_id', $empresaId)->select('id')
        ));

        $kg = round((float) (clone $coletas)->sum('total_kg'), 2);
        $coins = (int) (clone $coletas)->sum('total_coins');

        return [
            'kg_total'                     => $kg,
            'kg_total_formatado'           => Formato::kg($kg),
            'coletas'                      => (clone $coletas)->count(),
            'pessoas_atendidas'            => (clone $coletas)->distinct()->count('pessoa_id'),
            'coins_distribuidos'           => $coins,
            'coins_distribuidos_formatado' => Formato::inteiro($coins),
            'ecopontos_ativos'             => Ecoponto::query()->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
                ->where('status', 'ativo')->count(),
        ];
    }

    /*
     * Painel da empresa (GET /empresas/me/dashboard).
     * "totais", "serie", "categorias" e "ecopontos" seguem o formato pedido pelo front (ajustes, item 7.1).
     * "resumo" e "serie_mensal" continuam vindo, para quem já usava.
     */
    public function painelEmpresa(Empresa $empresa): array
    {
        $serie = $this->serieMensal($empresa->id);
        $ecopontos = $this->ecopontos($empresa->id);

        return [
            'empresa'      => ['id' => $empresa->id, 'nome' => $empresa->nome_empresa],
            'totais'       => $this->totaisEmpresa($empresa->id, $ecopontos),
            'serie'        => $this->serieParaGrafico($serie),
            'categorias'   => $this->porCategoria($empresa->id),
            'ecopontos'    => $ecopontos,
            'resumo'       => $this->resumo($empresa->id),
            'serie_mensal' => $serie,
        ];
    }

    // Cartões do topo do painel: ecopontos, kg, leituras validadas, ativos e variação do mês
    private function totaisEmpresa(int $empresaId, array $ecopontos): array
    {
        $kgTotal = array_sum(array_column($ecopontos, 'kg_coletado'));
        $ids = array_column($ecopontos, 'id');
        $validacoes = $ids
            ? LeituraCodigo::query()->whereIn('ecoponto_id', $ids)->where('resultado', 'creditado')->count()
            : 0;

        return [
            'ecopontos'            => count($ecopontos),
            'ativos'               => count(array_filter($ecopontos, fn ($e) => $e['status'] === 'ativo')),
            'kg'                   => round($kgTotal, 2),
            'kg_formatado'         => Formato::numero($kgTotal),
            'validacoes'           => $validacoes,
            'validacoes_formatado' => Formato::inteiro($validacoes),
            'variacao_kg_texto'    => $this->variacaoDoMes($empresaId),
        ];
    }

    // "↑ 12% vs mês anterior" (kg deste mês comparado com o mês passado)
    private function variacaoDoMes(int $empresaId): string
    {
        $inicioDoMes = self::inicioDoMes();
        $kgEntre = fn (Carbon $de, ?Carbon $ate) => (float) Coleta::query()
            ->whereIn('ecoponto_id', Ecoponto::query()->where('empresa_id', $empresaId)->select('id'))
            ->where('criado_em', '>=', $de)
            ->when($ate, fn ($q) => $q->where('criado_em', '<', $ate))
            ->sum('total_kg');

        $atual = $kgEntre($inicioDoMes, null);
        $anterior = $kgEntre($inicioDoMes->copy()->subMonth(), $inicioDoMes);

        if ($anterior <= 0) {
            return $atual > 0 ? 'Primeiro mês com coletas' : 'Sem coletas neste mês';
        }

        $variacao = (int) round(($atual - $anterior) / $anterior * 100);

        return match (true) {
            $variacao > 0 => "↑ {$variacao}% vs mês anterior",
            $variacao < 0 => '↓ ' . abs($variacao) . '% vs mês anterior',
            default       => 'Igual ao mês anterior',
        };
    }

    // Gráfico de barras: rótulos curtos ("Jun"), valores, valores formatados e o total
    private function serieParaGrafico(array $serie): array
    {
        return [
            'labels'             => array_map(fn ($l) => ucfirst(explode('/', $l)[0]), $serie['labels']),
            'valores'            => $serie['valores'],
            'valores_formatados' => array_map(fn ($v) => Formato::numero($v), $serie['valores']),
            'total_formatado'    => 'Total: ' . Formato::kg(array_sum($serie['valores'])),
            'unidade'            => 'kg',
        ];
    }

    public function painelMaster(): array
    {
        $resumo = $this->resumo();
        $resumo['pessoas'] = Pessoa::query()->where('perfil', 'comum')->where('ativo', true)->count();
        $resumo['empresas'] = Empresa::query()->count();
        $resumo['resgates'] = Resgate::query()->count();

        return [
            'resumo'       => $resumo,
            'serie_mensal' => $this->serieMensal(),
            'categorias'   => $this->porCategoria(),
            'ecopontos'    => $this->ecopontos(),
        ];
    }
}
