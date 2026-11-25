<?php

namespace Tests\Feature;

use App\Models\DesafioVerificacao;
use App\Models\LogAutenticacao;
use App\Models\TokenAcesso;
use App\Services\TwoFactorService;
use Tests\TestCase;

class AutenticacaoTest extends TestCase
{
    private function respostaCerta(string $tipo): string
    {
        return ['nome_mae' => 'MARIA JOSE da silva', 'nascimento' => '12/05/1990', 'cep' => '20040-020'][$tipo];
    }

    public function test_login_de_pessoa_pede_2fa_e_depois_entrega_o_token(): void
    {
        $pessoa = $this->criarPessoa(['login' => 'anasil']);

        $login = $this->postJson('/api/v1/auth/login', ['login' => 'AnaSil', 'senha' => 'abcdefgh'])
            ->assertOk()
            ->assertJsonPath('status', '2fa')
            ->assertJsonPath('tentativas_restantes', 3)
            ->assertJsonStructure(['desafio_id', 'pergunta', 'pergunta_tipo', 'expira_em']);

        $this->assertContains($login->json('pergunta'), TwoFactorService::PERGUNTAS);

        $this->postJson('/api/v1/auth/2fa', [
            'desafio_id' => $login->json('desafio_id'),
            'resposta'   => $this->respostaCerta($login->json('pergunta_tipo')),
        ])
            ->assertOk()
            ->assertJsonPath('token_tipo', 'Bearer')
            ->assertJsonPath('usuario.id', $pessoa->id)
            ->assertJsonPath('usuario.tipo', 'pessoa')
            ->assertJsonPath('usuario.perfil', 'comum')
            ->assertJsonStructure(['token', 'expira_em', 'usuario' => ['id', 'nome', 'email', 'tipo', 'perfil', 'avatar', 'coins']]);

        // O desafio não pode ser usado de novo
        $this->postJson('/api/v1/auth/2fa', ['desafio_id' => $login->json('desafio_id'), 'resposta' => 'x'])
            ->assertStatus(410)->assertJsonPath('tentativas_restantes', 0);
    }

    public function test_login_por_email(): void
    {
        $this->criarPessoa(['email' => 'ana@teste.com']);
        $this->postJson('/api/v1/auth/login', ['email' => 'ANA@teste.com', 'senha' => 'abcdefgh'])->assertOk()->assertJsonPath('status', '2fa');
    }

    public function test_tres_respostas_erradas_bloqueiam_o_desafio(): void
    {
        $pessoa = $this->criarPessoa(['login' => 'anasil']);
        $desafio = $this->postJson('/api/v1/auth/login', ['login' => 'anasil', 'senha' => 'abcdefgh'])->json();

        $this->postJson('/api/v1/auth/2fa', ['desafio_id' => $desafio['desafio_id'], 'resposta' => 'errada'])
            ->assertStatus(422)->assertJsonPath('erro', 'resposta_invalida')->assertJsonPath('tentativas_restantes', 2);
        $this->postJson('/api/v1/auth/2fa', ['desafio_id' => $desafio['desafio_id'], 'resposta' => 'errada'])
            ->assertStatus(422)->assertJsonPath('tentativas_restantes', 1);
        $this->postJson('/api/v1/auth/2fa', ['desafio_id' => $desafio['desafio_id'], 'resposta' => 'errada'])
            ->assertStatus(423)
            ->assertJsonPath('erro', 'desafio_bloqueado')
            ->assertJsonPath('message', '3 tentativas sem sucesso! Favor realizar Login novamente.')
            ->assertJsonPath('tentativas_restantes', 0);

        // Nem a resposta certa passa depois do bloqueio
        $this->postJson('/api/v1/auth/2fa', ['desafio_id' => $desafio['desafio_id'], 'resposta' => $this->respostaCerta($desafio['pergunta_tipo'])])
            ->assertStatus(423);

        // As falhas ficam no log com a pergunta e o número da tentativa
        $falhas = LogAutenticacao::query()->where('pessoa_id', $pessoa->id)->where('evento', '2fa')->where('sucesso', false)->get();
        $this->assertCount(3, $falhas);
        $this->assertSame([1, 2, 3], $falhas->pluck('tentativa_n')->all());
        $this->assertSame($desafio['pergunta'], $falhas->first()->metodo_2fa);
    }

