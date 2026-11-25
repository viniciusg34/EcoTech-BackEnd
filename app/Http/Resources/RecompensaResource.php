<?php

namespace App\Http\Resources;

use App\Support\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecompensaResource extends JsonResource
{
    public const CATEGORIAS = [
        'food'      => 'Alimentação',
        'transport' => 'Transporte',
        'health'    => 'Saúde',
        'shop'      => 'Compras',
        'leisure'   => 'Lazer',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'categoria'        => $this->categoria,
            'categoria_rotulo' => self::CATEGORIAS[$this->categoria] ?? $this->categoria,
            'icone'            => $this->icone,
            'nome'             => $this->nome,
            'descricao'        => $this->descricao,
            'custo_coins'      => (int) $this->custo_coins,
            'custo_formatado'  => Formato::inteiro($this->custo_coins),
            'estoque'          => $this->estoque,
            'disponivel'       => (bool) $this->ativa && ($this->estoque === null || $this->estoque > 0),
            'ativa'            => (bool) $this->ativa,
        ];
    }
}
