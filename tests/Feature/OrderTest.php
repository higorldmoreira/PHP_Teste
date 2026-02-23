<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PropostaStatusEnum;
use App\Models\Cliente;
use App\Models\Order;
use App\Models\Proposta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->authAsUser();
    }

    // ── Criação ────────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/propostas/{id}/orders com proposta APPROVED deve criar Order com status pending.
     */
    public function test_cria_order_de_proposta_aprovada(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        $response = $this->postJson("/api/v1/propostas/{$proposta->id}/orders");

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['id', 'proposta_id', 'user_id', 'status', 'status_label', 'valor_total'],
        ]);
        $response->assertJsonPath('data.status', OrderStatus::Pending->value);
        $response->assertJsonPath('data.proposta_id', $proposta->id);
        $response->assertJsonPath('data.user_id', $this->user->id);

        $this->assertDatabaseCount('orders', 1);
    }

    /**
     * Proposta em DRAFT não pode gerar order — deve retornar 422.
     */
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

    /**
     * Proposta aprovada não pode ter mais de um order ativo.
     */
    public function test_nao_cria_order_duplicado_para_mesma_proposta(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        // Primeiro order — deve funcionar
        $this->postJson("/api/v1/propostas/{$proposta->id}/orders")->assertStatus(201);

        // Segundo order para a mesma proposta — deve falhar
        $this->postJson("/api/v1/propostas/{$proposta->id}/orders")->assertStatus(422);

        $this->assertDatabaseCount('orders', 1);
    }

    // ── Listagem ───────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/orders deve retornar apenas orders do usuário autenticado.
     */
    public function test_lista_apenas_orders_do_usuario_autenticado(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        // Order do usuário autenticado
        Order::factory()->create([
            'proposta_id' => $proposta->id,
            'user_id'     => $this->user->id,
        ]);

        // Order de outro usuário
        Order::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->getJson('/api/v1/orders');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.user_id', $this->user->id);
    }

    // ── Cancelamento ──────────────────────────────────────────────────────────

    /**
     * POST /api/v1/orders/{id}/cancel deve cancelar um order pendente.
     */
    public function test_cancela_order_pendente(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        $order = Order::factory()->pending()->create([
            'proposta_id' => $proposta->id,
            'user_id'     => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/orders/{$order->id}/cancel");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => OrderStatus::Cancelled->value,
        ]);
    }

    /**
     * Order entregue (terminal) não pode ser cancelado — 422.
     */
    public function test_nao_cancela_order_em_estado_terminal(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        $order = Order::factory()->delivered()->create([
            'proposta_id' => $proposta->id,
            'user_id'     => $this->user->id,
        ]);

        $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertStatus(422);
    }

    /**
     * Usuário não pode cancelar order de outro usuário — 403.
     */
    public function test_usuario_nao_pode_cancelar_order_de_outro(): void
    {
        $outroUser = User::factory()->create();
        $proposta  = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        $order = Order::factory()->pending()->create([
            'proposta_id' => $proposta->id,
            'user_id'     => $outroUser->id,
        ]);

        $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertStatus(403);
    }
}
