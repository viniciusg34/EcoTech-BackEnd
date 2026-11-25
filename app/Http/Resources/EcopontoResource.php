<?php

namespace App\Http\Resources;

use App\Support\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Ecoponto como o mapa espera (item da lista e detalhe).
 */
class EcopontoResource extends JsonResource
{
    public const STATUS = [
        'ativo'      => ['rotulo' => 'Ativo', 'cor' => '#2e7d32'],
        'manutencao' => ['rotulo' => 'Em manutenção', 'cor' => '#f9a825'],
        'inativo'    => ['rotulo' => 'Inativo', 'cor' => '#9e9e9e'],
    ];

    public function toArray(Request $request): array
    {
        $kg = (float) ($this->kg_coletado ?? 0);

        return [
            'id'                    => $this->id,
            'codigo'                => $this->codigo,
            'nome'                  => $this->nome,
            'descricao'             => $this->descricao,
            'endereco'              => $this->endereco,
            'cidade'                => $this->cidade,
            'estado'                => $this->estado,
            'lat'                   => (float) $this->lat,
            'lng'                   => (float) $this->lng,
            'status'                => $this->status,
            'status_rotulo'         => self::STATUS[$this->status]['rotulo'] ?? $this->status,
            'status_cor'            => self::STATUS[$this->status]['cor'] ?? '#9e9e9e',
            'categorias'            => CategoriaResource::collection($this->whenLoaded('categorias', fn () => $this->categorias, collect()))->resolve(),
            'kg_coletado'           => round($kg, 2),
            'kg_coletado_formatado' => Formato::kg($kg),
            'empresa'               => $this->whenLoaded('empresa', fn () => [
                'id'   => $this->empresa->id,
                'nome' => $this->empresa->nome_empresa,
            ]),
        ];
    }
}
