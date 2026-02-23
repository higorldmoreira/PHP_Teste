<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditoriaEventoEnum;
use App\Enums\PropostaOrigemEnum;
use App\Models\Cliente;
use App\Models\Proposta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authAsUser();
    }

    /**
     * Fluxo: criar proposta (1 log CREATED) → submeter (1 log STATUS_CHANGED)
     *
     * GET /api/v1/propostas/{id}/auditoria deve:
     *  - retornar HTTP 200
     *  - retornar exatamente 2 registros
     *  - ordenar do mais recente para o mais antigo
     *    (STATUS_CHANGED primeiro, CREATED por último)
     */
    public function test_auditoria_registra_created_e_status_changed_em_ordem_decrescente(): void
    {
        // ── Arrange ───────────────────────────────────────────────────────────

        $cliente = Cliente::factory()->create();

        // Criação via rota (dispara PropostaObserver::created → 1 log)
        $criarResponse = $this->postJson('/api/v1/propostas', [
            'cliente_id'   => $cliente->id,
            'produto'      => 'Seguro de Vida',
            'valor_mensal' => 250.00,
            'origem'       => PropostaOrigemEnum::APP->value,
        ], ['Idempotency-Key' => 'auditoria-test-key-001']);

        $criarResponse->assertStatus(201);
        $propostaId = $criarResponse->json('data.id');

        // Submissão via rota (dispara PropostaObserver::updated → 1 log STATUS_CHANGED)
        $this->postJson("/api/v1/propostas/{$propostaId}/submit")
             ->assertStatus(200);

        // ── Act ───────────────────────────────────────────────────────────────

        $response = $this->getJson("/api/v1/propostas/{$propostaId}/auditoria");

        // ── Assert ────────────────────────────────────────────────────────────

        $response->assertStatus(200);

        // Exatamente 2 registros de auditoria
        $response->assertJsonCount(2, 'data');

        // Estrutura de cada item
        $response->assertJsonStructure([
            'data' => [
                '*' => ['actor', 'evento', 'payload', 'created_at'],
            ],
        ]);

        $dados = $response->json('data');

        // Ordem decrescente: o mais recente (STATUS_CHANGED) deve vir primeiro
        $this->assertSame(
            AuditoriaEventoEnum::STATUS_CHANGED->value,
            $dados[0]['evento'],
            'O primeiro registro deve ser o mais recente (STATUS_CHANGED)',
        );

        $this->assertSame(
            AuditoriaEventoEnum::CREATED->value,
            $dados[1]['evento'],
            'O segundo registro deve ser o mais antigo (CREATED)',
        );

        // Banco deve ter exatamente 2 registros para esta proposta
        $this->assertDatabaseCount('auditoria_propostas', 2);
    }
}
