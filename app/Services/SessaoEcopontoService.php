<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Configuracao;
use App\Models\Ecoponto;
use App\Models\Pessoa;
use App\Models\SessaoEcoponto;
use Illuminate\Support\Facades\DB;

/*
 * SESSÃO NO ECOPONTO: o vínculo pessoa <-> Ecoponto criado ao ler o QR da placa.
 * Uma pessoa tem no máximo UMA sessão ativa por vez.
 */
class SessaoEcopontoService
{
    // Conecta (ou devolve a sessão que já existe neste ponto: chamar de novo não duplica)
    public function conectar(Pessoa $pessoa, Ecoponto $ecoponto): SessaoEcoponto
    {
        return DB::transaction(function () use ($pessoa, $ecoponto) {
            // Trava a pessoa para dois "conectar" juntos não criarem duas sessões
            Pessoa::query()->whereKey($pessoa->id)->lockForUpdate()->first();

            $existente = SessaoEcoponto::query()->validas()
                ->where('pessoa_id', $pessoa->id)->where('ecoponto_id', $ecoponto->id)
                ->latest('id')->first();
            if ($existente) {
                return $existente;
            }

            // Sessão aberta em outro Ecoponto é encerrada
            SessaoEcoponto::query()->where('pessoa_id', $pessoa->id)->where('status', 'ativa')
                ->update(['status' => 'encerrada', 'encerrada_em' => now()]);

            $minutos = Configuracao::inteiro('sessao_minutos', 10);

            return SessaoEcoponto::create([
                'ecoponto_id' => $ecoponto->id,
                'pessoa_id'   => $pessoa->id,
                'token'       => bin2hex(random_bytes(32)),
                'status'      => 'ativa',
                'iniciada_em' => now(),
                'expira_em'   => now()->addMinutes($minutos),
            ]);
        });
    }

    // Sessão ativa mais recente do Ecoponto (para a tela do Ecoponto saber quem está conectado)
    public function ativaDoEcoponto(Ecoponto $ecoponto): ?SessaoEcoponto
    {
        return SessaoEcoponto::query()->validas()
            ->where('ecoponto_id', $ecoponto->id)
            ->with('pessoa')
            ->latest('iniciada_em')->latest('id')
            ->first();
    }

    // Sessão da própria pessoa (check-in)
    public function daPessoa(Pessoa $pessoa, int $sessaoId): SessaoEcoponto
    {
        $sessao = SessaoEcoponto::query()->whereKey($sessaoId)->where('pessoa_id', $pessoa->id)->first();
        return $this->exigirValida($sessao);
    }

    // Sessão de um Ecoponto (leituras e entregas feitas pela empresa)
    public function doEcoponto(Ecoponto $ecoponto, int $sessaoId): SessaoEcoponto
    {
        $sessao = SessaoEcoponto::query()->whereKey($sessaoId)->where('ecoponto_id', $ecoponto->id)->first();
        return $this->exigirValida($sessao);
    }

    private function exigirValida(?SessaoEcoponto $sessao): SessaoEcoponto
    {
        if (! $sessao) {
            throw new ApiException(404, 'sessao_nao_encontrada', 'Sessão não encontrada. Leia o QR Code do Ecoponto de novo.');
        }
        if (! $sessao->estaValida()) {
            if ($sessao->status === 'ativa') {
                $sessao->update(['status' => 'expirada']);
            }
            throw new ApiException(410, 'sessao_expirada', 'A sessão no Ecoponto expirou. Leia o QR Code de novo.');
        }
        return $sessao;
    }

    public function encerrar(SessaoEcoponto $sessao): void
    {
        $sessao->update(['status' => 'encerrada', 'encerrada_em' => now()]);
    }

    // Usado pelo comando agendado: marca como "expirada" o que passou do prazo
    public function expirarVencidas(): int
    {
        return SessaoEcoponto::query()->where('status', 'ativa')->where('expira_em', '<=', now())
            ->update(['status' => 'expirada']);
    }
}
