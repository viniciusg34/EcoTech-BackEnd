<?php

namespace Tests\Feature;

use App\Models\Pessoa;
use Tests\TestCase;

class CadastroTest extends TestCase
{
    private function dados(array $troca = []): array
    {
        return array_merge([
            'nome' => 'Ana Maria da Silva Souza', 'email' => 'Ana@Teste.com', 'cpf' => '529.982.247-25',
            'login' => 'AnaSil', 'senha' => 'Senha123', 'nome_mae' => 'Joana Pereira', 'nascimento' => '1990-05-12',
            'sexo' => 'feminino', 'telefone_celular' => '21999991234', 'telefone_fixo' => '2133331234',
            'cep' => '20040020', 'logradouro' => 'Rua da Assembleia', 'numero' => '10', 'bairro' => 'Centro',
            'cidade' => 'Rio de Janeiro', 'uf' => 'RJ', 'termos_aceitos' => true,
        ], $troca);
    }

    public function test_cadastro_de_pessoa_com_sucesso(): void
    {
        $this->postJson('/api/v1/pessoas', $this->dados())
            ->assertCreated()
            ->assertJsonPath('usuario.email', 'ana@teste.com')
            ->assertJsonPath('usuario.perfil', 'comum')
            ->assertJsonMissingPath('token');

        $pessoa = Pessoa::query()->where('email', 'ana@teste.com')->first();
        $this->assertSame('52998224725', $pessoa->cpf);         // só dígitos
        $this->assertSame('anasil', $pessoa->login);            // minúsculo
        $this->assertNotSame('Senha123', $pessoa->senha_hash);  // senha com hash
        $this->assertSame('comum', $pessoa->perfil);
        $this->assertSame(0, $pessoa->saldo_coins);
        $this->assertNotNull($pessoa->termos_aceitos_em);
    }

    public function test_aceita_mascaras_e_data_brasileira(): void
    {
        $this->postJson('/api/v1/pessoas', $this->dados([
            'telefone_celular' => '(+55)21-99999-1234', 'telefone_fixo' => '(21) 3333-1234',
            'cep' => '20040-020', 'nascimento' => '12/05/1990', 'uf' => 'rj', 'sexo' => 'F',
        ]))->assertCreated();

        $pessoa = Pessoa::query()->first();
        $this->assertSame('21999991234', $pessoa->telefone_celular);
        $this->assertSame('2133331234', $pessoa->telefone_fixo);
        $this->assertSame('1990-05-12', $pessoa->nascimento->format('Y-m-d'));
        $this->assertSame('RJ', $pessoa->uf);
        $this->assertSame('feminino', $pessoa->sexo);
    }

    public function test_dados_repetidos_voltam_409_com_os_campos(): void
    {
        $this->postJson('/api/v1/pessoas', $this->dados())->assertCreated();

        $this->postJson('/api/v1/pessoas', $this->dados())
            ->assertStatus(409)
            ->assertJsonPath('erro', 'duplicado')
            ->assertJsonStructure(['erro', 'message', 'campos' => ['email', 'cpf', 'login']]);

        $this->postJson('/api/v1/pessoas', $this->dados(['email' => 'outra@teste.com', 'cpf' => '11144477735']))
            ->assertStatus(409)
            ->assertJsonStructure(['campos' => ['login']])
            ->assertJsonMissingPath('campos.email');
    }

    public function test_dados_invalidos_voltam_422_com_os_campos(): void
    {
        $this->postJson('/api/v1/pessoas', $this->dados([
            'nome' => 'Ana', 'cpf' => '12345678900', 'login' => 'ana1', 'senha' => 'abc', 'telefone_celular' => '2199',
            'termos_aceitos' => false,
        ]))
            ->assertStatus(422)
            ->assertJsonPath('erro', 'validacao')
            ->assertJsonStructure(['message', 'campos' => ['nome', 'cpf', 'login', 'senha', 'telefone_celular', 'termos_aceitos']]);

        $this->postJson('/api/v1/pessoas', [])->assertStatus(422)->assertJsonPath('campos.email.0', 'O campo e-mail é obrigatório.');
    }

    public function test_senha_precisa_ser_forte(): void
    {
        // regra padrão (SENHA_REGRA=forte), a mesma do front
        $this->postJson('/api/v1/pessoas', $this->dados(['senha' => 'abcdefgh']))
            ->assertStatus(422)
            ->assertJsonPath('campos.senha.0', 'A senha precisa ter 8 ou mais caracteres, com letra maiúscula, minúscula e número.');
    }

    public function test_regra_do_enunciado_continua_disponivel(): void
    {
        config(['ecotech.senha_regra' => 'enunciado']);

        $this->postJson('/api/v1/pessoas', $this->dados(['senha' => 'abcd1234']))
            ->assertStatus(422)
            ->assertJsonPath('campos.senha.0', 'A senha deve ter exatamente 8 letras.');
        $this->postJson('/api/v1/pessoas', $this->dados(['senha' => 'abcdefgh']))->assertCreated();
    }

    public function test_cadastro_de_empresa(): void
    {
        $dados = ['cnpj' => '11.222.333/0001-81', 'nome_empresa' => 'Recicla Teste', 'email' => 'contato@recicla.com', 'senha' => 'Empresa123'];

        $this->postJson('/api/v1/empresas', $dados)
            ->assertCreated()
            ->assertJsonPath('empresa.cnpj', '11222333000181')
            ->assertJsonPath('empresa.cnpj_formatado', '11.222.333/0001-81');

        $this->postJson('/api/v1/empresas', $dados)->assertStatus(409)->assertJsonStructure(['campos' => ['cnpj', 'email']]);
        $this->postJson('/api/v1/empresas', ['cnpj' => '11222333000182'] + $dados)->assertStatus(422)->assertJsonStructure(['campos' => ['cnpj']]);
        $this->postJson('/api/v1/empresas', ['senha' => 'fraca'] + $dados)->assertStatus(422)->assertJsonStructure(['campos' => ['senha']]);
    }

    public function test_email_de_pessoa_nao_pode_ser_usado_por_empresa(): void
    {
        $this->criarPessoa(['email' => 'mesmo@teste.com']);

        $this->postJson('/api/v1/empresas', [
            'cnpj' => '11222333000181', 'nome_empresa' => 'Recicla Teste', 'email' => 'mesmo@teste.com', 'senha' => 'Empresa123',
        ])->assertStatus(409)->assertJsonStructure(['campos' => ['email']]);
    }
}
