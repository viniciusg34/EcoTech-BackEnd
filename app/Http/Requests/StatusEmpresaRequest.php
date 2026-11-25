<?php

namespace App\Http\Requests;

class StatusEmpresaRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['status' => ['required', 'in:ativa,suspensa']];
    }
}
