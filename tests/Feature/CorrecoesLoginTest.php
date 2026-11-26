<?php

namespace Tests\Feature;

use App\Support\Texto;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/*
 * Correções de 26/11: datas e nomes no 2FA, código por e-mail desligado e envio pelo Brevo.
 */
class CorrecoesLoginTest extends TestCase
{
    private function loginAtePergunta(string $tipo): array
    {
        // Pessoa que só tem o dado da pergunta pedida (assim a pergunta sorteada é sempre essa)
        $dados = ['nome_mae' => null, 'nascimento' => null, 'cep' => null];
        $dados[$tipo] = ['nome_mae' => 'Maria-José D’Ávila', 'nascimento' => '1995-03-04', 'cep' => '20040020'][$tipo];
        $this->criarPessoa(['login' => 'anasil'] + $dados);

        return $this->postJson('/api/v1/auth/login', ['login' => 'anasil', 'senha' => 'abcdefgh'])
            ->assertOk()->assertJsonPath('pergunta_tipo', $tipo)->json();
    }

    public static function datasAceitas(): array
    {
        return [['04/03/1995'], ['4/3/1995'], ['4-3-1995'], ['04.03.1995'], ['04031995'], ['04/03/95'], ['1995-03-04']];
    }

    #[DataProvider('datasAceitas')]
    public function test_data_de_nascimento_aceita_varios_jeitos_de_digitar(string $resposta): void
    {
        $d = $this->loginAtePergunta('nascimento');
        $this->postJson('/api/v1/auth/2fa', ['desafio_id' => $d['desafio_id'], 'resposta' => $resposta])
            ->assertOk()->assertJsonPath('status', 'codigo_email');
    }

    public function test_data_errada_continua_errada(): void
    {
        $d = $this->loginAtePergunta('nascimento');
        $this->postJson('/api/v1/auth/2fa', ['desafio_id' => $d['desafio_id'], 'resposta' => '03/04/1995'])->assertStatus(422);
    }

    public static function nomesAceitos(): array
    {
        return [['MARIA-JOSÉ D’ÁVILA'], ['maria jose d avila'], ["  Maria  José  D'Ávila  "], ["Maria Jose\u{0301} D'Avila"], ['maria-jose d\'avila.']];
    }

    #[DataProvider('nomesAceitos')]
    public function test_nome_da_mae_nao_liga_para_maiuscula_acento_hifen_ou_apostrofo(string $resposta): void
    {
        $d = $this->loginAtePergunta('nome_mae');
        $this->postJson('/api/v1/auth/2fa', ['desafio_id' => $d['desafio_id'], 'resposta' => $resposta])
            ->assertOk()->assertJsonPath('status', 'codigo_email');
    }

    public function test_nome_diferente_continua_errado(): void
    {
        $d = $this->loginAtePergunta('nome_mae');
        $this->postJson('/api/v1/auth/2fa', ['desafio_id' => $d['desafio_id'], 'resposta' => 'Maria José'])->assertStatus(422);
    }

    public function test_texto_data_recusa_datas_que_nao_existem(): void
    {
        $this->assertNull(Texto::data('31/02/1995'));
        $this->assertNull(Texto::data('29/02/1999'));
        $this->assertSame('2000-02-29', Texto::data('29/2/2000'));
    }

    public function test_codigo_desligado_login_volta_a_ser_senha_e_pergunta(): void
    {
        config(['ecotech.codigo_email_ativo' => false]);
        $d = $this->loginAtePergunta('cep');
        $enviados = $this->emailsEnviados();

        $this->postJson('/api/v1/auth/2fa', ['desafio_id' => $d['desafio_id'], 'resposta' => '20040-020'])
            ->assertOk()->assertJsonStructure(['token', 'usuario']);
        $this->assertSame($enviados, $this->emailsEnviados());
    }

    public function test_codigo_desligado_empresa_entra_direto_e_cadastro_nao_pede_confirmacao(): void
    {
        config(['ecotech.codigo_email_ativo' => false]);
        $this->criarEmpresa(['email' => 'contato@recicla.com']);
        $this->postJson('/api/v1/auth/login', ['email' => 'contato@recicla.com', 'senha' => 'Empresa123'])
            ->assertOk()->assertJsonStructure(['token']);

        $this->postJson('/api/v1/pessoas', [
            'nome' => 'Ana Maria da Silva Souza', 'email' => 'ana@teste.com', 'cpf' => '529.982.247-25',
            'login' => 'anasou', 'senha' => 'Senha123', 'nome_mae' => 'Joana Pereira', 'nascimento' => '12/05/1990',
            'sexo' => 'feminino', 'telefone_celular' => '21999991234', 'cep' => '20040020', 'logradouro' => 'Rua da Assembleia',
            'numero' => '10', 'bairro' => 'Centro', 'cidade' => 'Rio de Janeiro', 'uf' => 'RJ', 'termos_aceitos' => true,
        ])->assertCreated()->assertJsonMissingPath('desafio_id');

        $this->postJson('/api/v1/auth/login', ['login' => 'anasou', 'senha' => 'Senha123'])->assertOk()->assertJsonPath('status', '2fa');
    }

    public function test_codigo_desligado_esqueci_a_senha_volta_para_a_pergunta(): void
    {
        config(['ecotech.codigo_email_ativo' => false]);
        $this->criarPessoa(['email' => 'ana@teste.com', 'nome_mae' => null, 'nascimento' => null]);

        $d = $this->postJson('/api/v1/auth/esqueci-senha', ['email' => 'ana@teste.com'])
            ->assertOk()->assertJsonPath('status', 'pergunta')->assertJsonPath('pergunta_tipo', 'cep')->json();

        $token = $this->postJson('/api/v1/auth/esqueci-senha/verificar', ['desafio_id' => $d['desafio_id'], 'resposta' => '20040-020'])
            ->assertOk()->json('token');
        $this->postJson('/api/v1/auth/redefinir-senha', ['token' => $token, 'nova_senha' => 'NovaSenha1'])->assertOk();
    }

    public function test_quem_ficou_sem_confirmar_entra_quando_o_codigo_e_desligado(): void
    {
        config(['ecotech.codigo_email_ativo' => false]);
        $this->criarPessoa(['login' => 'anasil', 'email_confirmado' => false]);
        $this->postJson('/api/v1/auth/login', ['login' => 'anasil', 'senha' => 'abcdefgh'])->assertOk()->assertJsonPath('status', '2fa');
    }

    public function test_brevo_tem_prioridade_e_recebe_o_remetente_certo(): void
    {
        config(['ecotech.brevo_key' => 'xkeysib-teste', 'ecotech.email_remetente' => 'EcoTech <souvinicius2004@gmail.com>']);
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'x'], 201)]);
        $antesResend = $this->emailsEnviados();

        $this->loginAtePergunta('cep');
        $d = $this->postJson('/api/v1/auth/login', ['login' => 'anasil', 'senha' => 'abcdefgh'])->json();
        $this->postJson('/api/v1/auth/2fa', ['desafio_id' => $d['desafio_id'], 'resposta' => '20040020'])
            ->assertOk()->assertJsonPath('status', 'codigo_email');

        $this->assertSame($antesResend, $this->emailsEnviados()); // não usou o Resend
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.brevo.com/v3/smtp/email')
            && $r->hasHeader('api-key', 'xkeysib-teste')
            && $r['sender'] === ['name' => 'EcoTech', 'email' => 'souvinicius2004@gmail.com']
            && $r['to'] === [['email' => 'pessoa' . substr($r['to'][0]['email'], 6)]]
            && preg_match('/Código: \d{6}/', $r['textContent']));
    }
}
