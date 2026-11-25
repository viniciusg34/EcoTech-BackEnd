<?php

namespace App\Policies;

use App\Models\Ecoponto;
use App\Models\Empresa;
use Illuminate\Auth\Access\Response;

/*
 * A empresa só vê e mexe nos PRÓPRIOS Ecopontos.
 */
class EcopontoPolicy
{
    public function gerenciar(Empresa $empresa, Ecoponto $ecoponto): Response
    {
        return $ecoponto->empresa_id === $empresa->id
            ? Response::allow()
            : Response::deny('Este Ecoponto não é da sua empresa.');
    }
}
