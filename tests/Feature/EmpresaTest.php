<?php

namespace Tests\Feature;

use App\Models\LeituraCodigo;
use App\Services\CoinService;
use Tests\TestCase;

class EmpresaTest extends TestCase
{
    public function test_empresa_cria_e_edita_os_proprios_ecopontos(): void
    {
        $empresa = $this->criarEmpresa();
        $this->comToken($empresa);

        $criado = $this->postJson('/api/v1/ecopontos', [
            'nome' => 'Ecoponto Novo', 'lat' => -22.95, 'lng' => -43.18, 'cidade' => 'Rio de Janeiro', 'estado' => 'rj',
            'categorias' => ['metal', 'papel'],
        ])->assertCreated()->assertJsonPath('estado', 'RJ')->assertJsonCount(2, 'categorias');

        $id = $criado->json('id');
        $this->assertMatchesRegularExpression('/^ECO-\d{2,}$/', $criado->json('codigo'));

        $this->patchJson("/api/v1/ecopontos/{$id}", ['status' => 'manutencao'])
            ->assertOk()->assertJsonPath('status', 'manutencao')->assertJsonPath('status_rotulo', 'Em manutenção');

        $this->postJson('/api/v1/ecopontos', ['nome' => 'X', 'lat' => 200, 'lng' => 0, 'categorias' => ['ouro']])
            ->assertStatus(422)->assertJsonStructure(['campos' => ['lat', 'categorias.0']]);

        // Outra empresa não edita
        $this->comToken($this->criarEmpresa())->patchJson("/api/v1/ecopontos/{$id}", ['nome' => 'Invadido'])
            ->assertStatus(403)->assertJsonPath('message', 'Este Ecoponto não é da sua empresa.');
    }

    public function test_qr_e_imagem_svg_do_qr(): void
    {
        $empresa = $this->criarEmpresa();
        $ecoponto = $this->criarEcoponto($empresa, ['codigo' => 'ECO-77']);
        $this->comToken($empresa);

        $this->getJson("/api/v1/ecopontos/{$ecoponto->id}/qr")->assertOk()
            ->assertJsonPath('payload', $this->qr($ecoponto))
            ->assertJsonPath('dinamico', false);
        $this->getJson("/api/v1/ecopontos/{$ecoponto->id}/qr?dinamico=1")->assertOk()->assertJsonPath('dinamico', true);

        $svg = $this->get("/api/v1/ecopontos/{$ecoponto->id}/qr.svg")->assertOk();
        $this->assertSame('image/svg+xml', $svg->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $svg->getContent());
    }

    public function test_painel_da_empresa_com_dados_reais(): void
    {
        [$pessoa, $empresa, $ecoponto, $sessaoId] = $this->sessaoAtiva();
        $this->comToken($pessoa)->postJson('/api/v1/checkins', ['sessao_id' => $sessaoId])->assertOk();
        $this->comToken($empresa)->postJson("/api/v1/ecopontos/{$ecoponto->id}/leituras", ['sessao_id' => $sessaoId, 'code' => '7891234567895'])->assertOk();

        $painel = $this->getJson('/api/v1/empresas/me/dashboard')->assertOk()
            ->assertJsonPath('resumo.coletas', 2)
            ->assertJsonPath('resumo.pessoas_atendidas', 1)
            ->assertJsonPath('resumo.coins_distribuidos', 90)
            ->assertJsonCount(6, 'serie_mensal.labels')
            ->assertJsonCount(6, 'serie_mensal.valores')
            ->assertJsonPath('ecopontos.0.sessao_ativa', true);

        $this->assertSame(0.52, $painel->json('serie_mensal.valores.5'));   // 0,5 kg do check-in + 0,02 kg da lata
        $metal = collect($painel->json('categorias'))->firstWhere('chave', 'metal');
        $this->assertSame(100, (int) $metal['percentual']);
    }

    public function test_dados_e_senha_da_empresa(): void
    {
        $empresa = $this->criarEmpresa();
        $this->comToken($empresa)->getJson('/api/v1/empresas/me')->assertOk()->assertJsonPath('cnpj', $empresa->cnpj);
        $this->putJson('/api/v1/empresas/me', ['nome_empresa' => 'Novo Nome'])->assertOk()->assertJsonPath('nome_empresa', 'Novo Nome');
        $this->putJson('/api/v1/empresas/me/senha', ['senha_atual' => 'Empresa123', 'nova_senha' => 'fraca'])->assertStatus(422);
        $this->putJson('/api/v1/empresas/me/senha', ['senha_atual' => 'Empresa123', 'nova_senha' => 'NovaSenha9'])->assertOk();
    }

    public function test_painel_da_empresa_no_formato_do_front(): void
    {
        [$pessoa, $empresa, $ecoponto, $sessaoId] = $this->sessaoAtiva();
        $this->comToken($pessoa)->postJson('/api/v1/checkins', ['sessao_id' => $sessaoId])->assertOk();
        $this->comToken($empresa)->postJson("/api/v1/ecopontos/{$ecoponto->id}/leituras", ['sessao_id' => $sessaoId, 'code' => '7891234567895'])->assertOk();
        $this->criarEcoponto($empresa, ['status' => 'manutencao']);

        $painel = $this->getJson('/api/v1/empresas/me/dashboard')->assertOk()
            ->assertJsonPath('totais.ecopontos', 2)
            ->assertJsonPath('totais.ativos', 1)
            ->assertJsonPath('totais.kg_formatado', '0,52')
            ->assertJsonPath('totais.validacoes_formatado', '1')
            ->assertJsonPath('totais.variacao_kg_texto', 'Primeiro mês com coletas')
            ->assertJsonCount(6, 'serie.labels')
            ->assertJsonCount(6, 'serie.valores_formatados')
            ->assertJsonPath('serie.valores_formatados.5', '0,52')
            ->assertJsonPath('serie.total_formatado', 'Total: 0,52 kg')
            ->assertJsonPath('ecopontos.0.kg_mes_formatado', '0,52 kg')
            ->assertJsonPath('ecopontos.0.endereco', 'Rio de Janeiro')
            ->assertJsonCount(3, 'ecopontos.0.categorias')
            ->assertJsonPath('ecopontos.1.ultima_atividade', 'Sem atividade ainda');

        $meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $this->assertSame($meses[now()->month - 1], $painel->json('serie.labels.5'));
        $this->assertStringStartsWith('Hoje, ', $painel->json('ecopontos.0.ultima_atividade'));
        $this->assertSame(100, array_sum(array_column($painel->json('categorias'), 'percentual')));
        foreach ($painel->json('categorias') as $categoria) {
            $this->assertIsInt($categoria['percentual']);
        }
    }

