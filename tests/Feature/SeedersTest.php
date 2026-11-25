<?php

namespace Tests\Feature;

use App\Models\Coleta;
use App\Models\Ecoponto;
use App\Models\Empresa;
use App\Models\MovimentoCoins;
use App\Models\Pessoa;
use App\Models\Recompensa;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    public function test_seed_principal_pode_rodar_duas_vezes_sem_duplicar(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(22, Ecoponto::query()->count());
        $this->assertSame(3, Empresa::query()->count());
        $this->assertSame(11, Recompensa::query()->count());
        $this->assertSame(1, Pessoa::query()->where('perfil', 'master')->count());
        $this->assertSame('master', Pessoa::query()->where('perfil', 'master')->value('login'));
        // O login é guardado em minúsculas; digitar MASTER também funciona
        $this->postJson('/api/v1/auth/login', ['login' => 'MASTER', 'senha' => 'Master123'])->assertOk()->assertJsonPath('status', '2fa');
    }

    public function test_dados_de_demonstracao_com_livro_razao_consistente(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $demo = Pessoa::query()->where('email', 'like', '%@demo.ecotech.example')->get();
        $this->assertCount(15, $demo);   // 3 para entrar no site + 12 moradores (rodar 2 vezes não duplica)
        // volume suficiente para o site mostrar toneladas de verdade
        $this->assertGreaterThan(1, $this->getJson('/api/v1/estatisticas')->json('toneladas'));
        foreach ($demo as $pessoa) {
            $this->assertSame($pessoa->saldo_coins, (int) MovimentoCoins::query()->where('pessoa_id', $pessoa->id)->sum('valor'));
        }
        $this->assertTrue(Coleta::query()->where('criado_em', '<', now()->subMonths(4)->startOfMonth())->exists());
        $this->assertFalse(Coleta::query()->where('criado_em', '>', now())->exists());

        // A pessoa de demonstração entra com a senha padrão e a pergunta de segurança
        $this->postJson('/api/v1/auth/login', ['login' => 'anasil', 'senha' => 'Reciclar1'])->assertOk()->assertJsonPath('status', '2fa');
    }
}
