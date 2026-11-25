<?php

namespace App\Http\Requests;

class GoogleRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'credential' => ['required', 'string', 'max:8192'],
            'lembrar'    => ['sometimes', 'boolean'],
        ];
    }
}
