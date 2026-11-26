<?php

namespace App\Http\Controllers\Api\V1;

use App\Clients\GoogleTokenClient;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CodigoRequest;
use App\Http\Requests\DesafioRequest;
use App\Http\Requests\EsqueciSenhaRequest;
use App\Http\Requests\GoogleRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ReenviarCodigoRequest;
use App\Http\Requests\RedefinirSenhaRequest;
use App\Models\CodigoEmail;
use App\Models\DesafioVerificacao;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Services\AuthService;
use App\Services\CodigoEmailService;
use App\Services\TwoFactorService;
use App\Support\Formato;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/*
 * /auth/... — login (senha -> pergunta -> código no e-mail), Google, confirmação de e-mail,
 * recuperação de senha (código no e-mail) e logout.
 */
class AuthController extends Controller
{
    public function __construct(
        private AuthService $auth,
        private TwoFactorService $doisFatores,
        private CodigoEmailService $codigos,
    ) {
    }

    /*
     * POST /auth/login
     *   pessoa  -> { status: "2fa", desafio_id, pergunta, pergunta_tipo }
     *              ou { status: "codigo_email", finalidade: "confirmar_email", ... } se o e-mail não foi confirmado
     *   empresa -> { status: "codigo_email", finalidade: "login", desafio_id, email, ... }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        return response()->json(
            $this->auth->login($request->identificador(), $request->input('senha'), $request->boolean('lembrar'))
        );
    }

    // POST /auth/2fa  { desafio_id, resposta }  -> { status: "codigo_email", finalidade: "login", desafio_id, ... }
    public function doisFatores(DesafioRequest $request): JsonResponse
    {
        $lembrar = $request->has('lembrar') && $request->input('lembrar') !== null ? $request->boolean('lembrar') : null;

        return response()->json(
            $this->auth->concluirDoisFatores($request->input('desafio_id'), $request->input('resposta'), $lembrar)
        );
    }

    // POST /auth/codigo  { desafio_id, codigo }  -> { token, usuario }  (última etapa do login)
    public function codigo(CodigoRequest $request): JsonResponse
    {
        $lembrar = $request->has('lembrar') && $request->input('lembrar') !== null ? $request->boolean('lembrar') : null;

        return response()->json(
            $this->auth->concluirCodigo($request->input('desafio_id'), $request->codigo(), $lembrar)
        );
    }

    // POST /auth/codigo/reenviar  { desafio_id }  -> mesmos dados, com prazos novos (serve para as 3 finalidades)
    public function reenviarCodigo(ReenviarCodigoRequest $request): JsonResponse
    {
        $desafio = $this->codigos->reenviar($request->input('desafio_id'));

        return response()->json($this->codigos->dados($desafio)
            + ['message' => "Enviamos um código novo para {$this->codigos->mascarar($desafio->email)}."]);
    }

    // POST /auth/confirmar-email  { desafio_id, codigo }  -> { message }  (depois vai para o login)
    public function confirmarEmail(CodigoRequest $request): JsonResponse
    {
        $this->auth->confirmarEmail($request->input('desafio_id'), $request->codigo());

        return response()->json(['message' => 'E-mail confirmado! Agora faça login para entrar.']);
    }

    /*
     * POST /auth/google  { credential }
     * Conta encontrada pelo e-mail -> segue para o 2FA, igual ao login com senha.
     * Sem conta -> { status: "cadastro_pendente", nome, email } para completar o cadastro.
     */
    public function google(GoogleRequest $request, GoogleTokenClient $google): JsonResponse
    {
        $dados = $google->verificar($request->input('credential'));

        // Google é só para o usuário comum: empresa e master entram com login e senha
        $soComum = 'O login com Google é só para usuários comuns. Entre com seu e-mail e senha.';
        if (Empresa::query()->where('email', $dados['email'])->exists()) {
            throw new ApiException(403, 'google_so_usuario_comum', $soComum);
        }
        $pessoa = Pessoa::query()->where('email', $dados['email'])->where('ativo', true)->first();
        if ($pessoa && $pessoa->perfil !== 'comum') {
            throw new ApiException(403, 'google_so_usuario_comum', $soComum);
        }

        if (! $pessoa) {
            return response()->json([
                'status'  => 'cadastro_pendente',
                'nome'    => $dados['nome'],
                'email'   => $dados['email'],
                'message' => 'Complete seu cadastro para entrar com o Google.',
            ]);
        }

        // O Google já garante que o e-mail é dessa pessoa (email_verified)
        if (! $pessoa->email_confirmado) {
            $pessoa->forceFill(['email_confirmado' => true])->save();
        }

        $desafio = $this->doisFatores->criarParaLogin($pessoa, $request->boolean('lembrar'));
        $this->auth->registrarLog($pessoa, null, 'google', true);

        return response()->json(['status' => '2fa'] + $this->doisFatores->dadosPergunta($desafio)
            + ['message' => 'Responda a pergunta de segurança para entrar.']);
    }

