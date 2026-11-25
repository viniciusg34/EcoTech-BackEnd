<?php

namespace App\Http\Requests;

// POST /coletas  { sessao_id, itens: [ { categoria, quantidade } ] }
class ColetaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'sessao_id'           => ['required', 'integer'],
            'itens'               => ['required', 'array', 'min:1', 'max:20'],
            'itens.*.categoria'   => ['required', 'string', 'max:20'],
            'itens.*.quantidade'  => ['required', 'numeric', 'gt:0', 'max:1000'],
        ];
    }
}
