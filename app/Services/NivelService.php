<?php

namespace App\Services;

use App\Models\Pessoa;
use App\Support\Formato;

/*
 * NÍVEL E IMPACTO DA PESSOA (antes eram calculados no navegador).
 * Regras copiadas do código antigo do front (perfil.html).
 */
class NivelService
{
    public const NIVEIS = [
        ['nome' => 'Iniciante Verde',  'min' => 0],
        ['nome' => 'Reciclador Prata', 'min' => 500],
        ['nome' => 'Guardião Verde',   'min' => 1500],
        ['nome' => 'Mestre Eco',       'min' => 3000],
    ];

    public function indice(int $coins): int
    {
        $indice = 0;
        foreach (self::NIVEIS as $i => $nivel) {
            if ($coins >= $nivel['min']) {
                $indice = $i;
            }
        }
        return $indice;
    }

    public function indicePorNome(?string $nome): ?int
    {
        foreach (self::NIVEIS as $i => $nivel) {
            if ($nivel['nome'] === $nome) {
                return $i;
            }
        }
        return null;
    }

    /*
     * { nome, meta, progresso (0 a 100), subiu }
     * subiu = true só na PRIMEIRA leitura depois de subir de nível (dispara o confete no front).
     */
    public function nivel(Pessoa $pessoa, bool $registrarVisto = true): array
    {
        $coins = max(0, (int) $pessoa->saldo_coins);
        $indice = $this->indice($coins);
        $atual = self::NIVEIS[$indice];
        $proximo = self::NIVEIS[$indice + 1] ?? null;

        $visto = $this->indicePorNome($pessoa->nivel_visto);
        $subiu = $visto !== null && $indice > $visto;

        if ($registrarVisto && $pessoa->nivel_visto !== $atual['nome']) {
            Pessoa::query()->whereKey($pessoa->id)->update(['nivel_visto' => $atual['nome']]);
            $pessoa->nivel_visto = $atual['nome'];
        }

        if (! $proximo) {
            return ['nome' => $atual['nome'], 'meta' => 'nível máximo alcançado 🏆', 'progresso' => 100, 'subiu' => $subiu];
        }

        $progresso = (int) floor((($coins - $atual['min']) / ($proximo['min'] - $atual['min'])) * 100);

        return [
            'nome'      => $atual['nome'],
            'meta'      => "próximo: {$proximo['nome']} ({$proximo['min']} pts)",
            'progresso' => max(0, min(100, $progresso)),
            'subiu'     => $subiu,
        ];
    }

    // Impacto ambiental estimado a partir das moedas
    public function impacto(int $coins): array
    {
        $p = max(0, $coins);
        return [
            'arvores' => Formato::inteiro(intdiv($p, 40)),
            'agua'    => Formato::inteiro(round($p * 1.5)) . 'L',
            'energia' => Formato::inteiro(round($p * 0.25)) . 'kWh',
            'co2'     => Formato::inteiro(round($p * 0.3)) . 'kg',
        ];
    }
}
