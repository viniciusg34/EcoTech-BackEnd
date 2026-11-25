<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/*
 * POST /ecopontos (criar), PUT (tudo) e PATCH (só o que mudou).
 * categorias = lista de chaves: ["metal", "papel"]
 */
class SalvarEcopontoRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->semEspacos(['nome', 'descricao', 'endereco', 'cidade']);
        if ($this->filled('codigo') && is_string($this->input('codigo'))) {
            $this->merge(['codigo' => mb_strtoupper(trim($this->input('codigo')))]);
        }
        if ($this->filled('estado') && is_string($this->input('estado'))) {
            $this->merge(['estado' => mb_strtoupper(trim($this->input('estado')))]);
        }
    }

    public function rules(): array
    {
        $parcial = $this->isMethod('PATCH');
        $obrigatorio = $parcial ? ['sometimes', 'required'] : ['required'];
        $ecoponto = $this->route('ecoponto');

        return [
            'codigo'       => ['sometimes', 'nullable', 'string', 'regex:/^[A-Z0-9-]{3,20}$/',
                Rule::unique('ecoponto', 'codigo')->ignore($ecoponto?->id)],
            'nome'         => [...$obrigatorio, 'string', 'max:150'],
            'descricao'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'endereco'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'cidade'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'estado'       => ['sometimes', 'nullable', 'string', 'size:2'],
            'lat'          => [...$obrigatorio, 'numeric', 'between:-90,90'],
            'lng'          => [...$obrigatorio, 'numeric', 'between:-180,180'],
            'status'       => ['sometimes', 'in:ativo,manutencao,inativo'],
            'categorias'   => [...$obrigatorio, 'array', 'min:1'],
            'categorias.*' => ['string', 'distinct', 'exists:categoria_material,chave'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.regex'        => 'O código deve ter de 3 a 20 letras maiúsculas, números ou hífen (ex.: ECO-23).',
            'codigo.unique'       => 'Já existe um Ecoponto com este código.',
            'categorias.min'      => 'Escolha pelo menos um material.',
            'categorias.*.exists' => 'Categoria de material inexistente.',
        ];
    }
}
