<?php

namespace Tests\Feature;

use App\Models\Pessoa;
use App\Models\ProdutoCatalogo;
use App\Models\Recompensa;
use Tests\TestCase;

class AdminTest extends TestCase
{
    public function test_lista_de_pessoas_com_busca_e_cpf_mascarado(): void
    {
        $this->criarPessoa(['nome' => 'Ana Maria da Silva', 'cpf' => '52998224725']);
        $this->criarPessoa(['nome' => 'Bruno Souza Oliveira Lima']);

        $this->comToken($this->criarMaster())->getJson('/api/v1/admin/pessoas?busca=maria&perfil=comum')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nome', 'Ana Maria da Silva')
            ->assertJsonPath('data.0.cpf_mascarado', '***.982.247-**')
            ->assertJsonMissingPath('data.0.cpf')
            ->assertJsonMissingPath('data.0.nome_mae')
            ->assertJsonMissingPath('data.0.nascimento')
            ->assertJsonMissingPath('data.0.cep');
    }

    public function test_excluir_pessoa_anonimiza_e_nao_deixa_excluir_master(): void
    {
        $pessoa = $this->criarPessoa();
        $outroMaster = $this->criarMaster();

        $this->comToken($this->criarMaster());
        $this->deleteJson("/api/v1/admin/pessoas/{$pessoa->id}")->assertOk();
        $this->assertNotNull(Pessoa::query()->find($pessoa->id)->anonimizado_em);
        $this->deleteJson("/api/v1/admin/pessoas/{$pessoa->id}")->assertStatus(409);
        $this->deleteJson("/api/v1/admin/pessoas/{$outroMaster->id}")->assertStatus(403);
    }

    public function test_pdf_da_lista_de_usuarios(): void
    {
        $this->criarPessoa(['nome' => 'Ana Maria da Silva']);
        $resposta = $this->comToken($this->criarMaster())->get('/api/v1/admin/pessoas/pdf')->assertOk();

        $this->assertSame('application/pdf', $resposta->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $resposta->getContent());
    }

    public function test_log_de_autenticacao_filtra_por_nome_e_cpf_do_mais_recente_ao_mais_antigo(): void
    {
        $ana = $this->criarPessoa(['nome' => 'Ana Maria da Silva', 'cpf' => '52998224725', 'login' => 'anasil']);
        $this->criarPessoa(['nome' => 'Bruno Souza Oliveira Lima', 'login' => 'brunos']);

        $this->postJson('/api/v1/auth/login', ['login' => 'anasil', 'senha' => 'errada']);
        $this->travel(1)->minutes();
        $this->postJson('/api/v1/auth/login', ['login' => 'anasil', 'senha' => 'abcdefgh']);
        $this->postJson('/api/v1/auth/login', ['login' => 'brunos', 'senha' => 'abcdefgh']);

        $master = $this->criarMaster();
        $this->comToken($master);

        $this->getJson('/api/v1/admin/logs-autenticacao?tipo=nome&termo=ana')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.sucesso', true)     // o mais recente primeiro
            ->assertJsonPath('data.1.sucesso', false)
            ->assertJsonPath('data.0.cpf_mascarado', '***.982.247-**')
            ->assertJsonStructure(['data' => [['data_hora', 'data_hora_formatada', 'nome', 'evento', 'metodo_2fa', 'ip']]]);

        $this->getJson('/api/v1/admin/logs-autenticacao?tipo=cpf&termo=529.982')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/admin/logs-autenticacao?tipo=todos')->assertJsonPath('meta.total', 3);
    }

    public function test_crud_de_recompensas_e_produtos(): void
    {
        $this->comToken($this->criarMaster());

        $id = $this->postJson('/api/v1/admin/recompensas', ['categoria' => 'food', 'nome' => 'Suco Natural', 'custo_coins' => 60, 'icone' => '🧃'])
            ->assertCreated()->json('id');
        $this->patchJson("/api/v1/admin/recompensas/{$id}", ['custo_coins' => 70])->assertOk()->assertJsonPath('custo_coins', 70);
        $this->deleteJson("/api/v1/admin/recompensas/{$id}")->assertOk();
        $this->assertNull(Recompensa::query()->find($id));

        $this->postJson('/api/v1/admin/produtos', ['codigo_barras' => '7896004004777', 'nome' => 'X', 'categoria' => 'plastico', 'coins' => 10])
            ->assertStatus(422)->assertJsonStructure(['campos' => ['codigo_barras']]);
        $this->postJson('/api/v1/admin/produtos', ['codigo_barras' => '96385074', 'nome' => 'Garrafinha', 'categoria' => 'plastico', 'coins' => 10, 'peso_kg' => 0.01])
            ->assertCreated()->assertJsonPath('participa', true);
        $this->patchJson('/api/v1/admin/produtos/96385074', ['ativo' => false])->assertOk()->assertJsonPath('participa', false);
        $this->assertFalse(ProdutoCatalogo::query()->find('96385074')->ativo);
    }

