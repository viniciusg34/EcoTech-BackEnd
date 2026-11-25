<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

// POST /auth/login  { email OU login, senha, lembrar }
class LoginRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email'   => ['nullable', 'string', 'max:255'],
            'login'   => ['nullable', 'string', 'max:255'],
            'senha'   => ['required', 'string', 'max:255'],
            'lembrar' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $this->filled('email') && ! $this->filled('login')) {
                $validator->errors()->add('email', 'Informe o e-mail ou o login.');
            }
        }];
    }

    public function identificador(): string
    {
        return (string) ($this->input('email') ?: $this->input('login'));
    }
}
