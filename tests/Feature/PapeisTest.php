<?php

namespace Tests\Feature;

use Tests\TestCase;

/*
 * Autorização por papel: pessoa, empresa e master.
 */
class PapeisTest extends TestCase
{
    public function test_sem_token_401_em_json(): void
    {
        foreach (['/api/v1/me', '/api/v1/empresas/me', '/api/v1/admin/pessoas'] as $url) {
            $this->getJson($url)->assertStatus(401)->assertJsonStructure(['erro', 'message']);
        }
    }

    public function test_pessoa_nao_acessa_rotas_de_empresa_nem_de_master(): void
    {
        $this->comToken($this->criarPessoa());
        $this->getJson('/api/v1/empresas/me')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
        $this->getJson('/api/v1/admin/pessoas')->assertStatus(403);
        $this->getJson('/api/v1/admin/logs-autenticacao')->assertStatus(403);
        $this->postJson('/api/v1/ecopontos', [])->assertStatus(403);
    }

    public function test_empresa_nao_acessa_rotas_de_pessoa(): void
    {
        $this->comToken($this->criarEmpresa());
        $this->getJson('/api/v1/me')->assertStatus(403);
        $this->postJson('/api/v1/checkins', ['sessao_id' => 1])->assertStatus(403);
        $this->getJson('/api/v1/admin/pessoas')->assertStatus(403);
    }

    public function test_master_acessa_o_admin_e_tambem_as_rotas_de_pessoa(): void
    {
        $this->comToken($this->criarMaster());
        $this->getJson('/api/v1/admin/pessoas')->assertOk();
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('perfil', 'master');
    }

    public function test_empresa_suspensa_perde_o_acesso_na_hora(): void
    {
        $empresa = $this->criarEmpresa();
        $this->comToken($empresa)->getJson('/api/v1/empresas/me')->assertOk();

        $empresa->forceFill(['status' => 'suspensa'])->save();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/empresas/me')->assertStatus(403);
    }

    public function test_pessoa_que_deixou_de_ser_master_perde_o_admin(): void
    {
        $master = $this->criarMaster();
        $this->comToken($master)->getJson('/api/v1/admin/pessoas')->assertOk();

        $master->forceFill(['perfil' => 'comum'])->save();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/admin/pessoas')->assertStatus(403);
    }
}
