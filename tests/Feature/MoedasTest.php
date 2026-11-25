<?php

namespace Tests\Feature;

use App\Models\MovimentoCoins;
use App\Models\Recompensa;
use App\Models\Resgate;
use App\Services\CoinService;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class MoedasTest extends TestCase
{
    public function test_resgate_debita_e_gera_cupom(): void
    {
        $pessoa = $this->criarPessoa();
        app(CoinService::class)->creditar($pessoa->id, 100, 'ajuste', 'Saldo inicial');
        $cafe = Recompensa::query()->where('nome', 'Café Grátis')->first();

        $this->comToken($pessoa)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/resgates', ['recompensa_id' => $cafe->id])
            ->assertCreated()
            ->assertJsonPath('balance', 20)
            ->assertJsonStructure(['message', 'codigo_cupom', 'resgate' => ['codigo_cupom', 'status']]);

        $this->assertMatchesRegularExpression('/^CUP-[A-Z0-9]{6}$/', Resgate::query()->first()->codigo_cupom);
        $this->getJson('/api/v1/me/movimentos')->assertJsonPath('data.0.tipo', 'resgate')->assertJsonPath('data.0.valor', -80)
            ->assertJsonPath('data.0.valor_formatado', '-80');
    }

    public function test_saldo_nunca_fica_negativo(): void
    {
        $pessoa = $this->criarPessoa();
        app(CoinService::class)->creditar($pessoa->id, 50, 'ajuste', 'Saldo inicial');
        $vale = Recompensa::query()->where('nome', 'Vale Compras R$30')->first();

        $this->comToken($pessoa)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/resgates', ['recompensa_id' => $vale->id])
            ->assertStatus(409)->assertJsonPath('erro', 'saldo_insuficiente')->assertJsonPath('message', 'Saldo insuficiente.');

        $this->assertSame(50, $pessoa->fresh()->saldo_coins);
        $this->assertSame(0, Resgate::query()->count());   // nada foi gravado
        $this->assertSame(1, MovimentoCoins::query()->count());
    }

    public function test_dois_resgates_juntos_com_saldo_para_um_so(): void
    {
        $pessoa = $this->criarPessoa();
        app(CoinService::class)->creditar($pessoa->id, 150, 'ajuste', 'Saldo inicial');
        $passe = Recompensa::query()->where('nome', 'Passe Metrô Rio')->first(); // 150

        $this->comToken($pessoa);
        $a = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/resgates', ['recompensa_id' => $passe->id]);
        $b = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/resgates', ['recompensa_id' => $passe->id]);

        $this->assertEqualsCanonicalizing([201, 409], [$a->status(), $b->status()]);
        $this->assertSame(0, $pessoa->fresh()->saldo_coins);
        $this->assertSame(1, Resgate::query()->count());
    }

    public function test_resgate_exige_idempotency_key_e_repetir_nao_debita_de_novo(): void
    {
        $pessoa = $this->criarPessoa();
        app(CoinService::class)->creditar($pessoa->id, 500, 'ajuste', 'Saldo inicial');
        $cafe = Recompensa::query()->where('nome', 'Café Grátis')->first();

        $this->comToken($pessoa)->postJson('/api/v1/resgates', ['recompensa_id' => $cafe->id])->assertStatus(422);

        $chave = (string) Str::uuid();
        $this->withHeader('Idempotency-Key', $chave)->postJson('/api/v1/resgates', ['recompensa_id' => $cafe->id])->assertCreated();
        $this->withHeader('Idempotency-Key', $chave)->postJson('/api/v1/resgates', ['recompensa_id' => $cafe->id])->assertCreated();

        $this->assertSame(420, $pessoa->fresh()->saldo_coins);
        $this->assertSame(1, Resgate::query()->count());
    }

    public function test_recompensa_sem_estoque_ou_inativa(): void
    {
        $pessoa = $this->criarPessoa();
        app(CoinService::class)->creditar($pessoa->id, 1000, 'ajuste', 'Saldo inicial');
        $cinema = Recompensa::query()->where('nome', 'Ingresso de Cinema')->first();
        $cinema->update(['estoque' => 1]);

        $this->comToken($pessoa);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/resgates', ['recompensa_id' => $cinema->id])->assertCreated();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/resgates', ['recompensa_id' => $cinema->id])
            ->assertStatus(409)->assertJsonPath('erro', 'sem_estoque');

        $cinema->update(['ativa' => false]);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/resgates', ['recompensa_id' => $cinema->id])->assertStatus(404);
    }

    public function test_livro_razao_e_imutavel_e_bate_com_o_saldo(): void
    {
        $pessoa = $this->criarPessoa();
        $coins = app(CoinService::class);
        $coins->creditar($pessoa->id, 300, 'ajuste', 'Saldo inicial');
        $coins->debitar($pessoa->id, 120, 'resgate', 'Teste');
        $coins->creditar($pessoa->id, 45, 'leitura', 'Teste');

        $this->assertSame(225, $pessoa->fresh()->saldo_coins);
        $this->assertSame(225, (int) MovimentoCoins::query()->where('pessoa_id', $pessoa->id)->sum('valor'));
        $this->assertSame(225, MovimentoCoins::query()->latest('id')->first()->saldo_apos);

        $movimento = MovimentoCoins::query()->first();
        $this->expectException(LogicException::class);
        $movimento->update(['valor' => 999999]);
    }

    public function test_saldo_e_consultado_pelo_me_coins(): void
    {
        $pessoa = $this->criarPessoa();
        app(CoinService::class)->creditar($pessoa->id, 1250, 'ajuste', 'Saldo inicial');
        $this->comToken($pessoa)->getJson('/api/v1/me/coins')->assertExactJson(['balance' => 1250, 'balance_formatado' => '1.250']);
    }
}
