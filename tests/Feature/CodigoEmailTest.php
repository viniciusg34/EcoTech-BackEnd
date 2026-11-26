<?php

namespace Tests\Feature;

use App\Models\CodigoEmail;
use App\Models\Pessoa;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
 * Código de 6 dígitos por e-mail: cadastro, login, reenvio, bloqueio e falhas de envio.
 */
class CodigoEmailTest extends TestCase
{
    private function dadosCadastro(array $troca = []): array
    {
        return array_merge([
            'nome' => 'Ana Maria da Silva Souza', 'email' => 'ana@teste.com', 'cpf' => '529.982.247-25',
            'login' => 'anasil', 'senha' => 'Senha123', 'nome_mae' => 'Joana Pereira', 'nascimento' => '1990-05-12',
            'sexo' => 'feminino', 'telefone_celular' => '21999991234',
            'cep' => '20040020', 'logradouro' => 'Rua da Assembleia', 'numero' => '10', 'bairro' => 'Centro',
            'cidade' => 'Rio de Janeiro', 'uf' => 'RJ', 'termos_aceitos' => true,
        ], $troca);
    }

    // Faz login até a etapa do código e devolve o desafio_id do código
    private function ateOCodigo(string $login = 'anasil', string $senha = 'abcdefgh'): string
    {
        $pergunta = $this->postJson('/api/v1/auth/login', ['login' => $login, 'senha' => $senha])->assertOk()->json();
        $resposta = ['nome_mae' => 'Maria José da Silva', 'nascimento' => '12/05/1990', 'cep' => '20040020'][$pergunta['pergunta_tipo']];

        return $this->postJson('/api/v1/auth/2fa', ['desafio_id' => $pergunta['desafio_id'], 'resposta' => $resposta])
            ->assertOk()->assertJsonPath('status', 'codigo_email')->json('desafio_id');
    }

    public function test_cadastro_envia_codigo_e_so_depois_de_confirmar_a_pessoa_consegue_entrar(): void
    {
        $cadastro = $this->postJson('/api/v1/pessoas', $this->dadosCadastro())
            ->assertCreated()
            ->assertJsonPath('status', 'codigo_email')
            ->assertJsonPath('finalidade', 'confirmar_email')
            ->assertJsonPath('email', 'an***@teste.com')
            ->assertJsonPath('email_enviado', true)
            ->assertJsonPath('usuario.email', 'ana@teste.com')
            ->assertJsonMissingPath('token')
            ->json();

        $this->assertFalse(Pessoa::query()->first()->email_confirmado);

        // Antes de confirmar: a senha certa só reenvia a confirmação (não chega na pergunta)
        $deNovo = $this->postJson('/api/v1/auth/login', ['login' => 'anasil', 'senha' => 'Senha123'])
            ->assertOk()->assertJsonPath('status', 'codigo_email')->assertJsonPath('finalidade', 'confirmar_email')->json();
        $this->assertNotSame($cadastro['desafio_id'], $deNovo['desafio_id']);

        $this->postJson('/api/v1/auth/confirmar-email', ['desafio_id' => $deNovo['desafio_id'], 'codigo' => $this->ultimoCodigo('ana@teste.com')])
            ->assertOk()->assertJsonPath('message', 'E-mail confirmado! Agora faça login para entrar.');

        $this->assertTrue(Pessoa::query()->first()->email_confirmado);
        $this->postJson('/api/v1/auth/login', ['login' => 'anasil', 'senha' => 'Senha123'])->assertOk()->assertJsonPath('status', '2fa');
    }

    public function test_codigo_de_confirmacao_nao_serve_para_login_e_vice_versa(): void
    {
        $this->criarPessoa(['login' => 'anasil']);
        $login = $this->ateOCodigo();

        $this->postJson('/api/v1/auth/confirmar-email', ['desafio_id' => $login, 'codigo' => $this->ultimoCodigo()])
            ->assertStatus(410);
        $this->postJson('/api/v1/auth/esqueci-senha/verificar', ['desafio_id' => $login, 'codigo' => $this->ultimoCodigo()])
            ->assertStatus(410);
    }

