<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\CategoriaMaterial;
use App\Models\Coleta;
use App\Models\ColetaItem;
use App\Models\Ecoponto;
use App\Models\Pessoa;
use App\Models\SessaoEcoponto;
use App\Support\Formato;
use Illuminate\Support\Facades\DB;

/*
 * ENTREGA DE MATERIAIS PESADOS NO ECOPONTO (POST /coletas)
 * A empresa dona do Ecoponto informa o que a pessoa da sessão entregou
 * (ex.: 2,5 kg de plástico). As moedas são calculadas aqui:
 *   moedas = coins_por_unidade da categoria x quantidade (arredondado para baixo)
 */
class ColetaService
{
    public function __construct(private CoinService $coins)
    {
    }

    // Coleta "entrega" da sessão (cria na primeira vez)
    public static function coletaDaSessao(Pessoa $pessoa, Ecoponto $ecoponto, ?SessaoEcoponto $sessao): Coleta
    {
        if ($sessao) {
            $coleta = Coleta::query()->where('sessao_id', $sessao->id)->where('tipo', 'entrega')->first();
            if ($coleta) {
                return $coleta;
            }
        }

        return Coleta::create([
            'pessoa_id'   => $pessoa->id,
            'ecoponto_id' => $ecoponto->id,
            'sessao_id'   => $sessao?->id,
            'tipo'        => 'entrega',
            'total_coins' => 0,
            'total_kg'    => 0,
        ]);
    }

    /*
     * $itens = [ ['categoria' => 'plastico', 'quantidade' => 2.5], ... ]
     */
    public function registrarEntrega(Ecoponto $ecoponto, SessaoEcoponto $sessao, array $itens): array
    {
        $aceitas = $ecoponto->categorias()->get()->keyBy('chave');
        $linhas = [];
        $erros = [];

        foreach ($itens as $i => $item) {
            $categoria = $aceitas->get($item['categoria']);
            if (! $categoria) {
                $existe = CategoriaMaterial::query()->where('chave', $item['categoria'])->exists();
                $erros["itens.{$i}.categoria"] = [$existe
                    ? "Este Ecoponto não recebe {$item['categoria']}."
                    : 'Categoria de material inexistente.'];
                continue;
            }
            $quantidade = (float) $item['quantidade'];
            if ($categoria->unidade === 'un' && floor($quantidade) != $quantidade) {
                $erros["itens.{$i}.quantidade"] = ["{$categoria->rotulo} é contado em unidades (número inteiro)."];
                continue;
            }
            $linhas[] = [
                'categoria'  => $categoria,
                'quantidade' => round($quantidade, 2),
                'peso_kg'    => $categoria->unidade === 'kg' ? round($quantidade, 3) : 0,
                'coins'      => (int) floor($categoria->coins_por_unidade * $quantidade),
            ];
        }

        if ($erros) {
            throw new ApiException(422, 'categoria_nao_aceita', collect($erros)->flatten()->first(), $erros);
        }

        $pessoa = $sessao->pessoa;

        return DB::transaction(function () use ($linhas, $pessoa, $ecoponto, $sessao) {
            Pessoa::query()->whereKey($pessoa->id)->lockForUpdate()->first();

            $coleta = self::coletaDaSessao($pessoa, $ecoponto, $sessao);
            $total = 0;
            $kg = 0.0;
            $resumo = [];

            foreach ($linhas as $linha) {
                ColetaItem::create([
                    'coleta_id'    => $coleta->id,
                    'categoria_id' => $linha['categoria']->id,
                    'quantidade'   => $linha['quantidade'],
                    'peso_kg'      => $linha['peso_kg'],
                    'coins'        => $linha['coins'],
                ]);
                $total += $linha['coins'];
                $kg += $linha['peso_kg'];
                $resumo[] = $linha['categoria']->unidade === 'kg'
                    ? Formato::kg($linha['quantidade']) . ' de ' . mb_strtolower($linha['categoria']->rotulo)
                    : Formato::inteiro($linha['quantidade']) . ' un. de ' . mb_strtolower($linha['categoria']->rotulo);
            }

            $coleta->total_coins += $total;
            $coleta->total_kg = round($coleta->total_kg + $kg, 2);
            $coleta->save();

            $saldo = $this->coins->saldo($pessoa->id);
            if ($total > 0) {
                $movimento = $this->coins->creditar(
                    $pessoa->id, $total, 'entrega', "Entrega no {$ecoponto->nome}", 'coleta', $coleta->id,
                    mb_substr(implode(' + ', $resumo), 0, 150), '♻️'
                );
                $saldo = $movimento->saldo_apos;
            }

            return [
                'coinsAwarded'      => $total,
                'balance'           => $saldo,
                'balance_formatado' => Formato::inteiro($saldo),
                'message'           => Formato::frase("Entrega registrada: +{$total} coins para " . Formato::nomeAbreviado($pessoa->nome)),
                'coleta'            => $coleta->fresh(['ecoponto', 'itens.categoria']),
            ];
        });
    }
}
