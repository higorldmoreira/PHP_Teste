<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PropostaStatusEnum;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Proposta;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * OrderService
 *
 * Gerencia o ciclo de vida de pedidos (Orders).
 * Um Order é gerado a partir de uma Proposta no status APPROVED.
 *
 * Regras de negócio:
 *  - Somente propostas APPROVED podem gerar um pedido.
 *  - Uma proposta só pode ter um pedido ativo (não cancelado) por vez.
 *  - Cancelamento só é permitido nos status: Pending, Approved.
 *
 * @extends BaseService<Order>
 */
class OrderService extends BaseService
{
    public function __construct(Order $order)
    {
        $this->model = $order;
    }

    // ── Criação ────────────────────────────────────────────────────────────────

    /**
     * Cria um pedido a partir de uma Proposta APPROVED.
     *
     * @param  array<string, mixed>  $data  Campos adicionais (observacoes, etc.)
     *
     * @throws BusinessException
     * @throws \Throwable
     */
    public function placeOrder(Proposta $proposta, User $user, array $data = []): Order
    {
        if ($proposta->status !== PropostaStatusEnum::APPROVED) {
            throw BusinessException::because(
                "Somente propostas aprovadas podem gerar um pedido. "
                . "Status atual: {$proposta->status->value}.",
                ['proposta_id' => $proposta->id, 'status' => $proposta->status->value],
            );
        }

        $this->ensureNoActiveOrder($proposta);

        return DB::transaction(function () use ($proposta, $user, $data): Order {
            /** @var Order $order */
            $order = $this->create([
                ...$data,
                'proposta_id' => $proposta->id,
                'user_id'     => $user->id,
                'status'      => OrderStatus::Pending->value,
                'valor_total' => $proposta->valor_mensal,
            ]);

            return $order;
        });
    }

    // ── Cancelamento ───────────────────────────────────────────────────────────

    /**
     * Cancela um pedido se o status permitir.
     *
     * @throws BusinessException
     * @throws \Throwable
     */
    public function cancel(Order $order): Order
    {
        if (! $order->status->isCancellable()) {
            throw BusinessException::because(
                "Pedido nao pode ser cancelado no status '{$order->status->label()}'.",
                ['order_id' => $order->id, 'status' => $order->status->value],
            );
        }

        return DB::transaction(function () use ($order): Order {
            $order->update(['status' => OrderStatus::Cancelled->value]);
            return $order->refresh();
        });
    }

    // ── Consultas ──────────────────────────────────────────────────────────────

    /**
     * Retorna pedidos paginados do usuario autenticado,
     * com filtro opcional por status.
     */
    public function paginatedForUser(User $user, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model
            ->newQuery()
            ->with('proposta')
            ->where('user_id', $user->id)
            ->latest();

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    // ── Guardas privadas ───────────────────────────────────────────────────────

    /**
     * Garante que a proposta nao possui um pedido ativo (nao cancelado).
     *
     * @throws BusinessException
     */
    private function ensureNoActiveOrder(Proposta $proposta): void
    {
        $exists = $this->model
            ->newQuery()
            ->where('proposta_id', $proposta->id)
            ->whereNot('status', OrderStatus::Cancelled->value)
            ->exists();

        if ($exists) {
            throw BusinessException::because(
                "A proposta #{$proposta->id} ja possui um pedido ativo.",
                ['proposta_id' => $proposta->id],
            );
        }
    }
}
