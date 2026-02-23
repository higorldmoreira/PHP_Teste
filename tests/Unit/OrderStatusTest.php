<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    // ── isCancellable ─────────────────────────────────────────────────────────

    public function test_somente_pending_e_cancelavel(): void
    {
        $this->assertTrue(OrderStatus::PENDING->isCancellable());

        foreach ([OrderStatus::APPROVED, OrderStatus::REJECTED, OrderStatus::SHIPPED, OrderStatus::DELIVERED, OrderStatus::CANCELED] as $status) {
            $this->assertFalse($status->isCancellable(), "Status {$status->value} não deveria ser cancelável");
        }
    }

    // ── isTerminal ────────────────────────────────────────────────────────────

    public function test_delivered_canceled_rejected_sao_terminais(): void
    {
        $this->assertTrue(OrderStatus::DELIVERED->isTerminal());
        $this->assertTrue(OrderStatus::CANCELED->isTerminal());
        $this->assertTrue(OrderStatus::REJECTED->isTerminal());
    }

    public function test_pending_approved_shipped_nao_sao_terminais(): void
    {
        $this->assertFalse(OrderStatus::PENDING->isTerminal());
        $this->assertFalse(OrderStatus::APPROVED->isTerminal());
        $this->assertFalse(OrderStatus::SHIPPED->isTerminal());
    }

    // ── label ─────────────────────────────────────────────────────────────────

    public function test_labels_retornam_texto_legivel(): void
    {
        $this->assertSame('Aguardando pagamento', OrderStatus::PENDING->label());
        $this->assertSame('Aprovado', OrderStatus::APPROVED->label());
        $this->assertSame('Rejeitado', OrderStatus::REJECTED->label());
        $this->assertSame('Enviado', OrderStatus::SHIPPED->label());
        $this->assertSame('Entregue', OrderStatus::DELIVERED->label());
        $this->assertSame('Cancelado', OrderStatus::CANCELED->label());
    }

    // ── values ────────────────────────────────────────────────────────────────

    public function test_values_retorna_todos_os_valores_string(): void
    {
        $expected = ['pending', 'approved', 'rejected', 'shipped', 'delivered', 'canceled'];
        $this->assertSame($expected, OrderStatus::values());
    }
}
