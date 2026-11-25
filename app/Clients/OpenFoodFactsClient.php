<?php

namespace App\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/*
 * OPEN FOOD FACTS: base pública com nome, marca e foto dos produtos pelo código de barras.
 * A consulta é feita no servidor (antes era no navegador), com tempo máximo de 4 s.
 * Devolve:
 *   ['encontrado' => true, 'nome' => ..., 'marca' => ..., 'imagem_url' => ...]
 *   ['encontrado' => false]   o Open Food Facts respondeu que não conhece o código
 *   null                      a API está fora do ar / demorou: a leitura continua sem esses dados
 */
class OpenFoodFactsClient
{
    public function buscar(string $codigoBarras): ?array
    {
        try {
            $resposta = Http::timeout(config('ecotech.off_timeout', 4))
                ->connectTimeout(config('ecotech.off_timeout', 4))
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'EcoTech/1.0 (projeto academico UNISUAM)'])
                ->get(config('ecotech.off_base_url') . '/api/v2/product/' . $codigoBarras . '.json', [
                    'fields' => 'product_name,product_name_pt,brands,image_front_small_url,image_url',
                ]);
        } catch (Throwable $e) {
            Log::warning('Open Food Facts indisponível: ' . $e->getMessage());
            return null;
        }

        if ($resposta->status() === 404 || ($resposta->successful() && (int) $resposta->json('status') === 0)) {
            return ['encontrado' => false];
        }
        if (! $resposta->successful() || (int) $resposta->json('status') !== 1) {
            return null;
        }

        $produto = (array) $resposta->json('product', []);
        $nome = trim((string) ($produto['product_name_pt'] ?? '')) ?: trim((string) ($produto['product_name'] ?? ''));

        return [
            'encontrado' => true,
            'nome'       => $nome !== '' ? mb_substr($nome, 0, 200) : null,
            'marca'      => isset($produto['brands']) ? mb_substr(trim((string) $produto['brands']), 0, 150) : null,
            'imagem_url' => $produto['image_front_small_url'] ?? $produto['image_url'] ?? null,
        ];
    }
}