    // POST /auth/logout  -> revoga o token usado nesta chamada
    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());
        return response()->json(['message' => 'Você saiu da sua conta.']);
    }

    /*
     * POST /auth/esqueci-senha  { email }  -> { status: "codigo_email", finalidade: "recuperar_senha", desafio_id, email, ... }
     * SEMPRE responde igual (mesmo se o e-mail não existir): não revela quem tem conta.
     * Só manda o e-mail de verdade quando a conta existe.
     */
    public function esqueciSenha(EsqueciSenhaRequest $request): JsonResponse
    {
        $email = $request->input('email');
        $pessoa = Pessoa::query()->where('email', $email)->where('ativo', true)->first();

        // Código por e-mail desligado: volta para a pergunta de segurança
        if (! CodigoEmailService::ativo()) {
            $pergunta = $this->doisFatores->criarParaRecuperacao($email, $pessoa);
            if ($pessoa) {
                $this->auth->registrarLog($pessoa, null, 'recuperacao', true);
            }

            return response()->json(['status' => 'pergunta'] + $this->doisFatores->dadosPergunta($pergunta)
                + ['message' => 'Responda a pergunta de segurança para redefinir a senha.']);
        }

        $desafio = $this->codigos->criarOuFalhar($pessoa, $email, 'recuperar_senha');
        if ($pessoa) {
            $this->auth->registrarLog($pessoa, null, 'recuperacao', true);
        }

        return response()->json($this->codigos->dados($desafio) + [
            'message' => 'Se este e-mail tiver conta, você vai receber um código de 6 dígitos.',
        ]);
    }

    /*
     * POST /auth/esqueci-senha/verificar -> { token } (vale 10 minutos)
     *   { desafio_id, codigo }    código por e-mail
     *   { desafio_id, resposta }  pergunta de segurança (quando CODIGO_EMAIL_ATIVO=false)
     */
    public function verificarRecuperacao(Request $request): JsonResponse
    {
        if (DesafioVerificacao::query()->where('uuid', (string) $request->input('desafio_id'))->exists()) {
            return $this->verificarRecuperacaoPorPergunta(app(DesafioRequest::class));
        }

        $request = app(CodigoRequest::class);
        $desafio = $this->codigos->verificar(
            $request->input('desafio_id'),
            'recuperar_senha',
            $request->codigo(),
            aoErrar: fn (CodigoEmail $d) => $d->pessoa && $this->auth->registrarLog(
                $d->pessoa, null, 'recuperacao', false, CodigoEmailService::METODO_LOG, $d->tentativas
            ),
        );

        $token = Str::random(64);
        $desafio->forceFill([
            'token_reset_hash'      => hash('sha256', $token),
            'token_reset_expira_em' => now()->addMinutes(10),
        ])->save();

        return response()->json([
            'token'     => $token,
            'expira_em' => Formato::iso($desafio->token_reset_expira_em),
            'message'   => 'Código certo! Agora crie a nova senha.',
        ]);
    }

    private function verificarRecuperacaoPorPergunta(DesafioRequest $request): JsonResponse
    {
        $desafio = $this->doisFatores->verificar(
            $request->input('desafio_id'),
            'recuperar_senha',
            $request->input('resposta'),
            aoErrar: fn (DesafioVerificacao $d) => $d->pessoa && $this->auth->registrarLog(
                $d->pessoa, null, 'recuperacao', false, TwoFactorService::PERGUNTAS[$d->pergunta], $d->tentativas
            ),
        );

        $token = Str::random(64);
        $desafio->forceFill([
            'token_reset_hash'      => hash('sha256', $token),
            'token_reset_expira_em' => now()->addMinutes(10),
        ])->save();

        return response()->json([
            'token'     => $token,
            'expira_em' => Formato::iso($desafio->token_reset_expira_em),
            'message'   => 'Resposta certa! Agora crie a nova senha.',
        ]);
    }

    // POST /auth/redefinir-senha  { token, nova_senha }
    public function redefinirSenha(RedefinirSenhaRequest $request): JsonResponse
    {
        $resultado = DB::transaction(function () use ($request) {
            // O token pode ter vindo do código por e-mail ou da pergunta de segurança
            $hash = hash('sha256', (string) $request->input('token'));
            $desafio = CodigoEmail::query()->where('token_reset_hash', $hash)->where('finalidade', 'recuperar_senha')->lockForUpdate()->first()
                ?? DesafioVerificacao::query()->where('token_reset_hash', $hash)->where('finalidade', 'recuperar_senha')->lockForUpdate()->first();

            if (! $desafio || $desafio->token_reset_usado_em || ! $desafio->token_reset_expira_em?->isFuture() || ! $desafio->pessoa) {
                return null;
            }

            $pessoa = $desafio->pessoa;
            $pessoa->forceFill(['senha_hash' => Hash::make($request->input('nova_senha'))])->save();
            $desafio->forceFill(['token_reset_usado_em' => now()])->save();
            $pessoa->tokens()->delete(); // sai de todos os aparelhos

            return $pessoa;
        });

        if (! $resultado) {
            throw new ApiException(410, 'token_expirado', 'O código para redefinir a senha é inválido ou expirou. Comece de novo.');
        }

        $this->auth->registrarLog($resultado, null, 'redefinir_senha', true);

        return response()->json(['message' => 'Senha alterada! Faça login com a nova senha.']);
    }
}
