<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/*
 * ERRO "DE NEGÓCIO" DA API, já com o formato que o front espera:
 *   { "erro": "codigo_curto", "message": "texto pronto para o toast", "campos": {...} }
 * Uso:  throw new ApiException(409, 'saldo_insuficiente', 'Saldo insuficiente.');
 * O bootstrap/app.php transforma qualquer ApiException nessa resposta.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $erro,
        string $message,
        public readonly array $campos = [],
        public readonly array $extras = [],
        public readonly array $headers = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        $corpo = ['erro' => $this->erro, 'message' => $this->getMessage()];
        if ($this->campos) {
            $corpo['campos'] = $this->campos;
        }

        return response()->json($corpo + $this->extras, $this->status, $this->headers, JSON_UNESCAPED_UNICODE);
    }

    /* ---------- Atalhos para os erros mais comuns ---------- */

    public static function naoEncontrado(string $message = 'Não encontrado.'): self
    {
        return new self(404, 'nao_encontrado', $message);
    }

    public static function semPermissao(string $message = 'Você não tem permissão para isso.'): self
    {
        return new self(403, 'sem_permissao', $message);
    }

    public static function validacao(string $campo, string $message): self
    {
        return new self(422, 'validacao', $message, [$campo => [$message]]);
    }

    public static function conflito(string $erro, string $message, array $campos = []): self
    {
        return new self(409, $erro, $message, $campos);
    }
}