    public function test_desafio_expira_em_5_minutos(): void
    {
        $this->criarPessoa(['login' => 'anasil']);
        $desafio = $this->postJson('/api/v1/auth/login', ['login' => 'anasil', 'senha' => 'abcdefgh'])->json();

        $this->travel(6)->minutes();

        $this->postJson('/api/v1/auth/2fa', ['desafio_id' => $desafio['desafio_id'], 'resposta' => $this->respostaCerta($desafio['pergunta_tipo'])])
            ->assertStatus(410)->assertJsonPath('erro', 'desafio_expirado');
    }

    public function test_a_pergunta_sorteada_e_so_entre_as_que_a_pessoa_pode_responder(): void
    {
        $this->criarPessoa(['login' => 'semdad', 'nome_mae' => null, 'nascimento' => null]);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['login' => 'semdad', 'senha' => 'abcdefgh'])->assertJsonPath('pergunta_tipo', 'cep');
        }
    }

    public function test_senha_errada_401_e_log_da_falha(): void
    {
        $pessoa = $this->criarPessoa(['login' => 'anasil']);

        $this->postJson('/api/v1/auth/login', ['login' => 'anasil', 'senha' => 'errada'])
            ->assertStatus(401)->assertJsonPath('erro', 'credenciais_invalidas');
        $this->postJson('/api/v1/auth/login', ['login' => 'ninguem', 'senha' => 'abcdefgh'])->assertStatus(401);

        $this->assertDatabaseHas('log_autenticacao', ['pessoa_id' => $pessoa->id, 'evento' => 'senha', 'sucesso' => false]);
    }

    public function test_login_de_empresa_entrega_o_token_direto(): void
    {
        $empresa = $this->criarEmpresa(['email' => 'contato@recicla.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'contato@recicla.com', 'senha' => 'Empresa123'])
            ->assertOk()
            ->assertJsonPath('usuario.tipo', 'empresa')
            ->assertJsonPath('usuario.cnpj', $empresa->cnpj)
            ->assertJsonStructure(['token']);
    }

    public function test_empresa_suspensa_nao_entra(): void
    {
        $this->criarEmpresa(['email' => 'contato@recicla.com', 'status' => 'suspensa']);
        $this->postJson('/api/v1/auth/login', ['email' => 'contato@recicla.com', 'senha' => 'Empresa123'])
            ->assertStatus(403)->assertJsonPath('erro', 'empresa_suspensa');
    }

    public function test_limite_de_5_tentativas_de_login_em_15_minutos(): void
    {
        $this->criarPessoa(['login' => 'anasil']);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['login' => 'anasil', 'senha' => 'errada'])->assertStatus(401);
        }
        $this->postJson('/api/v1/auth/login', ['login' => 'anasil', 'senha' => 'abcdefgh'])
            ->assertStatus(429)->assertJsonPath('erro', 'muitas_tentativas')->assertHeader('Retry-After');
    }

    public function test_logout_revoga_o_token(): void
    {
        $pessoa = $this->criarPessoa();
        $token = $this->token($pessoa);

        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertSame(0, TokenAcesso::query()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401)->assertJsonPath('erro', 'nao_autenticado');
    }

    public function test_token_dura_8_horas_ou_30_dias_com_lembrar(): void
    {
        $pessoa = $this->criarPessoa();
        $token = $this->token($pessoa);

        $this->travel(9)->hours();
        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);

        $this->app['auth']->forgetGuards();
        $lembrar = $this->token($pessoa, true);
        $this->travel(29)->days();
        $this->withToken($lembrar)->getJson('/api/v1/me')->assertOk();
    }

    public function test_master_recebe_o_papel_master_no_token(): void
    {
        $master = $this->criarMaster();
        $this->token($master);
        $this->assertSame(['pessoa', 'master'], TokenAcesso::query()->first()->abilities);
    }

    public function test_desafio_inexistente_410(): void
    {
        $this->postJson('/api/v1/auth/2fa', ['desafio_id' => '6f1c1d0e-8a2b-4c3d-9e4f-123456789abc', 'resposta' => 'x'])
            ->assertStatus(410)->assertJsonPath('tentativas_restantes', 0);
        $this->assertSame(0, DesafioVerificacao::query()->count());
    }
}
