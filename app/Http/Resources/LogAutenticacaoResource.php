<?php

namespace App\Http\Resources;

use App\Services\TwoFactorService;
use App\Support\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Linha da tela de LOG do master: data/hora, nome, CPF (mascarado) e a pergunta do 2FA usada.
 */
class LogAutenticacaoResource extends JsonResource
{
    public const EVENTOS = [
        'senha'           => 'Login (senha)',
        '2fa'             => 'Segundo fator (2FA)',
        'google'          => 'Login com Google',
        'recuperacao'     => 'Recuperação de senha',
        'redefinir_senha' => 'Redefinição de senha',
    ];

    public function toArray(Request $request): array
    {
        $pessoa = $this->pessoa;
        $empresa = $this->empresa;

        return [
            'id'                  => $this->id,
            'data_hora'           => Formato::iso($this->data_hora),
            'data_hora_formatada' => Formato::dataHora($this->data_hora),
            'data_formatada'      => Formato::dataHora($this->data_hora),   // nome que o front usa
            'conta_tipo'          => $pessoa ? 'pessoa' : 'empresa',
            'conta_id'            => $pessoa?->id ?? $empresa?->id,
            'nome'                => $pessoa?->nome ?? $empresa?->nome_empresa,
            'cpf_mascarado'       => Formato::cpfMascarado($pessoa?->cpf),
            'perfil'              => $pessoa?->perfil ?? 'empresa',
            'evento'              => $this->evento,
            'evento_rotulo'       => self::EVENTOS[$this->evento] ?? $this->evento,
            'metodo_2fa'          => $this->metodo_2fa,
            'sucesso'             => (bool) $this->sucesso,
            'sucesso_rotulo'      => $this->sucesso ? 'Sucesso' : 'Falha',
            'tentativa_n'         => $this->tentativa_n,
            'ip'                  => $this->ipTexto(),
            'user_agent'          => $this->user_agent,
        ];
    }
}
