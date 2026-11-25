<?php

namespace Tests\Feature;

use App\Models\MovimentoCoins;
use App\Models\Pessoa;
use App\Services\CoinService;
use Tests\TestCase;

class MeTest extends TestCase
{
    public function test_me_traz_nivel_e_impacto_calculados(): void
    {
        $pessoa = $this->criarPessoa();
        app(CoinService::class)->creditar($pessoa->id, 1250, 'ajuste', 'Saldo inicial');

        $this->comToken($pessoa)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('coins', 1250)
            ->assertJsonPath('coins_formatado', '1.250')
            ->assertJsonPath('nivel.nome', 'Reciclador Prata')
            ->assertJsonPath('nivel.meta', 'próximo: Guardião Verde (1500 pts)')
            ->assertJsonPath('nivel.progresso', 75)
            ->assertJsonPath('impacto.arvores', '31')
            ->assertJsonPath('impacto.agua', '1.875L')
            ->assertJsonPath('impacto.energia', '313kWh')
            ->assertJsonPath('impacto.co2', '375kg')
            ->assertJsonMissingPath('cpf')
            ->assertJsonMissingPath('nome_mae');
    }

    public function test_subiu_de_nivel_so_na_primeira_leitura(): void
    {
        $pessoa = $this->criarPessoa();
        $this->comToken($pessoa)->getJson('/api/v1/me')->assertJsonPath('nivel.subiu', false);

        app(CoinService::class)->creditar($pessoa->id, 600, 'ajuste', 'Bônus');

        $this->getJson('/api/v1/me')->assertJsonPath('nivel.nome', 'Reciclador Prata')->assertJsonPath('nivel.subiu', true);
        $this->getJson('/api/v1/me')->assertJsonPath('nivel.subiu', false);
    }

    public function test_nivel_maximo(): void
    {
        $pessoa = $this->criarPessoa();
        app(CoinService::class)->creditar($pessoa->id, 3500, 'ajuste', 'Bônus');
        $this->comToken($pessoa)->getJson('/api/v1/me')
            ->assertJsonPath('nivel.nome', 'Mestre Eco')->assertJsonPath('nivel.meta', 'nível máximo alcançado 🏆')->assertJsonPath('nivel.progresso', 100);
    }

    public function test_atualizar_perfil(): void
    {
        $pessoa = $this->criarPessoa();
        $this->criarPessoa(['email' => 'ocupado@teste.com']);

        $this->comToken($pessoa)->putJson('/api/v1/me', ['nome' => 'Ana Maria Atualizada Silva', 'avatar_url' => 'https://exemplo.com/foto.png'])
            ->assertOk()->assertJsonPath('nome', 'Ana Maria Atualizada Silva')->assertJsonPath('avatar', 'https://exemplo.com/foto.png');

        $this->putJson('/api/v1/me', ['email' => 'ocupado@teste.com'])->assertStatus(409)->assertJsonStructure(['campos' => ['email']]);
    }

    public function test_excluir_conta_anonimiza_e_mantem_o_historico(): void
    {
        $pessoa = $this->criarPessoa();
        app(CoinService::class)->creditar($pessoa->id, 100, 'ajuste', 'Saldo inicial');

        $this->comToken($pessoa)->deleteJson('/api/v1/me')->assertNoContent();

        $anonima = Pessoa::query()->find($pessoa->id);
        $this->assertSame('Pessoa removida', $anonima->nome);
        $this->assertNull($anonima->cpf);
        $this->assertNull($anonima->nome_mae);
        $this->assertNull($anonima->nascimento);
        $this->assertNull($anonima->login);
        $this->assertFalse($anonima->ativo);
        $this->assertSame(1, MovimentoCoins::query()->where('pessoa_id', $pessoa->id)->count());
        $this->assertSame(0, $anonima->tokens()->count());
    }

    public function test_trocar_senha_so_para_perfil_comum(): void
    {
        $pessoa = $this->criarPessoa();
        $this->comToken($pessoa)->putJson('/api/v1/me/senha', ['senha_atual' => 'errada', 'nova_senha' => 'NovaSenha1'])
            ->assertStatus(422)->assertJsonStructure(['campos' => ['senha_atual']]);
        $this->putJson('/api/v1/me/senha', ['senha_atual' => 'abcdefgh', 'nova_senha' => 'novasenha'])->assertStatus(422);  // fraca
        $this->putJson('/api/v1/me/senha', ['senha_atual' => 'abcdefgh', 'nova_senha' => 'NovaSenha1'])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->comToken($this->criarMaster())->putJson('/api/v1/me/senha', ['senha_atual' => 'abcdefgh', 'nova_senha' => 'NovaSenha1'])
            ->assertStatus(403);
    }

    public function test_preferencias_do_perfil(): void
    {
        $pessoa = $this->criarPessoa();
        $this->comToken($pessoa)->getJson('/api/v1/me/preferencias')->assertOk()->assertExactJson([]);
        $this->putJson('/api/v1/me/preferencias', ['notificacoes' => true, 'tema' => 'escuro'])->assertOk()->assertJsonPath('notificacoes', true);
        $this->getJson('/api/v1/me/preferencias')->assertExactJson(['notificacoes' => true, 'tema' => 'escuro']);
        $this->putJson('/api/v1/me/preferencias', ['Chave Errada' => true])->assertStatus(422);
    }

    public function test_sessao_atual_da_pessoa(): void
    {
        [$pessoa, , $ecoponto] = $this->sessaoAtiva();
        $this->comToken($pessoa)->getJson('/api/v1/me/sessao')->assertOk()->assertJsonPath('ecoponto.codigo', $ecoponto->codigo);
        $this->deleteJson('/api/v1/me/sessao')->assertOk();
        $this->getJson('/api/v1/me/sessao')->assertStatus(404)->assertJsonPath('erro', 'sem_sessao');
    }
}
