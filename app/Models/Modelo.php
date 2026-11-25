<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/*
 * Base de todos os models do EcoTech.
 * As tabelas usam criado_em / atualizado_em (em português)
 * no lugar do created_at / updated_at padrão do Laravel.
 */
abstract class Modelo extends Model
{
    const CREATED_AT = 'criado_em';
    const UPDATED_AT = 'atualizado_em';

    protected $guarded = ['id'];
}
