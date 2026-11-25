<?php

namespace App\Http\Requests;

// POST /checkins  { sessao_id }
class SessaoRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['sessao_id' => ['required', 'integer']];
    }
}
