<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Cliente;
use App\Models\Order;
use App\Models\Proposta;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrderService();
    }

    // ── placeOrder ────────────────────────────────────────────────────────────

    public function test_place_order_cria_pedido_de_proposta_aprovada(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id'   => Cliente::factory()->create()->id,
            'valor_mensal' => 750.00,
        ]);

        $order = $this->service->placeOrder($proposta);

        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame($proposta->id, $order->proposta_id);
        $this->assertSame('750.00', $order->valor_total);
    }

    public function test_place_order_lanca_excecao_para_proposta_nao_aprovada(): void
    {
        foreach (['draft', 'submitted', 'rejected'] as $state) {
            $proposta = Proposta::factory()->{$state}()->create([
                'cliente_id' => Cliente::factory()->create()->id,
            ]);

            try {
                $this->service->placeOrder($proposta);
                $this->fail("Deveria ter lançado BusinessException para status '{$state}'");
            } catch (BusinessException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_place_order_impede_duplicata_ativa(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        $this->service->placeOrder($proposta);

        $this->expectException(BusinessException::class);

        $this->service->placeOrder($proposta);
    }

    public function test_place_order_permite_novo_pedido_apos_cancelamento(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        $primeiro = $this->service->placeOrder($proposta);
        $this->service->cancel($primeiro);

        // Deve ser possível criar novo pedido após cancelar o anterior
        $segundo = $this->service->placeOrder($proposta);

        $this->assertSame(OrderStatus::PENDING, $segundo->status);
        $this->assertDatabaseCount('orders', 2);
    }

    // ── cancel ────────────────────────────────────────────────────────────────

    public function test_cancel_muda_status_para_canceled(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);
        $order = Order::factory()->pending()->create(['proposta_id' => $proposta->id]);

        $cancelado = $this->service->cancel($order);

        $this->assertSame(OrderStatus::CANCELED, $cancelado->status);
    }

    public function test_cancel_lanca_excecao_para_pedido_nao_cancelavel(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        foreach (['approved', 'shipped', 'delivered'] as $state) {
            $order = Order::factory()->{$state}()->create(['proposta_id' => $proposta->id]);

            try {
                $this->service->cancel($order);
                $this->fail("Deveria ter lançado BusinessException para status '{$state}'");
            } catch (BusinessException) {
                $this->assertTrue(true);
            }
        }
    }

    // ── paginate ──────────────────────────────────────────────────────────────

    public function test_paginate_filtra_por_status(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        Order::factory()->pending()->create(['proposta_id' => $proposta->id]);
        Order::factory()->canceled()->create(['proposta_id' => $proposta->id]);

        $result = $this->service->paginate(OrderStatus::PENDING->value, 15);

        $this->assertCount(1, $result->items());
        $this->assertSame(OrderStatus::PENDING, $result->items()[0]->status);
    }

    public function test_paginate_sem_filtro_retorna_todos(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        Order::factory()->count(3)->create(['proposta_id' => $proposta->id]);

        $result = $this->service->paginate(null, 15);

        $this->assertCount(3, $result->items());
    }
}
