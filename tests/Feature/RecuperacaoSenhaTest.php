<?php

namespace Tests\Feature;

use App\Models\Pessoa;
use App\Models\TokenAcesso;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RecuperacaoSenhaTest extends TestCase
{
    public function test_fluxo_completo_de_recuperacao(): void
    {
        $pessoa = $this->criarPessoa(['email' => 'ana@teste.com', 'login' => 'anasil']);
        $this->token($pessoa); // um aparelho logado

        $desafio = $this->postJson('/api/v1/auth/esqueci-senha', ['email' => 'ana@teste.com'])
            ->assertOk()
            ->assertJsonPath('status', 'codigo_email')
            ->assertJsonPath('finalidade', 'recuperar_senha')
            ->assertJsonStructure(['desafio_id', 'email', 'expira_em', 'reenviar_em'])->json();

        $token = $this->postJson('/api/v1/auth/esqueci-senha/verificar', [
            'desafio_id' => $desafio['desafio_id'], 'codigo' => $this->ultimoCodigo('ana@teste.com'),
        ])->assertOk()->assertJsonStructure(['token', 'expira_em'])->json('token');

        $this->postJson('/api/v1/auth/redefinir-senha', ['token' => $token, 'nova_senha' => 'NovaSenha1'])
            ->assertOk()->assertJsonPath('message', 'Senha alterada! Faça login com a nova senha.');

        $this->assertTrue(Hash::check('NovaSenha1', $pessoa->fresh()->senha_hash));
        $this->assertSame(0, TokenAcesso::query()->count()); // saiu de todos os aparelhos

        // O mesmo token não serve duas vezes
        $this->postJson('/api/v1/auth/redefinir-senha', ['token' => $token, 'nova_senha' => 'OutraSenha1'])->assertStatus(410);

        $this->postJson('/api/v1/auth/login', ['login' => 'anasil', 'senha' => 'NovaSenha1'])->assertOk();
    }

    public function test_email_inexistente_recebe_desafio_falso_e_nao_envia_email(): void
    {
        $this->criarPessoa(['email' => 'ana@teste.com']);
        $real = $this->postJson('/api/v1/auth/esqueci-senha', ['email' => 'ana@teste.com'])->assertOk()->json();
        $enviados = $this->emailsEnviados();

        $falso = $this->postJson('/api/v1/auth/esqueci-senha', ['email' => 'naoexiste@teste.com'])->assertOk()->json();

        // Mesma cara de resposta, mas nenhum e-mail sai
        $this->assertSame(array_keys($real), array_keys($falso));
        $this->assertSame($real['message'], $falso['message']);
        $this->assertSame($enviados, $this->emailsEnviados());

        // Qualquer código falha como se fosse real, e bloqueia na 5ª
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/auth/esqueci-senha/verificar', ['desafio_id' => $falso['desafio_id'], 'codigo' => '12345' . $i])
                ->assertStatus(422)->assertJsonPath('erro', 'codigo_invalido');
        }
        $this->postJson('/api/v1/auth/esqueci-senha/verificar', ['desafio_id' => $falso['desafio_id'], 'codigo' => '000000'])
            ->assertStatus(423);
    }

    public function test_token_de_redefinicao_vale_10_minutos(): void
    {
        $this->criarPessoa(['email' => 'ana@teste.com']);
        $d = $this->postJson('/api/v1/auth/esqueci-senha', ['email' => 'ana@teste.com'])->json();
        $token = $this->postJson('/api/v1/auth/esqueci-senha/verificar', ['desafio_id' => $d['desafio_id'], 'codigo' => $this->ultimoCodigo('ana@teste.com')])->json('token');

        $this->travel(11)->minutes();
        $this->postJson('/api/v1/auth/redefinir-senha', ['token' => $token, 'nova_senha' => 'NovaSenha1'])
            ->assertStatus(410)->assertJsonPath('erro', 'token_expirado');
    }

    public function test_nova_senha_segue_a_regra(): void
    {
        $this->postJson('/api/v1/auth/redefinir-senha', ['token' => str_repeat('a', 64), 'nova_senha' => '123'])
            ->assertStatus(422)->assertJsonStructure(['campos' => ['nova_senha']]);
    }
}
