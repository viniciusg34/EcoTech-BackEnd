<?php

namespace App\Http\Resources;

use App\Support\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProdutoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'codigo_barras'    => $this->codigo_barras,
            'nome'             => $this->nome,
            'categoria'        => $this->categoria?->chave,
            'categoria_rotulo' => $this->categoria?->rotulo,
            'coins'            => (int) $this->coins,
            'icone'            => $this->icone,
            'peso_kg'          => $this->peso_kg !== null ? (float) $this->peso_kg : null,
            'ativo'            => (bool) $this->ativo,
            'participa'        => $this->participa(),
            'off_nome'         => $this->off_nome,
            'off_marca'        => $this->off_marca,
            'off_imagem_url'   => $this->off_imagem_url,
            'off_consultado_em_formatado' => Formato::dataHora($this->off_consultado_em),
        ];
    }
}