    public function test_aceita_codigo_com_espaco_ou_traco(): void
    {
        $this->criarPessoa(['login' => 'anasil']);
        $desafio = $this->ateOCodigo();
        $codigo = $this->ultimoCodigo();

        $this->postJson('/api/v1/auth/codigo', ['desafio_id' => $desafio, 'codigo' => substr($codigo, 0, 3) . ' - ' . substr($codigo, 3)])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_cinco_codigos_errados_bloqueiam(): void
    {
        $pessoa = $this->criarPessoa(['login' => 'anasil']);
        $desafio = $this->ateOCodigo();
        $certo = $this->ultimoCodigo();
        $errado = $certo === '000000' ? '111111' : '000000';

        for ($restam = 4; $restam >= 1; $restam--) {
            $this->postJson('/api/v1/auth/codigo', ['desafio_id' => $desafio, 'codigo' => $errado])
                ->assertStatus(422)->assertJsonPath('erro', 'codigo_invalido')->assertJsonPath('tentativas_restantes', $restam);
        }
        $this->postJson('/api/v1/auth/codigo', ['desafio_id' => $desafio, 'codigo' => $errado])
            ->assertStatus(423)->assertJsonPath('erro', 'codigo_bloqueado');

        // Nem o código certo passa depois do bloqueio
        $this->postJson('/api/v1/auth/codigo', ['desafio_id' => $desafio, 'codigo' => $certo])->assertStatus(423);

        $this->assertSame(5, \App\Models\LogAutenticacao::query()
            ->where('pessoa_id', $pessoa->id)->where('evento', 'codigo_email')->where('sucesso', false)->count());
    }

    public function test_codigo_expira_em_10_minutos(): void
    {
        $this->criarPessoa(['login' => 'anasil']);
        $desafio = $this->ateOCodigo();

        $this->travel(11)->minutes();
        $this->postJson('/api/v1/auth/codigo', ['desafio_id' => $desafio, 'codigo' => $this->ultimoCodigo()])
            ->assertStatus(410)->assertJsonPath('erro', 'codigo_expirado')->assertJsonPath('pode_reenviar', true);
    }

    public function test_reenviar_espera_60_segundos_e_o_codigo_antigo_deixa_de_valer(): void
    {
        $this->criarPessoa(['login' => 'anasil']);
        $desafio = $this->ateOCodigo();
        $antigo = $this->ultimoCodigo();

        $this->postJson('/api/v1/auth/codigo/reenviar', ['desafio_id' => $desafio])
            ->assertStatus(429)->assertJsonPath('erro', 'aguarde_reenvio');

        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/codigo/reenviar', ['desafio_id' => $desafio])
            ->assertOk()->assertJsonPath('status', 'codigo_email')->assertJsonPath('desafio_id', $desafio);

        $novo = $this->ultimoCodigo();
        if ($novo !== $antigo) {
            $this->postJson('/api/v1/auth/codigo', ['desafio_id' => $desafio, 'codigo' => $antigo])->assertStatus(422);
        }
        $this->postJson('/api/v1/auth/codigo', ['desafio_id' => $desafio, 'codigo' => $novo])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_reenviar_tem_limite(): void
    {
        $this->criarPessoa(['login' => 'anasil']);
        $desafio = $this->ateOCodigo();

        for ($i = 0; $i < 5; $i++) {
            $this->travel(61)->seconds();
            $this->postJson('/api/v1/auth/codigo/reenviar', ['desafio_id' => $desafio])->assertOk();
        }
        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/codigo/reenviar', ['desafio_id' => $desafio])
            ->assertStatus(429)->assertJsonPath('erro', 'limite_reenvios');
    }

    public function test_o_codigo_nunca_fica_salvo_em_texto(): void
    {
        $this->criarPessoa(['login' => 'anasil']);
        $this->ateOCodigo();

        $linha = CodigoEmail::query()->first();
        $this->assertNotSame($this->ultimoCodigo(), $linha->codigo_hash);
        $this->assertSame(64, strlen($linha->codigo_hash));
        $this->assertArrayNotHasKey('codigo_hash', $linha->toArray());
    }

    public function test_falha_no_envio_do_email_no_login_responde_502(): void
    {
        $this->criarPessoa(['login' => 'anasil']);
        $this->respostaResend = [['message' => 'domínio não verificado'], 403];

        $pergunta = $this->postJson('/api/v1/auth/login', ['login' => 'anasil', 'senha' => 'abcdefgh'])->json();
        $resposta = ['nome_mae' => 'Maria José da Silva', 'nascimento' => '12/05/1990', 'cep' => '20040020'][$pergunta['pergunta_tipo']];

        $this->postJson('/api/v1/auth/2fa', ['desafio_id' => $pergunta['desafio_id'], 'resposta' => $resposta])
            ->assertStatus(502)->assertJsonPath('erro', 'email_nao_enviado');
    }

    public function test_falha_no_envio_do_email_no_cadastro_ainda_cria_a_conta(): void
    {
        $this->respostaResend = [['message' => 'erro'], 500];

        $this->postJson('/api/v1/pessoas', $this->dadosCadastro())
            ->assertCreated()
            ->assertJsonPath('email_enviado', false)
            ->assertJsonPath('finalidade', 'confirmar_email');

        $this->assertSame(1, Pessoa::query()->count());
    }

    public function test_sem_chave_do_resend_o_codigo_vai_para_o_log_e_nada_e_enviado(): void
    {
        config(['ecotech.resend_key' => null]);
        $this->criarPessoa(['login' => 'anasil']);
        $antes = $this->emailsEnviados();

        $this->ateOCodigo();
        $this->assertSame($antes, $this->emailsEnviados());
    }

    public function test_google_confirma_o_email_de_quem_ainda_nao_confirmou(): void
    {
        config(['ecotech.google_client_id' => 'cliente-teste.apps.googleusercontent.com']);
        $pessoa = $this->criarPessoa(['email' => 'ana@gmail.com', 'email_confirmado' => false]);
        Http::fake(['oauth2.googleapis.com/*' => Http::response([
            'aud' => 'cliente-teste.apps.googleusercontent.com', 'iss' => 'https://accounts.google.com',
            'exp' => (string) (time() + 3600), 'email' => 'ana@gmail.com', 'email_verified' => 'true',
            'name' => 'Ana Maria da Silva', 'sub' => '123456',
        ])]);

        $this->postJson('/api/v1/auth/google', ['credential' => 'token'])->assertOk()->assertJsonPath('status', '2fa');
        $this->assertTrue($pessoa->fresh()->email_confirmado);
    }
}
