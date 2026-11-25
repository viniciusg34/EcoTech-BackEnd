<?php

namespace App\Http\Requests;

use App\Models\Empresa;
use App\Rules\SenhaValida;

// PUT /me/senha e PUT /empresas/me/senha  { senha_atual, nova_senha }
class AlterarSenhaRequest extends ApiRequest
{
    public function rules(): array
    {
        $regra = $this->user() instanceof Empresa ? new SenhaValida('forte') : new SenhaValida();

        return [
            'senha_atual'     => ['required', 'string', 'max:255'],
            'nova_senha'      => ['required', 'string', 'different:senha_atual', $regra],
            'confirmar_senha' => ['sometimes', 'nullable', 'same:nova_senha'],
        ];
    }

    public function messages(): array
    {
        return [
            'nova_senha.different' => 'A nova senha precisa ser diferente da atual.',
            'confirmar_senha.same' => 'As senhas não coincidem.',
        ];
    }
}
