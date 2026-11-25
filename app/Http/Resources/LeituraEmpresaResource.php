<?php

namespace App\Http\Resources;

use App\Support\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Linha do histórico de leituras da empresa (CSV "Histórico de leituras").
 * LGPD: NENHUM dado de quem reciclou (sem pessoa, nome, CPF, e-mail nem sessão).
 * O arquivo é da empresa, não da pessoa.
 */
class LeituraEmpresaResource extends JsonResource
{
    public const RESULTADOS = [
        'creditado'     => 'Creditado',
        'duplicado'     => 'Duplicado',
        'nao_participa' => 'Fora da campanha',
        'invalido'      => 'Código inválido',
    ];

    public function toArray(Request $request): array
    {
        $produto = $this->produto;
        $creditado = $this->resultado === 'creditado';
        $kg = $creditado && $produto?->peso_kg ? round((float) $produto->peso_kg, 3) : null;

        return [
            'id'               => $this->id,
            'data'             => Formato::iso($this->lido_em),
            'data_formatada'   => Formato::dataHora($this->lido_em),
            'ecoponto_id'      => $this->ecoponto_id,
            'ecoponto_codigo'  => $this->ecoponto?->codigo,
            'ecoponto_nome'    => $this->ecoponto?->nome,
            'codigo_barras'    => $this->codigo_barras,
            'produto'          => $produto?->nome ?? "Código {$this->codigo_barras}",
            'categoria'        => $produto?->categoria?->rotulo ?? '—',
            'kg'               => $kg,
            'kg_formatado'     => $kg ? Formato::kg($kg) : '—',
            'coins'            => (int) $this->coins_creditados,
            'resultado'        => $this->resultado,
            'resultado_rotulo' => self::RESULTADOS[$this->resultado] ?? $this->resultado,
        ];
    }
}
