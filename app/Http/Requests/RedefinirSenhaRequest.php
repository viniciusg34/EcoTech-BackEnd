<?php

namespace App\Http\Requests;

use App\Rules\SenhaValida;

class RedefinirSenhaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'token'           => ['required', 'string', 'max:255'],
            'nova_senha'      => ['required', 'string', new SenhaValida()],
            'confirmar_senha' => ['sometimes', 'nullable', 'same:nova_senha'],
        ];
    }
}
