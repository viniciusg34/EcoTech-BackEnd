<?php

namespace App\Clients;

use Illuminate\Support\Facades\Http;
use App\Support\LogSeguro as Log;
use Throwable;

/*
 * ENVIO DE E-MAIL pela API do Resend (POST https://api.resend.com/emails).
 * Usa HTTP (porta 443) e não SMTP, então funciona no Railway em qualquer plano.
 *
 * Sem RESEND_API_KEY (no seu computador, por exemplo) nada é enviado: o e-mail
 * vai para o log (storage/logs/laravel.log) para você conseguir testar.
 *
 * Devolve true se o Resend aceitou o e-mail, false se deu erro (o erro vai para o log).
 */
class EmailClient
{
    public function enviar(string $para, string $assunto, string $html, string $texto): bool
    {
        if (config('ecotech.brevo_key')) {
            return $this->brevo($para, $assunto, $html, $texto);
        }

        $chave = config('ecotech.resend_key');

        if (! $chave) {
            Log::warning("[e-mail NÃO enviado: falta BREVO_API_KEY ou RESEND_API_KEY] Para: {$para} | {$assunto}\n{$texto}");
            return true;
        }

        try {
            $resposta = Http::withToken($chave)->timeout(8)->connectTimeout(5)->acceptJson()
                ->post('https://api.resend.com/emails', [
                    'from'    => config('ecotech.email_remetente'),
                    'to'      => [$para],
                    'subject' => $assunto,
                    'html'    => $html,
                    'text'    => $texto,
                ]);
        } catch (Throwable $e) {
            Log::error('Resend: falha de conexão ao enviar e-mail.', ['para' => $para, 'erro' => $e->getMessage()]);
            return false;
        }

        if (! $resposta->successful()) {
            // O Resend explica o motivo no corpo (ex.: domínio não verificado)
            Log::error('Resend recusou o e-mail.', ['para' => $para, 'status' => $resposta->status(), 'resposta' => $resposta->json()]);
            return false;
        }

        return true;
    }

    /*
     * Brevo: POST https://api.brevo.com/v3/smtp/email (cabeçalho api-key).
     * EMAIL_REMETENTE precisa ser um remetente verificado no Brevo
     * (Senders, Domains & Dedicated IPs -> Senders), ex.: "EcoTech <seuemail@gmail.com>".
     */
    private function brevo(string $para, string $assunto, string $html, string $texto): bool
    {
        [$nome, $email] = $this->remetente();

        try {
            $resposta = Http::withHeaders(['api-key' => config('ecotech.brevo_key')])
                ->timeout(8)->connectTimeout(5)->acceptJson()
                ->post('https://api.brevo.com/v3/smtp/email', [
                    'sender'      => ['name' => $nome, 'email' => $email],
                    'to'          => [['email' => $para]],
                    'subject'     => $assunto,
                    'htmlContent' => $html,
                    'textContent' => $texto,
                ]);
        } catch (Throwable $e) {
            Log::error('Brevo: falha de conexão ao enviar e-mail.', ['para' => $para, 'erro' => $e->getMessage()]);
            return false;
        }

        if (! $resposta->successful()) {
            Log::error('Brevo recusou o e-mail.', ['para' => $para, 'status' => $resposta->status(), 'resposta' => $resposta->json()]);
            return false;
        }

        return true;
    }

    // "EcoTech <nao-responda@site.com>" -> ["EcoTech", "nao-responda@site.com"]
    private function remetente(): array
    {
        $texto = trim((string) config('ecotech.email_remetente'));
        if (preg_match('/^(.*)<([^>]+)>$/', $texto, $m)) {
            return [trim($m[1], " \"'") ?: 'EcoTech', trim($m[2])];
        }

        return ['EcoTech', $texto];
    }
}
