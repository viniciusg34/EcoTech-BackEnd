<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\SessaoEcoponto;
use App\Support\Formato;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/*
 * PESSOA: cadastro, perfil (/me) e anonimização (LGPD).
 */
class PessoaService
{
    public function __construct(private NivelService $niveis)
    {
    }

    // $dados já chegam limpos e validados pelo CadastrarPessoaRequest
    public function cadastrar(array $dados): Pessoa
    {
        $this->conferirRepetidos($dados['email'], $dados['cpf'], $dados['login']);

        $pessoa = new Pessoa();
        $pessoa->forceFill([
            'nome'              => $dados['nome'],
            'email'             => $dados['email'],
            'email_confirmado'  => false, // vira true quando a pessoa digitar o código do e-mail
            'cpf'               => $dados['cpf'],
            'login'             => $dados['login'],
            'senha_hash'        => Hash::make($dados['senha']),
            'nome_mae'          => $dados['nome_mae'],
            'nascimento'        => $dados['nascimento'],
            'sexo'              => $dados['sexo'],
            'telefone_celular'  => $dados['telefone_celular'],
            'telefone_fixo'     => $dados['telefone_fixo'] ?? null,
            'cep'               => $dados['cep'],
            'logradouro'        => $dados['logradouro'],
            'numero'            => $dados['numero'],
            'bairro'            => $dados['bairro'],
            'cidade'            => $dados['cidade'],
            'uf'                => $dados['uf'],
            'perfil'            => 'comum',
            'saldo_coins'       => 0,
            'termos_aceitos_em' => now(),
            'ativo'             => true,
        ]);

        try {
            $pessoa->save();
        } catch (UniqueConstraintViolationException) {
            // Dois cadastros iguais ao mesmo tempo: o banco barra o segundo
            $this->conferirRepetidos($dados['email'], $dados['cpf'], $dados['login']);
            throw ApiException::conflito('duplicado', 'Já existe uma conta com estes dados.');
        }

        return $pessoa;
    }

    // 409 com o campo certo marcado (e-mail, CPF ou login já usados)
    public function conferirRepetidos(?string $email, ?string $cpf, ?string $login, ?int $ignorarId = null): void
    {
        $campos = [];
        $consulta = fn () => Pessoa::query()->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId));

        if ($email && ($consulta()->where('email', $email)->exists() || Empresa::query()->where('email', $email)->exists())) {
            $campos['email'] = ['Este e-mail já está cadastrado.'];
        }
        if ($cpf && $consulta()->where('cpf', $cpf)->exists()) {
            $campos['cpf'] = ['Este CPF já está cadastrado.'];
        }
        if ($login && $consulta()->where('login', $login)->exists()) {
            $campos['login'] = ['Este login já está em uso. Escolha outro.'];
        }

        if ($campos) {
            throw ApiException::conflito('duplicado', collect($campos)->flatten()->first(), $campos);
        }
    }

    // GET /me: dados da própria pessoa, já com nível e impacto calculados
    public function me(Pessoa $pessoa): array
    {
        $pessoa->refresh();
        $coins = (int) $pessoa->saldo_coins;

        return [
            'id'              => $pessoa->id,
            'nome'            => $pessoa->nome,
            'email'           => $pessoa->email,
            'login'           => $pessoa->login,
            'avatar'          => $pessoa->avatar_url,
            'tipo'            => 'pessoa',
            'perfil'          => $pessoa->perfil,
            'coins'           => $coins,
            'coins_formatado' => Formato::inteiro($coins),
            'nivel'           => $this->niveis->nivel($pessoa),
            'impacto'         => $this->niveis->impacto($coins),
            'cidade'          => $pessoa->cidade,
            'uf'              => $pessoa->uf,
            'telefone_celular_formatado' => Formato::telefone($pessoa->telefone_celular),
            'membro_desde'    => Formato::data($pessoa->criado_em),
        ];
    }

    // PUT /me: nome, e-mail e foto
    public function atualizar(Pessoa $pessoa, array $dados): Pessoa
    {
        if (array_key_exists('email', $dados) && $dados['email'] !== $pessoa->email) {
            $this->conferirRepetidos($dados['email'], null, null, $pessoa->id);
        }

        $pessoa->fill(array_intersect_key($dados, array_flip(['nome', 'email'])));
        if (array_key_exists('avatar_url', $dados)) {
            $pessoa->avatar_url = $dados['avatar_url'];
        }
        $pessoa->save();

        return $pessoa;
    }

    /*
     * ANONIMIZAR (LGPD): apaga os dados pessoais e mantém o histórico
     * (moedas, coletas e resgates continuam, mas sem dizer de quem são).
     */
    public function anonimizar(Pessoa $pessoa): void
    {
        DB::transaction(function () use ($pessoa) {
            $pessoa->forceFill([
                'nome'             => 'Pessoa removida',
                'email'            => 'removido-' . $pessoa->id . '@anonimo.ecotech.invalid',
                'cpf'              => null,
                'login'            => null,
                'senha_hash'       => Hash::make(Str::random(40)),
                'nome_mae'         => null,
                'nascimento'       => null,
                'sexo'             => null,
                'telefone_celular' => null,
                'telefone_fixo'    => null,
                'cep'              => null,
                'logradouro'       => null,
                'numero'           => null,
                'bairro'           => null,
                'cidade'           => null,
                'uf'               => null,
                'avatar_url'       => null,
                'ativo'            => false,
                'anonimizado_em'   => now(),
            ])->save();

            // Sai de todos os aparelhos e encerra sessões em Ecopontos
            $pessoa->tokens()->delete();
            SessaoEcoponto::query()->where('pessoa_id', $pessoa->id)->where('status', 'ativa')
                ->update(['status' => 'encerrada', 'encerrada_em' => now()]);
        });
    }
}
