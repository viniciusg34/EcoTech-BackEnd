<?php

namespace App\Http\Requests;

// Master: ajuste manual de saldo  { valor (positivo ou negativo), descricao }
class AjusteRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'valor'     => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
            'descricao' => ['required', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return ['valor.not_in' => 'O ajuste não pode ser zero.'];
    }
}
