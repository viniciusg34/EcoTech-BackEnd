<?php

namespace App\Http\Controllers\Api\V1;

use App\Clients\GoogleTokenClient;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DesafioRequest;
use App\Http\Requests\EsqueciSenhaRequest;
use App\Http\Requests\GoogleRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RedefinirSenhaRequest;
use App\Models\DesafioVerificacao;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Services\AuthService;
use App\Services\TwoFactorService;
use App\Support\Formato;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/*
 * /auth/... — login em duas etapas, Google, recuperação de senha e logout.
 */
class AuthController extends Controller
{
    public function __construct(private AuthService $auth, private TwoFactorService $doisFatores)
    {
    }

    // POST /auth/login  -> pessoa: { status: "2fa", desafio_id, pergunta, pergunta_tipo } | empresa: { token, usuario }
    public function login(LoginRequest $request): JsonResponse
    {
        return response()->json(
            $this->auth->login($request->identificador(), $request->input('senha'), $request->boolean('lembrar'))
        );
    }

    // POST /auth/2fa  -> { token, usuario }
    public function doisFatores(DesafioRequest $request): JsonResponse
    {
        $lembrar = $request->has('lembrar') && $request->input('lembrar') !== null ? $request->boolean('lembrar') : null;

        return response()->json(
            $this->auth->concluirDoisFatores($request->input('desafio_id'), $request->input('resposta'), $lembrar)
        );
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
     * POST /auth/esqueci-senha  { email }
     * SEMPRE responde com um desafio (mesmo se o e-mail não existir): não revela quem tem conta.
     */
    public function esqueciSenha(EsqueciSenhaRequest $request): JsonResponse
    {
        $email = $request->input('email');
        $pessoa = Pessoa::query()->where('email', $email)->where('ativo', true)->first();

        $desafio = $this->doisFatores->criarParaRecuperacao($email, $pessoa);
        if ($pessoa) {
            $this->auth->registrarLog($pessoa, null, 'recuperacao', true);
        }

        return response()->json($this->doisFatores->dadosPergunta($desafio) + [
            'message' => 'Responda a pergunta de segurança para redefinir a senha.',
        ]);
    }

    // POST /auth/esqueci-senha/verificar  { desafio_id, resposta }  -> { token } (vale 10 minutos)
    public function verificarRecuperacao(DesafioRequest $request): JsonResponse
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
            $desafio = DesafioVerificacao::query()
                ->where('token_reset_hash', hash('sha256', (string) $request->input('token')))
                ->where('finalidade', 'recuperar_senha')
                ->lockForUpdate()->first();

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
