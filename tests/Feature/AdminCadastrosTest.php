<?php

namespace Tests\Feature;

use App\Models\Ecoponto;
use App\Models\Empresa;
use App\Models\LogAutenticacao;
use Tests\TestCase;

/*
 * Master editando e excluindo Ecopontos e empresas.
 */
class AdminCadastrosTest extends TestCase
{
    public function test_master_lista_e_edita_ecoponto_de_qualquer_empresa(): void
    {
        $empresa = $this->criarEmpresa(['nome_empresa' => 'Recicla Norte']);
        $ponto = $this->criarEcoponto($empresa, ['nome' => 'Ponto da Praça', 'cidade' => 'Niterói']);
        $this->criarEcoponto($this->criarEmpresa());

        $this->comToken($this->criarMaster());
        $this->getJson('/api/v1/admin/ecopontos')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/admin/ecopontos?busca=niter')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.empresa.nome', 'Recicla Norte');
        $this->getJson("/api/v1/admin/ecopontos?empresa_id={$empresa->id}")->assertJsonPath('meta.total', 1);

        $this->patchJson("/api/v1/admin/ecopontos/{$ponto->id}", [
            'nome' => 'Ponto da Praça XV', 'status' => 'manutencao', 'categorias' => ['papel', 'vidro'],
        ])->assertOk()
            ->assertJsonPath('nome', 'Ponto da Praça XV')
            ->assertJsonPath('status', 'manutencao')
            ->assertJsonCount(2, 'categorias')
            ->assertJsonPath('message', 'Ecoponto atualizado.');

        $this->patchJson("/api/v1/admin/ecopontos/{$ponto->id}", ['lat' => 200])->assertStatus(422)->assertJsonStructure(['campos' => ['lat']]);
    }

    public function test_excluir_ecoponto_sem_historico_apaga(): void
    {
        $ponto = $this->criarEcoponto($this->criarEmpresa());

        $this->comToken($this->criarMaster())->deleteJson("/api/v1/admin/ecopontos/{$ponto->id}")
            ->assertOk()->assertJsonPath('acao', 'excluido');
        $this->assertDatabaseMissing('ecoponto', ['id' => $ponto->id]);
    }

    public function test_excluir_ecoponto_com_historico_so_desativa(): void
    {
        [$pessoa, , $ponto, $sessaoId] = $this->sessaoAtiva();
        $this->comToken($pessoa)->postJson('/api/v1/checkins', ['sessao_id' => $sessaoId])->assertOk();

        $this->comToken($this->criarMaster())->deleteJson("/api/v1/admin/ecopontos/{$ponto->id}")
            ->assertOk()->assertJsonPath('acao', 'desativado');
        $this->assertSame('inativo', $ponto->fresh()->status);
        $this->assertDatabaseHas('coleta', ['ecoponto_id' => $ponto->id]);   // o histórico continua
        $this->assertSame('encerrada', $ponto->sessoes()->first()->status);
    }

    public function test_master_edita_e_suspende_empresa(): void
    {
        $empresa = $this->criarEmpresa(['nome_empresa' => 'Recicla Norte']);
        $outra = $this->criarEmpresa(['email' => 'ja.usado@teste.com']);

        $this->comToken($this->criarMaster());
        $this->getJson('/api/v1/admin/empresas?busca=norte')->assertOk()->assertJsonPath('meta.total', 1);

        $this->patchJson("/api/v1/admin/empresas/{$empresa->id}", ['nome_empresa' => 'Recicla Norte Ltda', 'endereco' => 'Rua A, 10'])
            ->assertOk()->assertJsonPath('nome_empresa', 'Recicla Norte Ltda')->assertJsonPath('message', 'Empresa atualizada.');
        $this->patchJson("/api/v1/admin/empresas/{$empresa->id}", ['email' => 'ja.usado@teste.com'])
            ->assertStatus(422)->assertJsonStructure(['campos' => ['email']]);
        $this->patchJson("/api/v1/admin/empresas/{$empresa->id}", ['status' => 'suspensa'])
            ->assertOk()->assertJsonPath('status', 'suspensa')->assertJsonPath('message', 'Empresa suspensa.');
        $this->assertNotNull($outra->fresh());
    }

    public function test_excluir_empresa_sem_historico_apaga_tudo(): void
    {
        $empresa = $this->criarEmpresa(['email' => 'apagar@teste.com']);
        $ponto = $this->criarEcoponto($empresa);
        $this->postJson('/api/v1/auth/login', ['email' => 'apagar@teste.com', 'senha' => 'Empresa123'])->assertOk();
        $this->assertSame(1, LogAutenticacao::query()->where('empresa_id', $empresa->id)->count());

        $this->comToken($this->criarMaster())->deleteJson("/api/v1/admin/empresas/{$empresa->id}")
            ->assertOk()->assertJsonPath('acao', 'excluida');
        $this->assertNull(Empresa::query()->find($empresa->id));
        $this->assertNull(Ecoponto::query()->find($ponto->id));
    }

    public function test_excluir_empresa_com_historico_suspende_e_desativa_os_pontos(): void
    {
        [$pessoa, $empresa, $ponto, $sessaoId] = $this->sessaoAtiva();
        $semUso = $this->criarEcoponto($empresa);
        $this->comToken($pessoa)->postJson('/api/v1/checkins', ['sessao_id' => $sessaoId])->assertOk();

        $this->comToken($this->criarMaster())->deleteJson("/api/v1/admin/empresas/{$empresa->id}")
            ->assertOk()->assertJsonPath('acao', 'desativada');
        $this->assertSame('suspensa', $empresa->fresh()->status);
        $this->assertSame('inativo', $ponto->fresh()->status);
        $this->assertNull(Ecoponto::query()->find($semUso->id));   // o ponto sem coletas foi apagado

        // a empresa suspensa não entra mais
        $this->postJson('/api/v1/auth/login', ['email' => $empresa->email, 'senha' => 'Empresa123'])->assertStatus(403);
    }

    public function test_so_o_master_mexe_nos_cadastros(): void
    {
        $empresa = $this->criarEmpresa();
        $ponto = $this->criarEcoponto($empresa);

        $this->comToken($this->criarPessoa())->getJson('/api/v1/admin/ecopontos')->assertStatus(403);
        $this->deleteJson("/api/v1/admin/empresas/{$empresa->id}")->assertStatus(403);
        $this->comToken($empresa)->deleteJson("/api/v1/admin/ecopontos/{$ponto->id}")->assertStatus(403);
        $this->assertNotNull($ponto->fresh());
    }
}
