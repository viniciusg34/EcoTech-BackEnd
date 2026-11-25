<?php

namespace App\Console\Commands;

use App\Models\DesafioVerificacao;
use App\Models\TokenAcesso;
use App\Services\SessaoEcopontoService;
use Illuminate\Console\Command;

/*
 * php artisan ecotech:expirar
 * Roda a cada minuto (routes/console.php):
 *   - sessões de Ecoponto vencidas viram "expirada"
 *   - desafios (2FA/recuperação) vencidos e não resolvidos ficam bloqueados
 *   - tokens vencidos há mais de 1 dia são apagados
 */
class ExpirarVencidos extends Command
{
    protected $signature = 'ecotech:expirar';

    protected $description = 'Expira sessões de Ecoponto, desafios de verificação e tokens vencidos';

    public function handle(SessaoEcopontoService $sessoes): int
    {
        $s = $sessoes->expirarVencidas();

        $d = DesafioVerificacao::query()
            ->whereNull('resolvido_em')->whereNull('bloqueado_em')->where('expira_em', '<=', now())
            ->update(['bloqueado_em' => now()]);

        $t = TokenAcesso::query()->whereNotNull('expires_at')->where('expires_at', '<', now()->subDay())->delete();

        $this->info("Sessões expiradas: {$s} | desafios encerrados: {$d} | tokens apagados: {$t}");

        return self::SUCCESS;
    }
}
