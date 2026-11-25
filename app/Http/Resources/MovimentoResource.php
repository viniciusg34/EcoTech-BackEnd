<?php

namespace App\Http\Resources;

use App\Support\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Uma linha do extrato de moedas (GET /me/movimentos).
 */
class MovimentoResource extends JsonResource
{
    public const ICONES = [
        'checkin' => '📍',
        'entrega' => '♻️',
        'leitura' => '🏷️',
        'resgate' => '🎁',
        'estorno' => '↩️',
        'ajuste'  => '⚙️',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'tipo'                 => $this->tipo,
            'titulo'               => $this->descricao,
            'subtitulo'            => $this->subtitulo,
            'icone'                => $this->icone ?: (self::ICONES[$this->tipo] ?? '•'),
            'valor'                => (int) $this->valor,
            'valor_formatado'      => Formato::comSinal((int) $this->valor),
            'saldo_apos'           => (int) $this->saldo_apos,
            'saldo_apos_formatado' => Formato::inteiro($this->saldo_apos),
            'data'                 => Formato::iso($this->criado_em),
            'data_formatada'       => Formato::dataHora($this->criado_em),
        ];
    }
}
