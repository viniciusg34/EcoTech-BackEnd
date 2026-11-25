<?php

namespace App\Http\Requests;

// POST /ecopontos/conectar  { qr: "ECO-01|emp=1|ep=3|sig=..." }
class ConectarRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['qr' => ['required', 'string', 'max:500']];
    }
}
