<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/*
 * PATCH /admin/empresas/{id} — o master edita os dados da empresa e/ou o status.
 * Só os campos enviados mudam. O CNPJ não muda (identifica a empresa).
 */
class AdminEmpresaRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->semEspacos(['nome_empresa', 'razao_social', 'endereco']);
        $this->minusculo(['email']);
    }

    public function rules(): array
    {
        $empresa = $this->route('empresa');

        return [
            'nome_empresa' => ['sometimes', 'required', 'string', 'between:2,150'],
            'razao_social' => ['sometimes', 'nullable', 'string', 'max:150'],
            'email'        => ['sometimes', 'required', 'string', 'email', 'max:255',
                Rule::unique('empresa', 'email')->ignore($empresa?->id)],
            'endereco'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'status'       => ['sometimes', 'required', 'in:ativa,suspensa'],
        ];
    }

    public function messages(): array
    {
        return ['email.unique' => 'Já existe uma empresa com este e-mail.'];
    }
}
