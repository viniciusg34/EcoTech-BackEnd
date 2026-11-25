<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleTest extends TestCase
{
    private function fakeGoogle(array $troca = []): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(array_merge([
            'aud' => 'cliente-teste.apps.googleusercontent.com', 'iss' => 'https://accounts.google.com',
            'exp' => (string) (time() + 3600), 'email' => 'ana@gmail.com', 'email_verified' => 'true',
            'name' => 'Ana Maria da Silva', 'sub' => '123456',
        ], $troca))]);
    }

    public function test_conta_existente_segue_para_o_2fa(): void
    {
        $pessoa = $this->criarPessoa(['email' => 'ana@gmail.com']);
        $this->fakeGoogle();

        $this->postJson('/api/v1/auth/google', ['credential' => 'token-do-google'])
            ->assertOk()->assertJsonPath('status', '2fa')->assertJsonStructure(['desafio_id', 'pergunta']);

        $this->assertDatabaseHas('log_autenticacao', ['pessoa_id' => $pessoa->id, 'evento' => 'google', 'sucesso' => true]);
    }

    public function test_sem_conta_pede_para_completar_o_cadastro(): void
    {
        $this->fakeGoogle();
        $this->postJson('/api/v1/auth/google', ['credential' => 'token-do-google'])
            ->assertOk()
            ->assertJsonPath('status', 'cadastro_pendente')
            ->assertJsonPath('email', 'ana@gmail.com')
            ->assertJsonPath('nome', 'Ana Maria da Silva');
    }

    public function test_token_de_outro_aplicativo_e_recusado(): void
    {
        $this->fakeGoogle(['aud' => 'outro-app']);
        $this->postJson('/api/v1/auth/google', ['credential' => 'token'])->assertStatus(401)->assertJsonPath('erro', 'google_invalido');
    }

    public function test_master_nao_entra_com_google(): void
    {
        $this->criarPessoa(['email' => 'ana@gmail.com', 'perfil' => 'master']);
        $this->fakeGoogle();
        $this->postJson('/api/v1/auth/google', ['credential' => 'token'])
            ->assertStatus(403)->assertJsonPath('erro', 'google_so_usuario_comum');
    }

    public function test_empresa_nao_entra_com_google(): void
    {
        $this->criarEmpresa(['email' => 'ana@gmail.com']);
        $this->fakeGoogle();
        $this->postJson('/api/v1/auth/google', ['credential' => 'token'])
            ->assertStatus(403)->assertJsonPath('erro', 'google_so_usuario_comum');
        $this->assertDatabaseCount('desafio_verificacao', 0);
    }
}
