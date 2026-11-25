<?php

namespace App\Http\Requests;

use App\Rules\Cnpj;
use App\Rules\SenhaValida;

/*
 * POST /empresas — cadastro de empresa parceira (senha forte).
 */
class CadastrarEmpresaRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        // O front antigo mandava "password" e "name": aceitamos os dois nomes
        if (! $this->has('senha') && $this->has('password')) {
            $this->merge(['senha' => $this->input('password')]);
        }
        if (! $this->has('nome_empresa') && $this->has('name')) {
            $this->merge(['nome_empresa' => $this->input('name')]);
        }
        $this->semEspacos(['nome_empresa', 'razao_social', 'endereco']);
        $this->minusculo(['email']);
        $this->soDigitos(['cnpj']);
    }

    public function rules(): array
    {
        return [
            'cnpj'         => ['required', 'string', new Cnpj()],
            'nome_empresa' => ['required', 'string', 'between:2,150'],
            'razao_social' => ['nullable', 'string', 'max:150'],
            'email'        => ['required', 'string', 'email', 'max:255'],
            'senha'        => ['required', 'string', new SenhaValida('forte')],
            'endereco'     => ['nullable', 'string', 'max:255'],
        ];
    }
}
