<?php

namespace App\Http\Requests;

class EsqueciSenhaRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->minusculo(['email']);
    }

    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255']];
    }
}
