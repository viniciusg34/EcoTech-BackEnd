<?php

namespace App\Http\Resources;

use App\Support\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ColetaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'tipo'                  => $this->tipo,
            'tipo_rotulo'           => $this->tipo === 'checkin' ? 'Check-in' : 'Entrega',
            'ecoponto'              => $this->whenLoaded('ecoponto', fn () => [
                'id'     => $this->ecoponto->id,
                'codigo' => $this->ecoponto->codigo,
                'nome'   => $this->ecoponto->nome,
            ]),
            'total_coins'           => (int) $this->total_coins,
            'total_coins_formatado' => Formato::comSinal((int) $this->total_coins),
            'total_kg'              => round((float) $this->total_kg, 2),
            'kg_formatado'          => Formato::kg($this->total_kg),
            'itens'                 => $this->whenLoaded('itens', fn () => $this->itens->map(fn ($item) => [
                'categoria'            => $item->categoria?->chave,
                'rotulo'               => $item->categoria?->rotulo,
                'cor_hex'              => $item->categoria?->cor_hex,
                'unidade'              => $item->categoria?->unidade,
                'quantidade'           => (float) $item->quantidade,
                'quantidade_formatada' => $item->categoria?->unidade === 'un'
                    ? Formato::inteiro($item->quantidade) . ' un.'
                    : Formato::kg($item->quantidade),
                'peso_kg'              => (float) $item->peso_kg,
                'coins'                => (int) $item->coins,
                'produto_codigo'       => $item->produto_codigo,
            ])->all()),
            'data'                  => Formato::iso($this->criado_em),
            'data_formatada'        => Formato::dataHora($this->criado_em),
        ];
    }
}
