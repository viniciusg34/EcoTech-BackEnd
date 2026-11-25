<?php

namespace App\Http\Resources;

use App\Support\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Pessoa na lista do master. LGPD: CPF mascarado e nada de nome da mãe, nascimento ou CEP.
 */
class PessoaAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'nome'                => $this->nome,
            'email'               => $this->email,
            'login'               => $this->login,
            'perfil'              => $this->perfil,
            'cpf_mascarado'       => Formato::cpfMascarado($this->cpf),
            'cidade'              => $this->cidade,
            'uf'                  => $this->uf,
            'coins'               => (int) $this->saldo_coins,
            'coins_formatado'     => Formato::inteiro($this->saldo_coins),
            'ativo'               => (bool) $this->ativo,
            'anonimizada'         => $this->anonimizado_em !== null,
            'criado_em'           => Formato::iso($this->criado_em),
            'criado_em_formatado' => Formato::data($this->criado_em),
        ];
    }
}