    public function test_ajuste_de_saldo_pelo_master(): void
    {
        $pessoa = $this->criarPessoa();
        $this->comToken($this->criarMaster())->postJson("/api/v1/admin/pessoas/{$pessoa->id}/ajustes", ['valor' => 200, 'descricao' => 'Migração do saldo antigo'])
            ->assertCreated()->assertJsonPath('balance', 200)->assertJsonPath('movimento.tipo', 'ajuste');
        $this->postJson("/api/v1/admin/pessoas/{$pessoa->id}/ajustes", ['valor' => -500, 'descricao' => 'Teste'])
            ->assertStatus(409)->assertJsonPath('erro', 'saldo_insuficiente');
    }

    public function test_master_suspende_empresa_e_ve_o_painel(): void
    {
        $empresa = $this->criarEmpresa();
        $this->token($empresa);

        $this->comToken($this->criarMaster());
        $this->patchJson("/api/v1/admin/empresas/{$empresa->id}", ['status' => 'suspensa'])->assertOk()->assertJsonPath('status', 'suspensa');
        $this->assertSame(0, $empresa->tokens()->count());

        $this->getJson('/api/v1/admin/painel')->assertOk()
            ->assertJsonStructure(['resumo' => ['kg_total', 'pessoas', 'empresas'], 'serie_mensal' => ['labels', 'valores'], 'categorias', 'ecopontos']);
    }

    public function test_lista_de_pessoas_mostra_so_comuns_e_busca_so_pelo_nome(): void
    {
        $this->criarPessoa(['nome' => 'Ana Maria da Silva']);
        $this->criarPessoa(['nome' => 'Bruno Souza Oliveira Lima', 'email' => 'maria.bruno@teste.com']);
        $master = $this->criarMaster();

        $lista = $this->comToken($master)->getJson('/api/v1/admin/pessoas')->assertOk()->assertJsonPath('meta.total', 2);
        $this->assertNotContains('master', collect($lista->json('data'))->pluck('perfil')->all());
        $this->assertSame(['Ana Maria da Silva', 'Bruno Souza Oliveira Lima'], collect($lista->json('data'))->pluck('nome')->all());

        // "maria" está no e-mail do Bruno, mas a busca é só pelo nome
        $this->getJson('/api/v1/admin/pessoas?busca=maria')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.nome', 'Ana Maria da Silva');
    }

    public function test_log_mostra_so_entradas_de_usuarios_comuns(): void
    {
        $this->criarPessoa(['nome' => 'Ana Maria da Silva', 'login' => 'anasil']);
        $master = $this->criarPessoa(['perfil' => 'master', 'nome' => 'Administrador Master EcoTech', 'login' => 'master']);
        $this->criarEmpresa(['email' => 'empresa.log@teste.com']);

        $this->postJson('/api/v1/auth/login', ['login' => 'anasil', 'senha' => 'abcdefgh'])->assertOk();
        $this->postJson('/api/v1/auth/login', ['login' => 'master', 'senha' => 'abcdefgh'])->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => 'empresa.log@teste.com', 'senha' => 'Empresa123'])->assertOk();
        $this->assertSame(3, \App\Models\LogAutenticacao::query()->count());

        $this->comToken($master)->getJson('/api/v1/admin/logs-autenticacao')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nome', 'Ana Maria da Silva')
            ->assertJsonPath('data.0.perfil', 'comum')
            ->assertJsonStructure(['data' => [['data_formatada', 'data_hora_formatada', 'nome', 'cpf_mascarado', 'metodo_2fa', 'sucesso']]]);

        $this->getJson('/api/v1/admin/logs-autenticacao?tipo=nome&termo=master')->assertJsonPath('meta.total', 0);
    }
}
