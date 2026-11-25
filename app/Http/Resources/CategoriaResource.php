<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoriaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'chave'             => $this->chave,
            'rotulo'            => $this->rotulo,
            'cor_hex'           => $this->cor_hex,
            'icone'             => $this->icone,
            'unidade'           => $this->unidade,
            'coins_por_unidade' => (int) $this->coins_por_unidade,
            'coins_rotulo'      => "{$this->coins_por_unidade} coins por " . ($this->unidade === 'kg' ? 'kg' : 'unidade'),
        ];
    }
}
