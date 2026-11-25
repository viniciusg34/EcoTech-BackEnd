<?php

namespace Tests\Feature;

use App\Models\LeituraCodigo;
use App\Models\MovimentoCoins;
use App\Models\ProdutoCatalogo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeituraTest extends TestCase
{
    public function test_ecoponto_le_o_produto_e_as_moedas_vao_para_a_pessoa(): void
    {
        [$pessoa, $empresa, $ecoponto, $sessaoId] = $this->sessaoAtiva($this->criarPessoa(['nome' => 'Ana Maria da Silva']));

        $this->comToken($empresa)->postJson("/api/v1/ecopontos/{$ecoponto->id}/leituras", ['sessao_id' => $sessaoId, 'code' => '7891234567895'])
            ->assertOk()
            ->assertJsonPath('coinsAwarded', 40)
            ->assertJsonPath('balance', 40)
            ->assertJsonPath('message', 'Lata de Alumínio 350ml: +40 coins para Ana M.')
            ->assertJsonPath('produto.categoria', 'metal');

        $this->assertSame(40, $pessoa->fresh()->saldo_coins);

        // O histórico da pessoa já mostra a leitura
        $this->comToken($pessoa)->getJson('/api/v1/me/movimentos')
            ->assertOk()
            ->assertJsonPath('data.0.tipo', 'leitura')
            ->assertJsonPath('data.0.titulo', 'Lata de Alumínio 350ml')
            ->assertJsonPath('data.0.subtitulo', $ecoponto->codigo)
            ->assertJsonPath('data.0.icone', '🥫')
            ->assertJsonPath('data.0.valor_formatado', '+40')
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/me/coletas')->assertJsonPath('data.0.tipo', 'entrega')->assertJsonPath('data.0.itens.0.categoria', 'metal');
    }

    public function test_idempotency_key_nao_credita_duas_vezes(): void
    {
        [$pessoa, $empresa, $ecoponto, $sessaoId] = $this->sessaoAtiva();
        $chave = (string) Str::uuid();
        $url = "/api/v1/ecopontos/{$ecoponto->id}/leituras";

        $primeira = $this->comToken($empresa)->withHeader('Idempotency-Key', $chave)
            ->postJson($url, ['sessao_id' => $sessaoId, 'code' => '7896004004778'])->assertOk();
        $segunda = $this->withHeader('Idempotency-Key', $chave)
            ->postJson($url, ['sessao_id' => $sessaoId, 'code' => '7896004004778'])->assertOk()->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($primeira->json(), $segunda->json());
        $this->assertSame(30, $pessoa->fresh()->saldo_coins);
        $this->assertSame(1, MovimentoCoins::query()->count());
        $this->assertSame(1, LeituraCodigo::query()->count());
    }

    public function test_mesmo_produto_em_24_horas_e_duplicado(): void
    {
        [$pessoa, $empresa, $ecoponto, $sessaoId] = $this->sessaoAtiva();
        $url = "/api/v1/ecopontos/{$ecoponto->id}/leituras";

        $this->comToken($empresa)->postJson($url, ['sessao_id' => $sessaoId, 'code' => '7891910000197'])->assertOk();
        $this->postJson($url, ['sessao_id' => $sessaoId, 'code' => '7891910000197'])
            ->assertStatus(409)->assertJsonPath('erro', 'ja_escaneado');

        $this->assertSame(80, $pessoa->fresh()->saldo_coins);
        $this->assertDatabaseHas('leitura_codigo', ['codigo_barras' => '7891910000197', 'resultado' => 'duplicado']);
    }

    public function test_codigo_com_digito_verificador_errado(): void
    {
        [, $empresa, $ecoponto, $sessaoId] = $this->sessaoAtiva();
        $this->comToken($empresa)->postJson("/api/v1/ecopontos/{$ecoponto->id}/leituras", ['sessao_id' => $sessaoId, 'code' => '7896004004777'])
            ->assertStatus(422)->assertJsonPath('erro', 'codigo_invalido');
        $this->assertDatabaseHas('leitura_codigo', ['codigo_barras' => '7896004004777', 'resultado' => 'invalido']);
    }

    public function test_produto_fora_da_campanha_consulta_o_open_food_facts_e_guarda_em_cache(): void
    {
        Http::fake(['world.openfoodfacts.org/*' => Http::response([
            'status' => 1, 'product' => ['product_name' => 'Biscoito Recheado', 'brands' => 'Marca X', 'image_front_small_url' => 'https://img/x.jpg'],
        ])]);
        [$pessoa, $empresa, $ecoponto, $sessaoId] = $this->sessaoAtiva();

        $this->comToken($empresa)->postJson("/api/v1/ecopontos/{$ecoponto->id}/leituras", ['sessao_id' => $sessaoId, 'code' => '7894900011517'])
            ->assertStatus(404)
            ->assertJsonPath('erro', 'produto_fora_da_campanha')
            ->assertJsonPath('message', 'Biscoito Recheado não participa da campanha EcoTech.');

        $produto = ProdutoCatalogo::query()->find('7894900011517');
        $this->assertFalse($produto->ativo);
        $this->assertSame('Marca X', $produto->off_marca);
        $this->assertNotNull($produto->off_consultado_em);

        // Segunda leitura usa o cache (não chama a API de novo)
        $this->postJson("/api/v1/ecopontos/{$ecoponto->id}/leituras", ['sessao_id' => $sessaoId, 'code' => '7894900011517'])->assertStatus(404);
        Http::assertSentCount(1);
        $this->assertSame(0, $pessoa->fresh()->saldo_coins);
    }

    public function test_open_food_facts_fora_do_ar_nao_quebra_a_leitura(): void
    {
        Http::fake(['world.openfoodfacts.org/*' => Http::response('erro', 500)]);
        [, $empresa, $ecoponto, $sessaoId] = $this->sessaoAtiva();

        // Produto da campanha continua funcionando
        $this->comToken($empresa)->postJson("/api/v1/ecopontos/{$ecoponto->id}/leituras", ['sessao_id' => $sessaoId, 'code' => '7896666000019'])
            ->assertOk()->assertJsonPath('coinsAwarded', 20);

        // Produto desconhecido: 404, sem erro 500
        $this->postJson("/api/v1/ecopontos/{$ecoponto->id}/leituras", ['sessao_id' => $sessaoId, 'code' => '7894900011517'])
            ->assertStatus(404);
    }

    public function test_empresa_nao_le_no_ecoponto_de_outra(): void
    {
        [, , $ecoponto, $sessaoId] = $this->sessaoAtiva();
        $this->comToken($this->criarEmpresa())->postJson("/api/v1/ecopontos/{$ecoponto->id}/leituras", ['sessao_id' => $sessaoId, 'code' => '7896004004778'])
            ->assertStatus(403);
    }

    public function test_scans_e_so_para_o_master_e_exige_idempotency_key(): void
    {
        $this->comToken($this->criarPessoa())->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/scans', ['code' => '7896004004778'])->assertStatus(403);

        $master = $this->criarMaster();
        $this->comToken($master)->postJson('/api/v1/scans', ['code' => '7896004004778'])
            ->assertStatus(422)->assertJsonStructure(['campos' => ['Idempotency-Key']]);

        $chave = (string) Str::uuid();
        $this->withHeader('Idempotency-Key', $chave)
            ->postJson('/api/v1/scans', ['code' => '7896004004778', 'scannedAt' => now()->toIso8601String()])
            ->assertOk()->assertJsonPath('coinsAwarded', 30);
        // Repetir com a mesma chave devolve o mesmo resultado sem creditar de novo
        $this->withHeader('Idempotency-Key', $chave)
            ->postJson('/api/v1/scans', ['code' => '7896004004778'])
            ->assertOk()->assertJsonPath('coinsAwarded', 30)->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame(30, $master->fresh()->saldo_coins);
    }

    public function test_entrega_pesada_registrada_pela_empresa(): void
    {
        [$pessoa, $empresa, , $sessaoId] = $this->sessaoAtiva();

        $this->comToken($empresa)->postJson('/api/v1/coletas', ['sessao_id' => $sessaoId, 'itens' => [
            ['categoria' => 'plastico', 'quantidade' => 2.5],
            ['categoria' => 'eletronicos', 'quantidade' => 2],
        ]])
            ->assertCreated()
            ->assertJsonPath('coinsAwarded', 80)          // 12 x 2,5 = 30  +  25 x 2 = 50
            ->assertJsonPath('coleta.total_kg', 2.5);

        $this->assertSame(80, $pessoa->fresh()->saldo_coins);

        $this->postJson('/api/v1/coletas', ['sessao_id' => $sessaoId, 'itens' => [['categoria' => 'vidro', 'quantidade' => 1]]])
            ->assertStatus(422)->assertJsonPath('erro', 'categoria_nao_aceita');
        $this->postJson('/api/v1/coletas', ['sessao_id' => $sessaoId, 'itens' => [['categoria' => 'eletronicos', 'quantidade' => 1.5]]])
            ->assertStatus(422);
    }
}
