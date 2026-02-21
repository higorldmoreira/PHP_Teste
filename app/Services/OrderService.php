<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

/**
 * OrderService
 *
 * Exemplo de Service de domínio que estende BaseService.
 * Toda lógica de negócio relacionada a pedidos vive aqui,
 * mantendo o Controller enxuto (apenas orquestração HTTP).
 *
 * @extends BaseService<Order>
 */
class OrderService extends BaseService
{
    public function __construct(Order $order)
    {
        $this->model = $order;
    }

    /**
     * Cria um pedido garantindo que o usuário não tenha outro pendente.
     *
     * @param  array<string, mixed>  $data
     */
    public function placeOrder(int $userId, array $data): Order
    {
        $this->ensureNoPendingOrder($userId);

        return $this->transaction(function () use ($userId, $data): Order {
            /** @var Order $order */
            $order = $this->create([
                ...$data,
                'user_id' => $userId,
                'status'  => OrderStatus::Pending->value,
            ]);

            // event(new OrderPlaced($order)); // dispare eventos aqui

            return $order;
        });
    }

    /**
     * Cancela um pedido se o status permitir.
     *
     * @throws BusinessException
     */
    public function cancel(int $orderId): Order
    {
        /** @var Order $order */
        $order = $this->findOrFail($orderId);

        $status = OrderStatus::from($order->status);

        if (! $status->isCancellable()) {
            throw BusinessException::because(
                "Pedido não pode ser cancelado no status '{$status->label()}'.",
                ['order_id' => $orderId, 'status' => $status->value],
            );
        }

        $order->update(['status' => OrderStatus::Cancelled->value]);

        return $order->refresh();
    }

    /**
     * Retorna todos os pedidos de um usuário.
     *
     * @return Collection<int, Order>
     */
    public function forUser(int $userId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('user_id', $userId)
            ->latest()
            ->get();
    }

    // -------------------------------------------------------------------------
    // Regras de guarda privadas
    // -------------------------------------------------------------------------

    /**
     * @throws BusinessException
     */
    private function ensureNoPendingOrder(int $userId): void
    {
        $exists = $this->model
            ->newQuery()
            ->where('user_id', $userId)
            ->where('status', OrderStatus::Pending->value)
            ->exists();

        if ($exists) {
            throw BusinessException::because(
                'Usuário já possui um pedido pendente.',
                ['user_id' => $userId],
            );
        }
    }
}
