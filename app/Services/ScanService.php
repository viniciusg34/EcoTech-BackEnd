<?php

namespace App\Services;

use App\Clients\OpenFoodFactsClient;
use App\Exceptions\ApiException;
use App\Models\Coleta;
use App\Models\ColetaItem;
use App\Models\Configuracao;
use App\Models\Ecoponto;
use App\Models\LeituraCodigo;
use App\Models\Pessoa;
use App\Models\ProdutoCatalogo;
use App\Models\SessaoEcoponto;
use App\Support\Formato;
use App\Support\Texto;
use Illuminate\Support\Facades\DB;

/*
 * LEITURA DE CÓDIGO DE BARRAS (feita pelo Ecoponto, ou pelo /scans de teste do master)
 * ---------------------------------------------------------------
 *  1. confere o dígito verificador (EAN-8, UPC-A ou EAN-13)          -> 422 se inválido
 *  2. acha o produto no catálogo; se nunca consultou, pergunta ao Open Food Facts
 *     (no servidor, máximo 4 s; se a API cair, segue sem ela) e guarda em cache
 *  3. confere se o produto participa da campanha                     -> 404 se não participa
 *  4. regra de duplicidade: o mesmo produto, pela mesma pessoa, em 24 h -> 409
 *  5. credita as moedas (CoinService) e soma o item na coleta da sessão
 * Toda leitura fica registrada em leitura_codigo, até as recusadas.
 */
class ScanService
{
    public function __construct(private CoinService $coins, private OpenFoodFactsClient $off)
    {
    }

    public function processar(Pessoa $pessoa, string $codigo, ?Ecoponto $ecoponto = null, ?SessaoEcoponto $sessao = null, ?string $chaveIdempotencia = null): array
    {
        $codigo = Texto::digitos($codigo) === trim($codigo) ? trim($codigo) : '';

        // 1. Código válido?
        if (! Texto::codigoBarrasValido($codigo)) {
            if (preg_match('/^\d{1,14}$/', $codigo)) {
                $this->registrar($pessoa, $codigo, null, 'invalido', 0, $ecoponto, $sessao, $chaveIdempotencia);
            }
            throw new ApiException(422, 'codigo_invalido', 'Código de barras inválido. Confira os números e leia de novo.');
        }

        // UPC-A (12 dígitos) vira EAN-13 com um zero na frente: é o mesmo produto
        if (strlen($codigo) === 12) {
            $codigo = '0' . $codigo;
        }

        // 2. Produto (catálogo + cache do Open Food Facts)
        $produto = $this->produto($codigo);

        // 3. Participa da campanha?
        if (! $produto || ! $produto->participa()) {
            $this->registrar($pessoa, $codigo, $produto, 'nao_participa', 0, $ecoponto, $sessao, $chaveIdempotencia);
            $nome = $produto ? ($produto->off_nome ?: $produto->nome) : null;
            throw new ApiException(404, 'produto_fora_da_campanha',
                ($nome && ! str_starts_with($nome, 'Produto ') ? "{$nome} não participa" : 'Este produto não participa') . ' da campanha EcoTech.',
                [], ['produto' => $produto ? $this->produtoResposta($produto) : null]);
        }

        $horas = Configuracao::inteiro('duplicidade_horas', 24);

        // 4 e 5. Duplicidade e crédito, com a pessoa travada (duas leituras juntas não passam as duas)
        [$situacao, $movimento] = DB::transaction(function () use ($pessoa, $codigo, $produto, $ecoponto, $sessao, $chaveIdempotencia, $horas) {
            Pessoa::query()->whereKey($pessoa->id)->lockForUpdate()->first();

            $jaLido = $horas > 0 && LeituraCodigo::query()
                ->where('pessoa_id', $pessoa->id)->where('codigo_barras', $codigo)->where('resultado', 'creditado')
                ->where('lido_em', '>=', now()->subHours($horas))
                ->exists();

            if ($jaLido) {
                $this->registrar($pessoa, $codigo, $produto, 'duplicado', 0, $ecoponto, $sessao, $chaveIdempotencia);
                return ['duplicado', null];
            }

            $leitura = $this->registrar($pessoa, $codigo, $produto, 'creditado', $produto->coins, $ecoponto, $sessao, $chaveIdempotencia);

            if ($ecoponto) {
                $this->somarNaColeta($pessoa, $ecoponto, $sessao, $produto);
            }

            $movimento = $this->coins->creditar(
                $pessoa->id, $produto->coins, 'leitura', $produto->nome,
                'leitura_codigo', $leitura->id, $ecoponto?->codigo ?? 'Leitura avulsa', $produto->icone ?? $produto->categoria?->icone
            );

            return ['creditado', $movimento];
        });

        if ($situacao === 'duplicado') {
            throw new ApiException(409, 'ja_escaneado', "Este produto já foi escaneado nas últimas {$horas} h.", [], [
                'produto' => $this->produtoResposta($produto),
            ]);
        }

        $primeiroNome = Formato::nomeAbreviado($pessoa->nome);

        return [
            'coinsAwarded'      => $produto->coins,
            'balance'           => $movimento->saldo_apos,
            'balance_formatado' => Formato::inteiro($movimento->saldo_apos),
            'message'           => Formato::frase("{$produto->nome}: +{$produto->coins} coins" . ($ecoponto ? " para {$primeiroNome}" : '')),
            'produto'           => $this->produtoResposta($produto),
        ];
    }

