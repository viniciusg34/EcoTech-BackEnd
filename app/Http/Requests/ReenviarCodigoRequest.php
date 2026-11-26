<?php

namespace App\Http\Requests;

// POST /auth/codigo/reenviar  { desafio_id }
class ReenviarCodigoRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['desafio_id' => ['required', 'string', 'uuid']];
    }

    public function messages(): array
    {
        return ['desafio_id.uuid' => 'Código expirado. Comece de novo.'];
    }
}
