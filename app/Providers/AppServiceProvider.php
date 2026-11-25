<?php

namespace App\Providers;

use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\TokenAcesso;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Tokens do Sanctum ficam na tabela token_acesso
        Sanctum::usePersonalAccessTokenModel(TokenAcesso::class);

        // No banco, o tipo da conta fica como "pessoa" ou "empresa" (e não o nome da classe PHP)
        Relation::enforceMorphMap([
            'pessoa'  => Pessoa::class,
            'empresa' => Empresa::class,
        ]);

        // Um objeto sai direto no JSON (sem embrulhar em { "data": ... })
        JsonResource::withoutWrapping();

        $this->limitesDeTentativas();
    }

    /*
     * LIMITES DE TENTATIVAS (rate limiting). Ao passar do limite a API responde 429.
     */
    private function limitesDeTentativas(): void
    {
        $quem = fn (Request $r) => $r->user()
            ? $r->user()->getMorphClass() . ':' . $r->user()->getKey()
            : 'ip:' . $r->ip();

        // Geral: 120 pedidos por minuto por conta; sem login, 300 por IP
        // (numa sala de aula todo mundo sai pelo mesmo IP da faculdade)
        RateLimiter::for('api', fn (Request $r) => $r->user()
            ? Limit::perMinute(120)->by($quem($r))
            : Limit::perMinute(300)->by($quem($r)));

        // Login: 5 tentativas a cada 15 minutos por IP + conta
        RateLimiter::for('login', function (Request $r) {
            $conta = mb_strtolower(trim((string) ($r->input('email') ?? $r->input('login') ?? '')));
            return Limit::perMinutes(15, 5)->by('login:' . $r->ip() . '|' . $conta);
        });

        // 2FA e recuperação de senha: 10 a cada 15 minutos por IP + desafio/e-mail
        // (cada desafio ainda tem o próprio limite de 3 tentativas)
        RateLimiter::for('verificacao', function (Request $r) {
            $alvo = (string) ($r->input('desafio_id') ?? $r->input('email') ?? substr((string) $r->input('token'), 0, 16));
            return Limit::perMinutes(15, 10)->by('verif:' . $r->ip() . '|' . mb_strtolower($alvo));
        });

        // Cadastro: 60 por hora por IP
        RateLimiter::for('cadastro', fn (Request $r) => Limit::perHour(60)->by('cad:' . $r->ip()));

        // Saldo (o celular consulta a cada 5 s durante a sessão no Ecoponto): generoso
        RateLimiter::for('coins', fn (Request $r) => Limit::perMinute(60)->by('coins:' . $quem($r)));

        // Leituras de código de barras: por pessoa (sessão no Ecoponto), por minuto
        RateLimiter::for('leituras', fn (Request $r) => Limit::perMinute(30)->by(
            'leit:' . ($r->filled('sessao_id') ? 'sessao:' . (int) $r->input('sessao_id') : $quem($r))
        ));
    }
}
