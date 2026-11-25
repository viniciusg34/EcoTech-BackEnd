<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CadastrarPessoaRequest;
use App\Services\PessoaService;
use Illuminate\Http\JsonResponse;

class PessoaController extends Controller
{
    /*
     * POST /pessoas — cadastro. 201 sem token: depois do cadastro a pessoa faz login (com 2FA).
     * 409 se e-mail, CPF ou login já existirem; 422 com "campos" se algo estiver inválido.
     */
    public function store(CadastrarPessoaRequest $request, PessoaService $pessoas): JsonResponse
    {
        $pessoa = $pessoas->cadastrar($request->validated());

        return response()->json([
            'message' => 'Cadastro realizado! Faça login para entrar.',
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
