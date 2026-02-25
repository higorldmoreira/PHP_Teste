<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class IdempotencyKeyTest extends TestCase
{
    public function test_retorna_um_uuid_por_padrao(): void
    {
        $response = $this->getJson('/api/idempotency-key');

        $response->assertOk()
            ->assertJsonStructure(['keys', 'qty', 'usage'])
            ->assertJsonPath('qty', 1);

        $keys = $response->json('keys');
        $this->assertCount(1, $keys);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $keys[0]
        );
    }

    public function test_retorna_multiplos_uuids_quando_qty_informado(): void
    {
        $response = $this->getJson('/api/idempotency-key?qty=5');

        $response->assertOk()
            ->assertJsonPath('qty', 5);

        $keys = $response->json('keys');
        $this->assertCount(5, $keys);

        // Todos devem ser UUIDs v4 válidos
        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                $key
            );
        }

        // Todos devem ser únicos
        $this->assertSame(count($keys), count(array_unique($keys)));
    }

    public function test_usage_aponta_para_o_primeiro_uuid(): void
    {
        $response = $this->getJson('/api/idempotency-key?qty=3');

        $response->assertOk();

        $keys  = $response->json('keys');
        $usage = $response->json('usage');

        $this->assertStringContainsString($keys[0], $usage);
    }

    public function test_rejeita_qty_zero(): void
    {
        $this->getJson('/api/idempotency-key?qty=0')
            ->assertUnprocessable()
            ->assertJsonPath('error', 'Unprocessable Entity');
    }

    public function test_rejeita_qty_acima_do_limite(): void
    {
        $this->getJson('/api/idempotency-key?qty=11')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'O parâmetro qty deve ser entre 1 e 10.');
    }

    public function test_rejeita_qty_negativo(): void
    {
        $this->getJson('/api/idempotency-key?qty=-1')
            ->assertUnprocessable();
    }

    public function test_uuid_gerado_funciona_como_idempotency_key_real(): void
    {
        // Gera a chave pela API
        $keyResponse = $this->getJson('/api/idempotency-key');
        $keyResponse->assertOk();

        $idempotencyKey = $keyResponse->json('keys.0');

        // Usa a chave em uma requisição com dados inválidos (valida só o formato da chave)
        $response = $this->postJson('/api/v1/propostas', [], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        // 422 de validação de campos — a chave foi aceita, a req foi ao Controller
        $response->assertUnprocessable();

        // Replay com a mesma chave: req anterior não foi 2xx → não foi cacheada
        // logo, não retorna X-Idempotency-Replayed
        $response2 = $this->postJson('/api/v1/propostas', [], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $response2->assertUnprocessable();
        $this->assertNull($response2->headers->get('X-Idempotency-Replayed'));
    }
}
