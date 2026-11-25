<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use Tests\TestCase;

class EcopontoSessaoTest extends TestCase
{
    public function test_conectar_pelo_qr_assinado_e_idempotente(): void
    {
        $pessoa = $this->criarPessoa();
        $ecoponto = $this->criarEcoponto($this->criarEmpresa(), ['nome' => 'Ecoponto Centro']);

        $primeira = $this->comToken($pessoa)->postJson('/api/v1/ecopontos/conectar', ['qr' => $this->qr($ecoponto)])
            ->assertOk()
            ->assertJsonPath('ecoponto.codigo', $ecoponto->codigo)
            ->assertJsonPath('message', 'Conectado ao Ecoponto Centro.')
            ->assertJsonStructure(['sessao_id', 'expira_em']);

        $this->postJson('/api/v1/ecopontos/conectar', ['qr' => $this->qr($ecoponto)])
            ->assertOk()->assertJsonPath('sessao_id', $primeira->json('sessao_id'));
    }

    public function test_qr_forjado_ou_de_ponto_inativo_e_recusado(): void
    {
        $pessoa = $this->criarPessoa();
        $empresa = $this->criarEmpresa();
        $ecoponto = $this->criarEcoponto($empresa);
        $qr = $this->qr($ecoponto);

        $this->comToken($pessoa);
        // Troca o 1º caractere da assinatura por outro (nunca pelo mesmo)
        $forjado = preg_replace_callback('/sig=(\w)/', fn ($m) => 'sig=' . ($m[1] === '0' ? '1' : '0'), $qr);
        $this->assertNotSame($qr, $forjado);
        $this->postJson('/api/v1/ecopontos/conectar', ['qr' => $forjado])->assertStatus(422)->assertJsonPath('erro', 'qr_invalido');
        $this->postJson('/api/v1/ecopontos/conectar', ['qr' => str_replace("emp={$empresa->id}", 'emp=999', $qr)])->assertStatus(422);
        $this->postJson('/api/v1/ecopontos/conectar', ['qr' => 'ECO-01'])->assertStatus(422);

        $ecoponto->update(['status' => 'manutencao']);
        $this->postJson('/api/v1/ecopontos/conectar', ['qr' => $qr])->assertStatus(422);
    }

    public function test_qr_dinamico_vale_60_segundos(): void
    {
        $pessoa = $this->criarPessoa();
        $ecoponto = $this->criarEcoponto($this->criarEmpresa());
        $qr = $this->qr($ecoponto, true);

        $this->travel(2)->minutes();
        $this->comToken($pessoa)->postJson('/api/v1/ecopontos/conectar', ['qr' => $qr])->assertStatus(422)->assertJsonPath('erro', 'qr_expirado');
    }

    public function test_checkin_credita_e_respeita_o_tempo_de_espera(): void
    {
        [$pessoa, , , $sessaoId] = $this->sessaoAtiva();

        $this->comToken($pessoa)->postJson('/api/v1/checkins', ['sessao_id' => $sessaoId])
            ->assertOk()
            ->assertJsonPath('coinsAwarded', 50)
            ->assertJsonPath('balance', 50)
            ->assertJsonPath('message', 'Check-in feito: +50 coins e 0,5 kg.');

        $this->postJson('/api/v1/checkins', ['sessao_id' => $sessaoId])
            ->assertStatus(429)->assertJsonPath('erro', 'checkin_cooldown')->assertJsonPath('message', 'Próximo check-in em 6 h.');

        $this->assertSame(50, $pessoa->fresh()->saldo_coins);
        $this->assertDatabaseHas('coleta', ['pessoa_id' => $pessoa->id, 'tipo' => 'checkin', 'total_coins' => 50, 'total_kg' => 0.5]);
    }

    public function test_checkin_usa_os_valores_da_configuracao(): void
    {
        Configuracao::query()->whereKey('checkin_coins')->update(['valor' => '70']);
        Configuracao::limparMemoria();
        [$pessoa, , , $sessaoId] = $this->sessaoAtiva();

        $this->comToken($pessoa)->postJson('/api/v1/checkins', ['sessao_id' => $sessaoId])->assertJsonPath('coinsAwarded', 70);
    }

    public function test_sessao_de_outra_pessoa_ou_expirada(): void
    {
        [, , , $sessaoId] = $this->sessaoAtiva();
        $outra = $this->criarPessoa();

        $this->comToken($outra)->postJson('/api/v1/checkins', ['sessao_id' => $sessaoId])->assertStatus(404);

        [$pessoa, , , $sessao2] = $this->sessaoAtiva();
        $this->travel(11)->minutes();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->comToken($pessoa)->postJson('/api/v1/checkins', ['sessao_id' => $sessao2])->assertStatus(410)->assertJsonPath('erro', 'sessao_expirada');
    }

    public function test_tela_do_ecoponto_ve_quem_esta_conectado(): void
    {
        [$pessoa, $empresa, $ecoponto] = $this->sessaoAtiva($this->criarPessoa(['nome' => 'Ana Maria da Silva']));

        $this->comToken($empresa)->getJson("/api/v1/ecopontos/{$ecoponto->id}/sessao-ativa")
            ->assertOk()
            ->assertJsonPath('pessoa.nome', 'Ana M.')
            ->assertJsonMissingPath('pessoa.email')
            ->assertJsonMissingPath('pessoa.cpf');

        $this->deleteJson("/api/v1/ecopontos/{$ecoponto->id}/sessao-ativa")->assertOk();
        $this->getJson("/api/v1/ecopontos/{$ecoponto->id}/sessao-ativa")->assertStatus(404)->assertJsonPath('erro', 'sem_sessao');

        // Outra empresa não vê
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->comToken($this->criarEmpresa())->getJson("/api/v1/ecopontos/{$ecoponto->id}/sessao-ativa")->assertStatus(403);
    }

    public function test_conectar_em_outro_ponto_encerra_a_sessao_anterior(): void
    {
        [$pessoa, , , $primeira] = $this->sessaoAtiva();
        $outro = $this->criarEcoponto($this->criarEmpresa());

        $this->comToken($pessoa)->postJson('/api/v1/ecopontos/conectar', ['qr' => $this->qr($outro)])->assertOk();
        $this->assertDatabaseHas('sessao_ecoponto', ['id' => $primeira, 'status' => 'encerrada']);
    }

    public function test_comando_agendado_expira_sessoes(): void
    {
        [, , , $sessaoId] = $this->sessaoAtiva();
        $this->travel(11)->minutes();
        $this->artisan('ecotech:expirar')->assertSuccessful();
        $this->assertDatabaseHas('sessao_ecoponto', ['id' => $sessaoId, 'status' => 'expirada']);
    }
}
