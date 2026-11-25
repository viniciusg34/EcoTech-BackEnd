<?php

namespace App\Clients;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/*
 * VIACEP: endereço a partir do CEP. A resposta fica em cache por 7 dias
 * (o mesmo CEP não é consultado de novo).
 */
class ViaCepClient
{
    // Devolve { cep, logradouro, bairro, cidade, uf } ou null se o CEP não existe
    public function buscar(string $cep): ?array
    {
        $chave = 'viacep:' . $cep;
        if (Cache::has($chave)) {
            return Cache::get($chave) ?: null;
        }

        try {
            $resposta = Http::timeout(5)->connectTimeout(5)->acceptJson()
                ->get(config('ecotech.viacep_url') . "/{$cep}/json/");
        } catch (Throwable $e) {
            throw new ApiException(503, 'cep_indisponivel', 'O serviço de CEP está fora do ar. Preencha o endereço manualmente.');
        }

        if ($resposta->status() === 400) {
            return null;
        }
        if (! $resposta->successful()) {
            throw new ApiException(503, 'cep_indisponivel', 'O serviço de CEP está fora do ar. Preencha o endereço manualmente.');
        }

        $dados = $resposta->json();
        $endereco = (! is_array($dados) || ! empty($dados['erro'])) ? null : [
            'cep'        => preg_replace('/\D/', '', (string) ($dados['cep'] ?? $cep)),
            'logradouro' => (string) ($dados['logradouro'] ?? ''),
            'bairro'     => (string) ($dados['bairro'] ?? ''),
            'cidade'     => (string) ($dados['localidade'] ?? ''),
            'uf'         => (string) ($dados['uf'] ?? ''),
        ];

        // Guarda até o "não encontrado" (false), para não repetir a consulta
        Cache::put($chave, $endereco ?? false, now()->addDays(7));

        return $endereco;
    }
}
