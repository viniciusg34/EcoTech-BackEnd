<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\LogAutenticacaoResource;
use App\Models\LogAutenticacao;
use App\Support\Banco;
use App\Support\Paginacao;
use App\Support\Texto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/*
 * MASTER: tela de LOG de autenticação (enunciado): só as entradas de usuários comuns.
 * GET /admin/logs-autenticacao?tipo=nome|cpf|todos&termo=...&sucesso=1|0&evento=2fa
 * Sempre do mais recente para o mais antigo.
 */
class LogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tipo = (string) $request->query('tipo', 'todos');
        $termo = trim((string) $request->query('termo', ''));

        // Enunciado: o log mostra as entradas dos usuários COMUNS (sem master e sem empresas)
        $consulta = LogAutenticacao::query()->with(['pessoa', 'empresa'])
            ->whereHas('pessoa', fn ($p) => $p->where('perfil', 'comum'))
            ->when($termo !== '', function ($q) use ($tipo, $termo) {
                $nome = '%' . Banco::escaparLike(mb_strtolower($termo)) . '%';
                $cpf = Texto::digitos($termo);

                $porNome = fn ($w) => $w->whereHas('pessoa', fn ($p) => $p->whereRaw("LOWER(nome) LIKE ? ESCAPE '!'", [$nome]));
                $porCpf = fn ($w) => $w->whereHas('pessoa', fn ($p) => $p->where('cpf', 'like', '%' . $cpf . '%'));

                if ($tipo === 'nome') {
                    $q->where($porNome);
                } elseif ($tipo === 'cpf') {
                    $cpf === '' ? $q->whereRaw('1 = 0') : $q->where($porCpf);
                } else {
                    $q->where(fn ($w) => $cpf !== ''
                        ? $w->where($porNome)->orWhere($porCpf)
                        : $w->where($porNome));
                }
            })
            ->when($request->filled('evento'), fn ($q) => $q->where('evento', $request->query('evento')))
            ->when($request->has('sucesso') && $request->query('sucesso') !== '', fn ($q) => $q->where('sucesso', $request->boolean('sucesso')))
            ->orderByDesc('data_hora')->orderByDesc('id');

        return Paginacao::resposta(Paginacao::paginar($consulta, $request, 50),
            fn ($l) => (new LogAutenticacaoResource($l))->resolve($request));
    }
}
