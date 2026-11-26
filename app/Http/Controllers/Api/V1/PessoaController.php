<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CadastrarPessoaRequest;
use App\Services\AuthService;
use App\Services\PessoaService;
use Illuminate\Http\JsonResponse;

class PessoaController extends Controller
{
    /*
     * POST /pessoas — cadastro. 201 sem token e com o código de confirmação já enviado:
     *   { status: "codigo_email", finalidade: "confirmar_email", desafio_id, email, ..., usuario }
     * Depois de confirmar (POST /auth/confirmar-email) a pessoa faz login.
     * 409 se e-mail, CPF ou login já existirem; 422 com "campos" se algo estiver inválido.
     */
    public function store(CadastrarPessoaRequest $request, PessoaService $pessoas, AuthService $auth): JsonResponse
    {
        $pessoa = $pessoas->cadastrar($request->validated());
        $confirmacao = $auth->pedirConfirmacaoEmail($pessoa);

        return response()->json([
            'message' => 'Cadastro realizado! ' . $confirmacao['message'],
        ] + $confirmacao + [
            'usuario' => [
                'id'     => $pessoa->id,
                'nome'   => $pessoa->nome,
                'email'  => $pessoa->email,
                'login'  => $pessoa->login,
                'tipo'   => 'pessoa',
                'perfil' => 'comum',
            ],
        ], 201);
    }
}
