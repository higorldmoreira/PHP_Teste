<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Cliente;
use App\Models\Order;
use App\Models\Proposta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    // ── Criacao ───────────────────────────────────────────────────────────────

    public function test_cria_order_de_proposta_aprovada(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        $response = $this->postJson("/api/v1/propostas/{$proposta->id}/orders");

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['id', 'proposta_id', 'status', 'status_label', 'valor_total'],
        ]);
        $response->assertJsonPath('data.status', OrderStatus::PENDING->value);
        $response->assertJsonPath('data.proposta_id', $proposta->id);

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_nao_cria_order_de_proposta_nao_aprovada(): void
    {
        $prDraft = Proposta::factory()->draft()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);
        $prSubmitted = Proposta::factory()->submitted()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        $this->postJson("/api/v1/propostas/{$prDraft->id}/orders")->assertStatus(422);
        $this->postJson("/api/v1/propostas/{$prSubmitted->id}/orders")->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_nao_cria_order_duplicado_para_mesma_proposta(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        $this->postJson("/api/v1/propostas/{$proposta->id}/orders")->assertStatus(201);
        $this->postJson("/api/v1/propostas/{$proposta->id}/orders")->assertStatus(422);

        $this->assertDatabaseCount('orders', 1);
    }

    // ── Listagem ──────────────────────────────────────────────────────────────

    public function test_lista_orders_paginados(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        Order::factory()->count(3)->create(['proposta_id' => $proposta->id]);

        $response = $this->getJson('/api/v1/orders');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure(['data', 'meta', 'links']);
    }

    // ── Cancelamento ─────────────────────────────────────────────────────────

    public function test_cancela_order_pendente(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        $order = Order::factory()->pending()->create(['proposta_id' => $proposta->id]);

        $response = $this->postJson("/api/v1/orders/{$order->id}/cancel");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', OrderStatus::CANCELED->value);

        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => OrderStatus::CANCELED->value,
        ]);
    }

    public function test_nao_cancela_order_em_estado_terminal(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        $order = Order::factory()->delivered()->create(['proposta_id' => $proposta->id]);

        $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertStatus(422);
    }

    // ── Exibir por ID ───────────────────────────────────────────────────────────────────────

    public function test_exibe_order_pelo_id(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);
        $order = Order::factory()->pending()->create(['proposta_id' => $proposta->id]);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $order->id);
        $response->assertJsonPath('data.status', OrderStatus::PENDING->value);
    }

    // ── Filtro por status ──────────────────────────────────────────────────────────────────

    public function test_filtra_orders_por_status(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        Order::factory()->pending()->create(['proposta_id' => $proposta->id]);
        Order::factory()->canceled()->create(['proposta_id' => $proposta->id]);

        $response = $this->getJson('/api/v1/orders?status=' . OrderStatus::PENDING->value);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', OrderStatus::PENDING->value);
    }
}