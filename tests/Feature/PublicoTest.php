<?php

namespace Tests\Feature;

use App\Services\CoinService;
use Database\Seeders\EmpresaEcopontoSeeder;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicoTest extends TestCase
{
    public function test_mapa_com_22_ecopontos_paginados_e_filtro_por_categoria(): void
    {
        $this->seed(EmpresaEcopontoSeeder::class);

        $this->getJson('/api/v1/ecopontos?pagina=1&por_pagina=100')
            ->assertOk()
            ->assertJsonPath('meta.total', 22)
            ->assertJsonPath('meta.por_pagina', 100)
            ->assertJsonCount(22, 'data')
            ->assertJsonStructure(['data' => [['id', 'codigo', 'nome', 'descricao', 'endereco', 'cidade', 'estado', 'lat', 'lng',
                'status', 'status_rotulo', 'categorias' => [['chave', 'rotulo', 'cor_hex']], 'kg_coletado_formatado']], 'links']);

        $this->getJson('/api/v1/ecopontos?por_pagina=10')->assertJsonPath('meta.ultima_pagina', 3)->assertJsonCount(10, 'data');
        $this->getJson('/api/v1/ecopontos?por_pagina=500')->assertJsonPath('meta.por_pagina', 100);

        $eletronicos = $this->getJson('/api/v1/ecopontos?categoria=eletronicos&por_pagina=100')->json('data');
        $this->assertCount(4, $eletronicos); // ECO-08, ECO-11, ECO-13 e ECO-16

        $this->getJson('/api/v1/ecopontos/ECO-08')->assertOk()->assertJsonPath('nome', 'TechRecicla Copacabana')->assertJsonMissingPath('segredo_qr');
        $this->getJson('/api/v1/ecopontos/9999')->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
    }

    public function test_estatisticas_categorias_recompensas_e_config(): void
    {
        $this->criarPessoa();
        $this->getJson('/api/v1/estatisticas')->assertOk()->assertJsonStructure(['toneladas', 'kg', 'kg_formatado', 'membros', 'ecopontos', 'empresas'])->assertJsonPath('membros', 1);
        $this->getJson('/api/v1/categorias')->assertOk()->assertJsonCount(6, 'data')->assertJsonPath('data.0.chave', 'metal')->assertJsonPath('data.0.cor_hex', '#f9a825');
        $this->getJson('/api/v1/recompensas?cat=food')->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.categoria', 'food');
        $this->getJson('/api/v1/recompensas?cat=todos&por_pagina=100')->assertJsonPath('meta.total', 11)
            ->assertJsonStructure(['data' => [['id', 'categoria', 'icone', 'nome', 'descricao', 'custo_coins']]]);
        $this->getJson('/api/v1/config')->assertOk()->assertJsonPath('checkin_coins', 50)->assertJsonPath('sessao_minutos', 10);
    }

    public function test_cep_pelo_viacep_com_cache(): void
    {
        Http::fake([
            'viacep.com.br/ws/20040020/*' => Http::response(['cep' => '20040-020', 'logradouro' => 'Rua da Assembleia', 'bairro' => 'Centro', 'localidade' => 'Rio de Janeiro', 'uf' => 'RJ']),
            'viacep.com.br/ws/99999999/*' => Http::response(['erro' => 'true']),
        ]);

        $this->getJson('/api/v1/cep/20040-020')->assertOk()->assertExactJson([
            'cep' => '20040020', 'logradouro' => 'Rua da Assembleia', 'bairro' => 'Centro', 'cidade' => 'Rio de Janeiro', 'uf' => 'RJ',
        ]);
        $this->getJson('/api/v1/cep/20040020')->assertOk();
        Http::assertSentCount(1);

        $this->getJson('/api/v1/cep/99999999')->assertStatus(404)->assertJsonPath('erro', 'cep_nao_encontrado');
        $this->getJson('/api/v1/cep/123')->assertStatus(422);
    }

    public function test_cors_e_erros_em_json(): void
    {
        $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], [
            'HTTP_ORIGIN' => 'http://127.0.0.1:5500', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type,idempotency-key',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://127.0.0.1:5500');

        $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], [
            'HTTP_ORIGIN' => 'https://eco-tech-blush.vercel.app', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ])->assertHeader('Access-Control-Allow-Origin', 'https://eco-tech-blush.vercel.app');

        $this->getJson('/api/v1/nao-existe')->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
        $this->deleteJson('/api/v1/estatisticas')->assertStatus(405)->assertJsonPath('erro', 'metodo_nao_permitido');
    }

    public function test_saude_responde_ok_com_o_banco_no_ar_e_sem_limite(): void
    {
        $this->getJson('/api/v1/saude')->assertOk()
            ->assertExactJson(['status' => 'ok', 'banco' => 'ok', 'modo' => 'real', 'versao' => '1.0.0']);

        // o front consulta /saude o tempo todo: não pode cair no limite (300 por minuto sem login)
        for ($i = 0; $i < 310; $i++) {
            $this->getJson('/api/v1/saude')->assertOk();
        }
    }

    public function test_saude_responde_503_quando_o_banco_cai(): void
    {
        \Illuminate\Support\Facades\DB::shouldReceive('select')->once()->andThrow(new \RuntimeException('MySQL fora do ar'));

        $this->getJson('/api/v1/saude')->assertStatus(503)
            ->assertJsonPath('status', 'erro')
            ->assertJsonPath('banco', 'erro')
            ->assertJsonPath('modo', 'real')
            ->assertJsonPath('message', 'Banco de dados indisponível.');
    }
}
