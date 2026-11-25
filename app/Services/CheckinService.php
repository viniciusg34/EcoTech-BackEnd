<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Coleta;
use App\Models\Configuracao;
use App\Models\Pessoa;
use App\Models\SessaoEcoponto;
use App\Support\Formato;
use Illuminate\Support\Facades\DB;

/*
 * CHECK-IN NO ECOPONTO: +50 moedas e +0,5 kg (valores da tabela configuracao),
 * com espera mínima (cooldown) entre um check-in e outro no mesmo Ecoponto.
 */
class CheckinService
{
    public function __construct(private CoinService $coins)
    {
    }

    public function fazer(Pessoa $pessoa, SessaoEcoponto $sessao): array
    {
        $coins = Configuracao::inteiro('checkin_coins', 50);
        $kg = Configuracao::decimal('checkin_kg', 0.5);
        $horas = Configuracao::inteiro('checkin_cooldown_horas', 6);
        $ecoponto = $sessao->ecoponto;

        return DB::transaction(function () use ($pessoa, $sessao, $ecoponto, $coins, $kg, $horas) {
            // Trava a pessoa: dois toques rápidos no botão não geram dois check-ins
            Pessoa::query()->whereKey($pessoa->id)->lockForUpdate()->first();

            $ultimo = Coleta::query()
                ->where('pessoa_id', $pessoa->id)->where('ecoponto_id', $ecoponto->id)->where('tipo', 'checkin')
                ->latest('criado_em')->latest('id')->first();

            if ($ultimo && $horas > 0 && $ultimo->criado_em->copy()->addHours($horas)->isFuture()) {
                $liberado = $ultimo->criado_em->copy()->addHours($horas);
                $segundos = (int) ceil(now()->diffInSeconds($liberado, true));
                throw new ApiException(429, 'checkin_cooldown', 'Próximo check-in em ' . $this->tempo($segundos) . '.', [], [
                    'proximo_checkin_em' => Formato::iso($liberado),
                ], ['Retry-After' => (string) $segundos]);
            }

            $coleta = Coleta::create([
                'pessoa_id'   => $pessoa->id,
                'ecoponto_id' => $ecoponto->id,
                'sessao_id'   => $sessao->id,
                'tipo'        => 'checkin',
                'total_coins' => $coins,
                'total_kg'    => $kg,
            ]);

            $movimento = $this->coins->creditar(
                $pessoa->id, $coins, 'checkin', "Check-in em {$ecoponto->nome}", 'coleta', $coleta->id, $ecoponto->codigo, '📍'
            );

            $kgTexto = str_replace(' kg', '', Formato::kg($kg));

            return [
                'coinsAwarded'      => $coins,
                'balance'           => $movimento->saldo_apos,
                'balance_formatado' => Formato::inteiro($movimento->saldo_apos),
                'message'           => "Check-in feito: +{$coins} coins e {$kgTexto} kg.",
            ];
        });
    }

    // 10800 -> "3 h" ; 5400 -> "1 h 30 min" ; 600 -> "10 min"
    private function tempo(int $segundos): string
    {
        $minutos = (int) ceil($segundos / 60);
        $h = intdiv($minutos, 60);
        $m = $minutos % 60;
        if ($h > 0) {
            return $m > 0 ? "{$h} h {$m} min" : "{$h} h";
        }
        return max(1, $m) . ' min';
    }
}
