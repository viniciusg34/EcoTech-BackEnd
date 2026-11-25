<?php

namespace App\Http\Requests;

// POST /resgates  { recompensa_id }  + cabeçalho Idempotency-Key
class ResgatarRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['recompensa_id' => ['required', 'integer']];
    }
}
