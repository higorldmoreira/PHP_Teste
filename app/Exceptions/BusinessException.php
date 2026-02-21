<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * BusinessException
 *
 * Exceção base para violações de regra de negócio.
 * Lançada pelos Services quando uma operação não pode ser concluída
 * por motivo de domínio (não por erro técnico).
 *
 * O Handler do Laravel pode capturá-la e retornar uma resposta
 * HTTP semântica (tipicamente 422 Unprocessable Entity).
 */
class BusinessException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context  Dados extras para log/debug
     */
    public function __construct(
        string $message = 'Operação não permitida.',
        private readonly array $context = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Dados de contexto adicionais (para logging estruturado).
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * Factory estático para leitura fluente no Service.
     *
     * Exemplo: throw BusinessException::because('Saldo insuficiente.', ['balance' => 0]);
     *
     * @param  array<string, mixed>  $context
     */
    public static function because(string $reason, array $context = []): static
    {
        return new static($reason, $context);
    }
}
