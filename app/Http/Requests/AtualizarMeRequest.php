<?php

namespace App\Http\Requests;

// PUT /me  { nome, email, avatar_url }
class AtualizarMeRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->semEspacos(['nome']);
        $this->minusculo(['email']);
        if ($this->has('avatar_url') && $this->input('avatar_url') === '') {
            $this->merge(['avatar_url' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'nome'       => ['sometimes', 'required', 'string', 'between:15,80', "regex:/^[\p{L}' -]+$/u"],
            'email'      => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'avatar_url' => ['sometimes', 'nullable', 'string', 'url', 'max:400'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.between' => 'O nome deve ter entre 15 e 80 caracteres.',
            'nome.regex'   => 'O nome deve ter apenas letras.',
        ];
    }
}
