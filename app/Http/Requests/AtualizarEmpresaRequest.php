<?php

namespace App\Http\Requests;

class AtualizarEmpresaRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->semEspacos(['nome_empresa', 'razao_social', 'endereco']);
        $this->minusculo(['email']);
    }

    public function rules(): array
    {
        return [
            'nome_empresa' => ['sometimes', 'required', 'string', 'between:2,150'],
            'razao_social' => ['sometimes', 'nullable', 'string', 'max:150'],
            'email'        => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'endereco'     => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
