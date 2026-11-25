<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AlterarSenhaRequest;
use App\Http\Requests\AtualizarMeRequest;
use App\Http\Resources\ColetaResource;
use App\Http\Resources\MovimentoResource;
use App\Http\Resources\ResgateResource;
use App\Services\CoinService;
use App\Models\SessaoEcoponto;
use App\Services\PessoaService;
use App\Support\Formato;
use App\Support\Paginacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

/*
 * /me — a própria pessoa logada (perfil comum ou master).
 */
class MeController extends Controller
{
    public function __construct(private PessoaService $pessoas)
    {
    }

    // GET /me — dados, saldo, nível e impacto
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->pessoas->me($request->user()));
    }

    // PUT /me  { nome, email, avatar_url }
    public function update(AtualizarMeRequest $request): JsonResponse
    {
        $pessoa = $this->pessoas->atualizar($request->user(), $request->validated());
        return response()->json($this->pessoas->me($pessoa) + ['message' => 'Perfil atualizado.']);
    }

    // DELETE /me — anonimiza a conta (LGPD) e mantém o histórico
    public function destroy(Request $request): Response
    {
        $pessoa = $request->user();
        if ($pessoa->ehMaster()) {
            throw ApiException::semPermissao('A conta master não pode ser excluída por aqui.');
        }
        $this->pessoas->anonimizar($pessoa);
        return response()->noContent();
    }

    // PUT /me/senha  { senha_atual, nova_senha } — só perfil comum (regra do enunciado)
    public function alterarSenha(AlterarSenhaRequest $request): JsonResponse
    {
        $pessoa = $request->user();
        if ($pessoa->ehMaster()) {
            throw ApiException::semPermissao('Somente o usuário comum pode alterar a própria senha.');
        }
        if (! Hash::check($request->input('senha_atual'), $pessoa->senha_hash)) {
            throw ApiException::validacao('senha_atual', 'A senha atual está incorreta.');
        }

        $pessoa->forceFill(['senha_hash' => Hash::make($request->input('nova_senha'))])->save();

        // Os outros aparelhos saem; este continua logado
        $pessoa->tokens()->whereKeyNot($pessoa->currentAccessToken()->getKey())->delete();

        return response()->json(['message' => 'Senha alterada com sucesso.']);
    }

    // GET /me/sessao — a pessoa está conectada a algum Ecoponto agora?
    public function sessao(Request $request): JsonResponse
    {
        $sessao = $this->sessaoAtual($request);

        return response()->json([
            'sessao_id'           => $sessao->id,
            'ecoponto'            => ['id' => $sessao->ecoponto->id, 'codigo' => $sessao->ecoponto->codigo, 'nome' => $sessao->ecoponto->nome],
            'iniciada_em'         => Formato::iso($sessao->iniciada_em),
            'expira_em'           => Formato::iso($sessao->expira_em),
            'expira_em_formatado' => $sessao->expira_em->copy()->setTimezone('America/Sao_Paulo')->format('H:i'),
        ]);
    }

    // DELETE /me/sessao — a pessoa sai do Ecoponto
    public function sairDaSessao(Request $request): JsonResponse
    {
        $this->sessaoAtual($request)->update(['status' => 'encerrada', 'encerrada_em' => now()]);
        return response()->json(['message' => 'Você saiu do Ecoponto.']);
    }

    private function sessaoAtual(Request $request): SessaoEcoponto
    {
        $sessao = SessaoEcoponto::query()->validas()->where('pessoa_id', $request->user()->id)
            ->with('ecoponto')->latest('id')->first();
        if (! $sessao) {
            throw new ApiException(404, 'sem_sessao', 'Você não está conectado a nenhum Ecoponto.');
        }
        return $sessao;
    }

    // GET /me/preferencias — ex.: { "notificacoes": true }
    public function preferencias(Request $request): JsonResponse
    {
        return response()->json((object) ($request->user()->preferencias ?? []));
    }

    // PUT /me/preferencias — guarda as preferências do perfil (antes ficavam só no navegador)
    public function salvarPreferencias(Request $request): JsonResponse
    {
        $dados = $request->json()->all();
        if (! is_array($dados) || count($dados) > 30) {
            throw ApiException::validacao('preferencias', 'Envie um objeto com até 30 preferências.');
        }
        foreach ($dados as $chave => $valor) {
            if (! is_string($chave) || ! preg_match('/^[a-z0-9_]{1,40}$/', $chave) || ! (is_scalar($valor) || $valor === null)
                || (is_string($valor) && mb_strlen($valor) > 100)) {
                throw ApiException::validacao('preferencias', 'Preferência inválida: ' . $chave);
            }
        }

        $pessoa = $request->user();
        $pessoa->preferencias = $dados;
        $pessoa->save();

        return response()->json((object) $dados);
    }

    // GET /me/coins — saldo (o celular consulta a cada 5 s durante a sessão no Ecoponto)
    public function coins(Request $request, CoinService $coins): JsonResponse
    {
        return response()->json($coins->saldoResposta($request->user()->id));
    }

    // GET /me/movimentos?pagina=1&por_pagina=20 — extrato, do mais recente para o mais antigo
    public function movimentos(Request $request): JsonResponse
    {
        $consulta = $request->user()->movimentos()->orderByDesc('criado_em')->orderByDesc('id');
        return Paginacao::resposta(Paginacao::paginar($consulta, $request),
            fn ($m) => (new MovimentoResource($m))->resolve($request));
    }

    // GET /me/coletas
    public function coletas(Request $request): JsonResponse
    {
        $consulta = $request->user()->coletas()->with(['ecoponto', 'itens.categoria'])
            ->orderByDesc('criado_em')->orderByDesc('id');
        return Paginacao::resposta(Paginacao::paginar($consulta, $request),
            fn ($c) => (new ColetaResource($c))->resolve($request));
    }

    // GET /me/resgates
    public function resgates(Request $request): JsonResponse
    {
        $consulta = $request->user()->resgates()->with('recompensa')->orderByDesc('criado_em')->orderByDesc('id');
        return Paginacao::resposta(Paginacao::paginar($consulta, $request),
            fn ($r) => (new ResgateResource($r))->resolve($request));
    }
}
