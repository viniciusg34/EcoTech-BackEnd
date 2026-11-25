<?php

namespace Tests\Feature;

use App\Models\Pessoa;
use App\Models\TokenAcesso;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RecuperacaoSenhaTest extends TestCase
{
    private function resposta(string $tipo): string
    {
        return ['nome_mae' => 'Maria José da Silva', 'nascimento' => '12/05/1990', 'cep' => '20040020'][$tipo];
    }

    public function test_fluxo_completo_de_recuperacao(): void
    {
        $pessoa = $this->criarPessoa(['email' => 'ana@teste.com', 'login' => 'anasil']);
        $this->token($pessoa); // um aparelho logado

        $desafio = $this->postJson('/api/v1/auth/esqueci-senha', ['email' => 'ana@teste.com'])
            ->assertOk()->assertJsonStructure(['desafio_id', 'pergunta', 'pergunta_tipo'])->json();

        $token = $this->postJson('/api/v1/auth/esqueci-senha/verificar', [
            'desafio_id' => $desafio['desafio_id'], 'resposta' => $this->resposta($desafio['pergunta_tipo']),
        ])->assertOk()->assertJsonStructure(['token', 'expira_em'])->json('token');

        $this->postJson('/api/v1/auth/redefinir-senha', ['token' => $token, 'nova_senha' => 'NovaSenha1'])
            ->assertOk()->assertJsonPath('message', 'Senha alterada! Faça login com a nova senha.');

        $this->assertTrue(Hash::check('NovaSenha1', $pessoa->fresh()->senha_hash));
        $this->assertSame(0, TokenAcesso::query()->count()); // saiu de todos os aparelhos

        // O mesmo token não serve duas vezes
        $this->postJson('/api/v1/auth/redefinir-senha', ['token' => $token, 'nova_senha' => 'OutraSenha1'])->assertStatus(410);

        $this->postJson('/api/v1/auth/login', ['login' => 'anasil', 'senha' => 'NovaSenha1'])->assertOk();
    }

    public function test_email_inexistente_recebe_desafio_falso_e_estavel(): void
    {
        $a = $this->postJson('/api/v1/auth/esqueci-senha', ['email' => 'naoexiste@teste.com'])->assertOk()->json();
        $b = $this->postJson('/api/v1/auth/esqueci-senha', ['email' => 'naoexiste@teste.com'])->assertOk()->json();

        $this->assertSame($a['pergunta'], $b['pergunta']);     // mesma pergunta para o mesmo e-mail
        $this->assertNotSame($a['desafio_id'], $b['desafio_id']);

        // Qualquer resposta falha como se fosse real, e bloqueia na 3ª
        foreach (['Maria José da Silva', '12/05/1990'] as $resposta) {
            $this->postJson('/api/v1/auth/esqueci-senha/verificar', ['desafio_id' => $a['desafio_id'], 'resposta' => $resposta])
                ->assertStatus(422)->assertJsonPath('erro', 'resposta_invalida');
        }
        $this->postJson('/api/v1/auth/esqueci-senha/verificar', ['desafio_id' => $a['desafio_id'], 'resposta' => '20040020'])
            ->assertStatus(423);
    }

    public function test_pergunta_de_conta_real_tambem_e_estavel(): void
    {
        $this->criarPessoa(['email' => 'ana@teste.com']);
        $a = $this->postJson('/api/v1/auth/esqueci-senha', ['email' => 'ana@teste.com'])->json('pergunta');
        $b = $this->postJson('/api/v1/auth/esqueci-senha', ['email' => 'ana@teste.com'])->json('pergunta');
        $this->assertSame($a, $b);
    }

    public function test_token_de_redefinicao_vale_10_minutos(): void
    {
        $this->criarPessoa(['email' => 'ana@teste.com']);
        $d = $this->postJson('/api/v1/auth/esqueci-senha', ['email' => 'ana@teste.com'])->json();
        $token = $this->postJson('/api/v1/auth/esqueci-senha/verificar', ['desafio_id' => $d['desafio_id'], 'resposta' => $this->resposta($d['pergunta_tipo'])])->json('token');

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
