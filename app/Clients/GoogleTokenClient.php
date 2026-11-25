<?php

namespace App\Clients;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;
use Throwable;

/*
 * LOGIN COM GOOGLE: confere no servidor o "ID token" (credential) que o botão do Google entrega ao front.
 * Usa o endpoint oficial tokeninfo do Google e confere:
 *   - aud (o token foi emitido para o NOSSO GOOGLE_CLIENT_ID)
 *   - iss (emitido pelo Google), exp (não venceu) e email_verified
 */
class GoogleTokenClient
{
    public function verificar(string $credential): array
    {
        $clientId = config('ecotech.google_client_id');
        if (! $clientId) {
            throw new ApiException(503, 'google_indisponivel', 'O login com Google não está configurado no servidor.');
        }

        try {
            $resposta = Http::timeout(5)->connectTimeout(5)->acceptJson()
                ->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $credential]);
        } catch (Throwable $e) {
            throw new ApiException(503, 'google_indisponivel', 'Não foi possível falar com o Google agora. Tente de novo.');
        }

        $dados = $resposta->successful() ? (array) $resposta->json() : [];

        $valido = ($dados['aud'] ?? null) === $clientId
            && in_array($dados['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
            && (int) ($dados['exp'] ?? 0) > time()
            && in_array($dados['email_verified'] ?? false, [true, 'true'], true)
            && ! empty($dados['email']);

        if (! $valido) {
            throw new ApiException(401, 'google_invalido', 'Não foi possível confirmar sua conta Google. Tente de novo.');
        }

        return [
            'email' => mb_strtolower((string) $dados['email']),
            'nome'  => (string) ($dados['name'] ?? ''),
            'sub'   => (string) ($dados['sub'] ?? ''),
        ];
    }
}
