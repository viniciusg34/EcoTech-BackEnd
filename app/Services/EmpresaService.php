<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Support\Formato;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;

/*
 * EMPRESA PARCEIRA: cadastro e perfil.
 */
class EmpresaService
{
    public function cadastrar(array $dados): Empresa
    {
        $this->conferirRepetidos($dados['cnpj'], $dados['email']);

        $empresa = new Empresa();
        $empresa->forceFill([
            'cnpj'         => $dados['cnpj'],
            'nome_empresa' => $dados['nome_empresa'],
            'razao_social' => $dados['razao_social'] ?? null,
            'email'        => $dados['email'],
            'senha_hash'   => Hash::make($dados['senha']),
            'endereco'     => $dados['endereco'] ?? null,
            'status'       => 'ativa',
        ]);

        try {
            $empresa->save();
        } catch (UniqueConstraintViolationException) {
            $this->conferirRepetidos($dados['cnpj'], $dados['email']);
            throw ApiException::conflito('duplicado', 'Já existe uma empresa com estes dados.');
        }

        return $empresa;
    }

    // O e-mail não pode existir nem em outra empresa nem numa pessoa (o login é pelo e-mail)
    public function conferirRepetidos(?string $cnpj, ?string $email, ?int $ignorarId = null): void
    {
        $campos = [];
        $consulta = fn () => Empresa::query()->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId));

        if ($cnpj && $consulta()->where('cnpj', $cnpj)->exists()) {
            $campos['cnpj'] = ['Este CNPJ já está cadastrado.'];
        }
        if ($email && ($consulta()->where('email', $email)->exists() || Pessoa::query()->where('email', $email)->exists())) {
            $campos['email'] = ['Este e-mail já está cadastrado.'];
        }

        if ($campos) {
            throw ApiException::conflito('duplicado', collect($campos)->flatten()->first(), $campos);
        }
    }

    public function atualizar(Empresa $empresa, array $dados): Empresa
    {
        if (isset($dados['email']) && $dados['email'] !== $empresa->email) {
            $this->conferirRepetidos(null, $dados['email'], $empresa->id);
        }
        $empresa->fill(array_intersect_key($dados, array_flip(['nome_empresa', 'razao_social', 'email', 'endereco'])));
        $empresa->save();
        return $empresa;
    }

    public function dados(Empresa $empresa): array
    {
        return [
            'id'              => $empresa->id,
            'tipo'            => 'empresa',
            'perfil'          => 'empresa',
            'cnpj'            => $empresa->cnpj,
            'cnpj_formatado'  => Formato::cnpj($empresa->cnpj),
            'nome'            => $empresa->nome_empresa,
            'nome_empresa'    => $empresa->nome_empresa,
            'razao_social'    => $empresa->razao_social,
            'email'           => $empresa->email,
            'endereco'        => $empresa->endereco,
            'status'          => $empresa->status,
            'status_rotulo'   => $empresa->status === 'ativa' ? 'Ativa' : 'Suspensa',
            'total_ecopontos' => $empresa->ecopontos()->count(),
            'membro_desde'    => Formato::data($empresa->criado_em),
        ];
    }
}
