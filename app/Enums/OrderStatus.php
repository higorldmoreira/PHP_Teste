<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * OrderStatus
 *
 * Enum nativo PHP 8.1+ (backed enum com string).
 * Usar backed enums permite persistir o valor no banco
 * e comparar diretamente: $order->status === OrderStatus::Approved
 */
enum OrderStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Shipped  = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    // -------------------------------------------------------------------------
    // Métodos auxiliares — lógica de domínio fica encapsulada no Enum
    // -------------------------------------------------------------------------

    /**
     * Rótulo legível para exibição em UI ou logs.
     */
    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Aguardando pagamento',
            self::Approved  => 'Aprovado',
            self::Rejected  => 'Rejeitado',
            self::Shipped   => 'Enviado',
            self::Delivered => 'Entregue',
            self::Cancelled => 'Cancelado',
        };
    }

    /**
     * Retorna se o pedido pode ser cancelado neste status.
     */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Pending, self::Approved], strict: true);
    }

    /**
     * Retorna se o pedido está em um estado terminal (não muda mais).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled, self::Rejected], strict: true);
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
