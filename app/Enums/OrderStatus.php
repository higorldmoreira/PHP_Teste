<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\StatusEnumInterface;

/**
 * Estados possíveis de um pedido (Order).
 *
 * Convenção: SCREAMING_SNAKE_CASE, alinhado aos demais enums do projeto.
 * Valor string: americano singular, compatível com coluna `status` no banco.
 */
enum OrderStatus: string implements StatusEnumInterface
{
    case PENDING   = 'pending';
    case APPROVED  = 'approved';
    case REJECTED  = 'rejected';
    case SHIPPED   = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELED  = 'canceled';

    /** Rótulo legível para exibição em UI ou logs. */
    public function label(): string
    {
        return match($this) {
            self::PENDING   => 'Aguardando pagamento',
            self::APPROVED  => 'Aprovado',
            self::REJECTED  => 'Rejeitado',
            self::SHIPPED   => 'Enviado',
            self::DELIVERED => 'Entregue',
            self::CANCELED  => 'Cancelado',
        };
    }

    /** Retorna true se o pedido pode ser cancelado neste status. */
    public function isCancellable(): bool
    {
        return $this === self::PENDING;
    }

    /** Retorna true se o pedido está em um estado terminal (não muda mais). */
    public function isTerminal(): bool
    {
        return in_array($this, [self::DELIVERED, self::CANCELED, self::REJECTED], strict: true);
    }

    /**
     * Todos os valores como array de strings — útil em validações.
     *
     * Uso: Rule::in(OrderStatus::values())
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