    public function test_variacao_do_mes_comparada_com_o_mes_anterior(): void
    {
        [$pessoa, $empresa, $ecoponto] = $this->sessaoAtiva();
        $coleta = fn (float $kg, $quando) => \App\Models\Coleta::query()->forceCreate([
            'pessoa_id' => $pessoa->id, 'ecoponto_id' => $ecoponto->id, 'tipo' => 'entrega',
            'total_coins' => 0, 'total_kg' => $kg, 'criado_em' => $quando,
        ]);
        $inicio = \App\Services\EstatisticaService::inicioDoMes();
        $coleta(10, $inicio->copy()->subDays(5));
        $coleta(12, $inicio->copy()->addHour());

        $this->comToken($empresa)->getJson('/api/v1/empresas/me/dashboard')->assertOk()
            ->assertJsonPath('totais.variacao_kg_texto', '↑ 20% vs mês anterior');
    }

    public function test_historico_de_leituras_da_empresa_sem_dados_pessoais(): void
    {
        [, $empresa, $ecoponto, $sessaoId] = $this->sessaoAtiva($this->criarPessoa(['nome' => 'Ana Maria da Silva']));
        $url = "/api/v1/ecopontos/{$ecoponto->id}/leituras";

        $this->comToken($empresa)->postJson($url, ['sessao_id' => $sessaoId, 'code' => '7896004004778'])->assertOk();
        $this->travel(1)->minutes();
        $this->postJson($url, ['sessao_id' => $sessaoId, 'code' => '7896004004778'])->assertStatus(409);
        $this->travel(1)->minutes();
        $this->postJson($url, ['sessao_id' => $sessaoId, 'code' => '7896004004777'])->assertStatus(422);

        $resposta = $this->getJson('/api/v1/empresas/me/leituras')->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.periodo', 'mes')
            ->assertJsonPath('data.0.resultado_rotulo', 'Código inválido')     // a mais recente primeiro
            ->assertJsonPath('data.0.produto', 'Código 7896004004777')
            ->assertJsonPath('data.0.kg_formatado', '—')
            ->assertJsonPath('data.0.coins', 0)
            ->assertJsonPath('data.1.resultado_rotulo', 'Duplicado')
            ->assertJsonPath('data.2.resultado_rotulo', 'Creditado')
            ->assertJsonPath('data.2.produto', 'Garrafa PET 500ml')
            ->assertJsonPath('data.2.categoria', 'Plástico')
            ->assertJsonPath('data.2.kg_formatado', '0,02 kg')
            ->assertJsonPath('data.2.coins', 30)
            ->assertJsonPath('data.2.ecoponto_codigo', $ecoponto->codigo);

        // LGPD: nada de quem reciclou
        $texto = $resposta->getContent();
        foreach (['pessoa', 'sessao_id', 'Ana', 'cpf', 'email'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $texto);
        }
    }

    public function test_historico_de_leituras_por_periodo_e_ecoponto(): void
    {
        [, $empresa, $ecoponto, $sessaoId] = $this->sessaoAtiva();
        $outro = $this->criarEcoponto($empresa);
        $this->comToken($empresa)->postJson("/api/v1/ecopontos/{$ecoponto->id}/leituras", ['sessao_id' => $sessaoId, 'code' => '7891910000197'])->assertOk();
        $antiga = LeituraCodigo::query()->first()->replicate();
        $antiga->forceFill(['lido_em' => now()->subMonths(2)->subDay(), 'idempotency_key' => null])->save();

        $this->getJson('/api/v1/empresas/me/leituras?periodo=mes')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/empresas/me/leituras?periodo=3m')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/empresas/me/leituras?periodo=tudo&por_pagina=100')->assertJsonPath('meta.total', 2)->assertJsonPath('meta.por_pagina', 100);
        $this->getJson("/api/v1/empresas/me/leituras?periodo=tudo&ecoponto_id={$outro->id}")->assertJsonPath('meta.total', 0);
        $this->getJson("/api/v1/empresas/me/leituras?periodo=tudo&ecoponto_id={$ecoponto->id}")->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/empresas/me/leituras?periodo=semana')->assertStatus(422)->assertJsonStructure(['campos' => ['periodo']]);
        $this->getJson('/api/v1/empresas/me/leituras?ecoponto_id=999999')->assertStatus(404);

        // Ecoponto de outra empresa e pessoa comum: 403
        $deOutraEmpresa = $this->criarEcoponto($this->criarEmpresa());
        $this->getJson("/api/v1/empresas/me/leituras?ecoponto_id={$deOutraEmpresa->id}")
            ->assertStatus(403)->assertJsonPath('message', 'Este Ecoponto não é da sua empresa.');
        $this->comToken($this->criarPessoa())->getJson('/api/v1/empresas/me/leituras')->assertStatus(403);
    }
}
