<?php

namespace App\Http\Requests;

// POST /auth/2fa e /auth/esqueci-senha/verificar  { desafio_id, resposta, lembrar? }
class DesafioRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'desafio_id' => ['required', 'string', 'uuid'],
            'resposta'   => ['required', 'string', 'max:150'],
            'lembrar'    => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'desafio_id.uuid' => 'Desafio inválido ou expirado. Faça login de novo.',
            'resposta.required' => 'Digite a resposta da pergunta de segurança.',
        ];
    }
}
