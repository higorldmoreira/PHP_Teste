<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contrato comum para todos os enums de status do sistema.
 *
 * Garante que PropostaStatusEnum e OrderStatus possam ser tratados
 * de forma polimórfica em services e validators.
 */
interface StatusEnumInterface
{
    /** Retorna true quando nenhuma transição adicional é permitida. */
    public function isTerminal(): bool;

    /** Retorna true quando o item pode ser cancelado neste estado. */
    public function isCancellable(): bool;

    /** Rótulo legível para exibição em UI, logs e mensagens de erro. */
    public function label(): string;
}
