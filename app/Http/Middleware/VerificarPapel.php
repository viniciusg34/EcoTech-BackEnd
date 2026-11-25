<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\Empresa;
use App\Models\Pessoa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 * PORTEIRO DE PAPEL (roda depois do auth:sanctum).
 *   ->middleware('papel:pessoa')   pessoa (comum ou master)
 *   ->middleware('papel:empresa')  empresa parceira ativa
 *   ->middleware('papel:master')   só o master
 * Confere o "ability" do token E a conta no banco (ex.: empresa suspensa perde o acesso na hora).
 */
class VerificarPapel
{
    public function handle(Request $request, Closure $next, string ...$papeis): Response
    {
        $conta = $request->user();

        foreach ($papeis as $papel) {
            if ($this->permite($conta, $papel)) {
                return $next($request);
            }
        }

        throw ApiException::semPermissao(match ($papeis[0] ?? '') {
            'master'  => 'Acesso permitido apenas para o administrador (master).',
            'empresa' => 'Acesso permitido apenas para empresas parceiras.',
            default   => 'Acesso permitido apenas para pessoas cadastradas.',
        });
    }

    private function permite(mixed $conta, string $papel): bool
    {
        return match ($papel) {
            'pessoa'  => $conta instanceof Pessoa && $conta->ativo && $conta->tokenCan('pessoa'),
            'master'  => $conta instanceof Pessoa && $conta->ativo && $conta->ehMaster() && $conta->tokenCan('master'),
            'empresa' => $conta instanceof Empresa && $conta->status === 'ativa' && $conta->tokenCan('empresa'),
            default   => false,
        };
    }
}
