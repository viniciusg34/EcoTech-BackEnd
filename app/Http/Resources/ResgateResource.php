<?php

namespace App\Http\Resources;

use App\Support\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResgateResource extends JsonResource
{
    public const STATUS = ['emitido' => 'Emitido', 'utilizado' => 'Utilizado', 'cancelado' => 'Cancelado'];

    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'recompensa'      => $this->whenLoaded('recompensa', fn () => [
                'id'        => $this->recompensa->id,
                'nome'      => $this->recompensa->nome,
                'icone'     => $this->recompensa->icone,
                'categoria' => $this->recompensa->categoria,
            ]),
            'custo_coins'     => (int) $this->custo_coins,
            'custo_formatado' => Formato::inteiro($this->custo_coins),
            'codigo_cupom'    => $this->codigo_cupom,
            'status'          => $this->status,
            'status_rotulo'   => self::STATUS[$this->status] ?? $this->status,
            'data'            => Formato::iso($this->criado_em),
            'data_formatada'  => Formato::dataHora($this->criado_em),
        ];
    }
}