    // Busca o produto; consulta o Open Food Facts só se ainda não consultou este código
    private function produto(string $codigo): ?ProdutoCatalogo
    {
        $produto = ProdutoCatalogo::query()->with('categoria')->find($codigo);
        if ($produto && $produto->off_consultado_em) {
            return $produto;
        }

        $dados = $this->off->buscar($codigo);
        if ($dados === null) {
            return $produto; // API fora do ar: segue com o que tem
        }

        $cache = [
            'off_nome'          => $dados['nome'] ?? null,
            'off_marca'         => $dados['marca'] ?? null,
            'off_imagem_url'    => isset($dados['imagem_url']) ? mb_substr((string) $dados['imagem_url'], 0, 400) : null,
            'off_consultado_em' => now(),
        ];

        if ($produto) {
            $produto->update($cache);
            return $produto;
        }

        // Produto fora do catálogo: guarda em cache como "não participa"
        return ProdutoCatalogo::create($cache + [
            'codigo_barras' => $codigo,
            'nome'          => mb_substr($dados['nome'] ?? "Produto {$codigo}", 0, 150),
            'categoria_id'  => null,
            'coins'         => 0,
            'ativo'         => false,
        ]);
    }

    private function registrar(Pessoa $pessoa, string $codigo, ?ProdutoCatalogo $produto, string $resultado, int $coins, ?Ecoponto $ecoponto, ?SessaoEcoponto $sessao, ?string $chave): LeituraCodigo
    {
        // A chave de idempotência é única: se já foi usada, não repete
        if ($chave && LeituraCodigo::query()->where('idempotency_key', $chave)->exists()) {
            $chave = null;
        }

        return LeituraCodigo::create([
            'pessoa_id'        => $pessoa->id,
            'ecoponto_id'      => $ecoponto?->id,
            'sessao_id'        => $sessao?->id,
            'codigo_barras'    => substr($codigo, 0, 14),
            'produto_codigo'   => $produto?->codigo_barras,
            'resultado'        => $resultado,
            'coins_creditados' => $coins,
            'idempotency_key'  => $chave,
            'lido_em'          => now(),
        ]);
    }

    // A sessão tem UMA coleta do tipo "entrega" que vai juntando os itens lidos
    private function somarNaColeta(Pessoa $pessoa, Ecoponto $ecoponto, ?SessaoEcoponto $sessao, ProdutoCatalogo $produto): void
    {
        $coleta = ColetaService::coletaDaSessao($pessoa, $ecoponto, $sessao);
        $categoria = $produto->categoria;
        $peso = (float) ($produto->peso_kg ?? 0);

        ColetaItem::create([
            'coleta_id'      => $coleta->id,
            'categoria_id'   => $categoria->id,
            'produto_codigo' => $produto->codigo_barras,
            'quantidade'     => $categoria->unidade === 'kg' ? max(0.01, round($peso, 2)) : 1,
            'peso_kg'        => $peso,
            'coins'          => $produto->coins,
        ]);

        $coleta->total_coins += $produto->coins;
        $coleta->total_kg = round($coleta->total_kg + $peso, 2);
        $coleta->save();
    }

    public function produtoResposta(ProdutoCatalogo $produto): array
    {
        return [
            'codigo'    => $produto->codigo_barras,
            'nome'      => $produto->nome,
            'marca'     => $produto->off_marca,
            'imagem'    => $produto->off_imagem_url,
            'icone'     => $produto->icone ?? $produto->categoria?->icone,
            'categoria' => $produto->categoria?->chave,
            'categoria_rotulo' => $produto->categoria?->rotulo,
            'coins'     => $produto->coins,
            'participa' => $produto->participa(),
        ];
    }
}
