<?php

namespace App\Http\Requests;

/*
 * POST /auth/codigo, /auth/confirmar-email e /auth/esqueci-senha/verificar
 *   { desafio_id, codigo, lembrar? }
 * Aceita o código com espaço ou traço ("123 456"); fica só com os dígitos.
 * Também aceita o campo antigo "resposta" no lugar de "codigo".
 */
class CodigoRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $codigo = $this->input('codigo', $this->input('resposta'));
        $this->merge(['codigo' => is_scalar($codigo) ? preg_replace('/\D/', '', (string) $codigo) : $codigo]);
    }

    public function rules(): array
    {
        return [
            'desafio_id' => ['required', 'string', 'uuid'],
            'codigo'     => ['required', 'string', 'digits:6'],
            'lembrar'    => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'desafio_id.required' => 'Código expirado. Comece de novo.',
            'desafio_id.uuid'     => 'Código expirado. Comece de novo.',
            'codigo.required'     => 'Digite o código de 6 dígitos que enviamos para o seu e-mail.',
            'codigo.digits'       => 'O código tem 6 números.',
        ];
    }

    public function codigo(): string
    {
        return (string) $this->input('codigo');
    }
}
