<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PropostaOrigemEnum;
use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private const IDEMPOTENCY_KEY = 'test-idempotency-key-proposta-001';

    /**
     * Payload válido para criação de proposta, reutilizado em ambos os testes.
     *
     * @return array<string, mixed>
     */
    private function payload(int $clienteId): array
    {
        return [
            'cliente_id'   => $clienteId,
            'produto'      => 'Empréstimo Pessoal',
            'valor_mensal' => 350.00,
            'origem'       => PropostaOrigemEnum::API->value,
        ];
    }

    /**
     * A primeira requisição POST com Idempotency-Key deve criar a proposta
     * e retornar HTTP 201 Created.
     */
    public function test_primeira_requisicao_cria_proposta_e_retorna_201(): void
    {
        $cliente = Cliente::factory()->create();

        $response = $this->postJson(
            '/api/v1/propostas',
            $this->payload($cliente->id),
            ['Idempotency-Key' => self::IDEMPOTENCY_KEY],
        );

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'produto',
                'status',
                'versao',
            ],
        ]);

        $this->assertDatabaseCount('propostas', 1);
    }

    /**
     * A segunda requisição idêntica deve:
     *  - retornar o mesmo body da primeira (resposta cacheada)
     *  - retornar HTTP 201 (status original preservado)
     *  - conter o header X-Idempotency-Replayed: true
     *  - NÃO criar um segundo registro no banco
     */
    public function test_requisicao_repetida_retorna_cache_com_header_replayed(): void
    {
        $cliente = Cliente::factory()->create();

        $headers = ['Idempotency-Key' => self::IDEMPOTENCY_KEY];
        $payload = $this->payload($cliente->id);

        // Primeira requisição — processa e armazena no cache
        $primeira = $this->postJson('/api/v1/propostas', $payload, $headers);
        $primeira->assertStatus(201);

        // Segunda requisição — deve ser servida do cache
        $segunda = $this->postJson('/api/v1/propostas', $payload, $headers);

        $segunda->assertStatus(201);
        $segunda->assertHeader('X-Idempotency-Replayed', 'true');

        // Os bodies devem ser idênticos
        $this->assertSame(
            $primeira->getContent(),
            $segunda->getContent(),
            'O body da resposta cacheada deve ser idêntico ao da resposta original.',
        );

        // Apenas 1 registro deve ter sido criado, mesmo com 2 requisições
        $this->assertDatabaseCount('propostas', 1);
    }
}
