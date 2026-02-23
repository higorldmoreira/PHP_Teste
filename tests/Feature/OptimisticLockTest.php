<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PropostaStatusEnum;
use App\Models\Cliente;
use App\Models\Proposta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OptimisticLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authAsUser();
    }

    /**
     * Cria um par Cliente + Proposta em DRAFT com versão inicial 1.
     */
    private function criarProposta(): Proposta
    {
        $cliente = Cliente::factory()->create();

        return Proposta::factory()->create([
            'cliente_id' => $cliente->id,
            'status'     => PropostaStatusEnum::DRAFT->value,
            'versao'     => 1,
        ]);
    }

    /**
     * PATCH com a versão correta deve atualizar a proposta e
     * incrementar a versão para 2.
     */
    public function test_update_com_versao_correta_retorna_200_e_incrementa_versao(): void
    {
        $proposta = $this->criarProposta();

        $response = $this->patchJson("/api/v1/propostas/{$proposta->id}", [
            'versao'  => 1,
            'produto' => 'Seguro de Vida Atualizado',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.versao', 2);
        $response->assertJsonPath('data.produto', 'Seguro de Vida Atualizado');

        $this->assertDatabaseHas('propostas', [
            'id'     => $proposta->id,
            'versao' => 2,
        ]);
    }

    /**
     * PATCH com versão desatualizada (conflito de concorrência otimista)
     * deve retornar HTTP 409 Conflict.
     */
    public function test_update_com_versao_desatualizada_retorna_409_conflict(): void
    {
        $proposta = $this->criarProposta();

        // Primeira atualização — sucesso, versão vai para 2
        $this->patchJson("/api/v1/propostas/{$proposta->id}", [
            'versao'  => 1,
            'produto' => 'Primeiro Update',
        ])->assertStatus(200);

        // Segunda tentativa com versão antiga (1) — deve falhar com 409
        $response = $this->patchJson("/api/v1/propostas/{$proposta->id}", [
            'versao'  => 1,
            'produto' => 'Segundo Update Conflitante',
        ]);

        $response->assertStatus(409);
        $response->assertJsonStructure(['error', 'message']);
        $response->assertJsonPath('error', 'Conflict');

        // Proposta deve permanecer com a versão do primeiro update (2)
        $this->assertDatabaseHas('propostas', [
            'id'     => $proposta->id,
            'versao' => 2,
        ]);
    }
}
