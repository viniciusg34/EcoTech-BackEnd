<?php

namespace App\Http\Requests;

// Master: criar (POST) / editar (PUT e PATCH) recompensa
class RecompensaRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->semEspacos(['nome', 'descricao']);
    }

    public function rules(): array
    {
        $obrigatorio = $this->isMethod('PATCH') ? ['sometimes', 'required'] : ['required'];

        return [
            'categoria'   => [...$obrigatorio, 'in:food,transport,health,shop,leisure'],
            'icone'       => ['sometimes', 'nullable', 'string', 'max:10'],
            'nome'        => [...$obrigatorio, 'string', 'max:150'],
            'descricao'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'custo_coins' => [...$obrigatorio, 'integer', 'min:1', 'max:1000000'],
            'estoque'     => ['sometimes', 'nullable', 'integer', 'min:0'],
            'ativa'       => ['sometimes', 'boolean'],
        ];
    }
}
