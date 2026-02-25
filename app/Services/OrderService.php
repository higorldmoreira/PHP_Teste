<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CriarOrderDTO;
use App\Enums\OrderStatus;
use App\Enums\PropostaStatusEnum;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Proposta;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    /**
     * Cria um pedido a partir de uma Proposta APPROVED.
     *
     * @throws BusinessException
     */
    public function placeOrder(Proposta $proposta, CriarOrderDTO $dto): Order
    {
        if ($proposta->status !== PropostaStatusEnum::APPROVED) {
            throw BusinessException::because(
                "Somente propostas aprovadas podem gerar um pedido. Status atual: {$proposta->status->value}.",
                ['proposta_id' => $proposta->id, 'status' => $proposta->status->value],
            );
        }

        $this->ensureNoActiveOrder($proposta);

        return DB::transaction(static function () use ($proposta, $dto): Order {
            $order = Order::create([
                ...$dto->toArray(),
                'proposta_id' => $proposta->id,
                'status'      => OrderStatus::PENDING->value,
                'valor_total' => $proposta->valor_mensal,
            ]);

            Log::info('order.placed', [
                'order_id'    => $order->id,
                'proposta_id' => $proposta->id,
                'valor_total' => $order->valor_total,
            ]);

            return $order;
        });
    }

    /**
     * Cancela um pedido se o status permitir.
     *
     * @throws BusinessException
     */
    public function cancel(Order $order): Order
    {
        if (! $order->status->isCancellable()) {
            throw BusinessException::because(
                "Pedido não pode ser cancelado no status '{$order->status->label()}'.",
                ['order_id' => $order->id, 'status' => $order->status->value],
            );
        }

        return DB::transaction(static function () use ($order): Order {
            $order->update(['status' => OrderStatus::CANCELED->value]);

            Log::info('order.canceled', [
                'order_id'    => $order->id,
                'proposta_id' => $order->proposta_id,
            ]);

            return $order->refresh();
        });
    }

    /**
     * Retorna pedidos paginados, com filtro opcional por status.
     */
    public function paginate(?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Order::with('proposta')->latest();

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    /**
     * Garante que a proposta não possui pedido ativo (não cancelado).
     *
     * @throws BusinessException
     */
    private function ensureNoActiveOrder(Proposta $proposta): void
    {
        $exists = Order::query()
            ->where('proposta_id', $proposta->id)
            ->whereNot('status', OrderStatus::CANCELED->value)
            ->exists();

        if ($exists) {
            throw BusinessException::because(
                "A proposta #{$proposta->id} já possui um pedido ativo.",
                ['proposta_id' => $proposta->id],
            );
        }
    }
}