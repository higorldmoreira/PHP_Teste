<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * ConcurrencyException
 *
 * Lançada quando uma operação detecta conflito de concorrência otimista
 * (ex.: a versão do registro foi alterada por outro usuário/processo antes
 * que esta requisição pudesse concluir).
 *
 * Retorna HTTP 409 Conflict, conforme semântica REST para conflito de estado.
 */
class ConcurrencyException extends RuntimeException
{
    public function __construct(
        string $message = 'O registro foi alterado por outro usuário. Recarregue e tente novamente.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Renderiza a exceção como resposta JSON com HTTP 409.
     * Chamado automaticamente pelo ExceptionHandler do Laravel 11.
     */
    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'error'   => 'Conflict',
            'message' => $this->getMessage(),
        ], JsonResponse::HTTP_CONFLICT);
    }

    /**
     * Factory estático para leitura fluente no Service.
     *
     * Exemplo: throw ConcurrencyException::versaoDesatualizada();
     */
    public static function versaoDesatualizada(): static
    {
        return new static(
            'A versão da proposta foi alterada por outro usuário. Recarregue o registro e tente novamente.'
        );
    }
}
