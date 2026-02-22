<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PropostaStatusEnum;
use App\Models\Cliente;
use App\Models\Proposta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropostaStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Cria uma proposta com versão explícita 1 para deixar os testes
     * determinísticos, independente dos defaults aleatórios da factory.
     */
    private function criarProposta(string $state): Proposta
    {
        $cliente = Cliente::factory()->create();

        return Proposta::factory()
            ->{$state}()
            ->create([
                'cliente_id' => $cliente->id,
                'versao'     => 1,
            ]);
    }

    // -------------------------------------------------------------------------
    // Testes Válidos
    // -------------------------------------------------------------------------

    /**
     * Teste 1 — DRAFT → SUBMITTED
     *
     * Uma proposta em DRAFT deve poder ser submetida:
     *  - HTTP 200
     *  - status = 'submitted'
     *  - versão incrementada de 1 para 2
     */
    public function test_draft_pode_ser_submetida_e_retorna_200(): void
    {
        $proposta = $this->criarProposta('draft');

        $response = $this->postJson("/api/v1/propostas/{$proposta->id}/submit");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', PropostaStatusEnum::SUBMITTED->value);
        $response->assertJsonPath('data.versao', 2);

        $this->assertDatabaseHas('propostas', [
            'id'     => $proposta->id,
            'status' => PropostaStatusEnum::SUBMITTED->value,
            'versao' => 2,
        ]);
    }

    /**
     * Teste 2 — SUBMITTED → APPROVED
     *
     * Uma proposta submetida deve poder ser aprovada:
     *  - HTTP 200
     *  - status = 'approved'
     *  - versão incrementada de 1 para 2
     */
    public function test_submitted_pode_ser_aprovada_e_retorna_200(): void
    {
        $proposta = $this->criarProposta('submitted');

        $response = $this->postJson("/api/v1/propostas/{$proposta->id}/approve");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', PropostaStatusEnum::APPROVED->value);
        $response->assertJsonPath('data.versao', 2);

        $this->assertDatabaseHas('propostas', [
            'id'     => $proposta->id,
            'status' => PropostaStatusEnum::APPROVED->value,
            'versao' => 2,
        ]);
    }

    // -------------------------------------------------------------------------
    // Testes Inválidos — Transição proibida
    // -------------------------------------------------------------------------

    /**
     * Teste 3 — DRAFT não pode ser aprovada diretamente
     *
     * Tentar aprovar uma proposta em DRAFT deve lançar BusinessException:
     *  - HTTP 422 Unprocessable Entity
     *  - JSON com campo 'error' = 'Unprocessable Entity'
     *  - Status no banco permanece 'draft'
     */
    public function test_draft_nao_pode_ser_aprovada_e_retorna_422(): void
    {
        $proposta = $this->criarProposta('draft');

        $response = $this->postJson("/api/v1/propostas/{$proposta->id}/approve");

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Unprocessable Entity');
        $response->assertJsonStructure(['error', 'message']);

        // Status e versão devem permanecer inalterados
        $this->assertDatabaseHas('propostas', [
            'id'     => $proposta->id,
            'status' => PropostaStatusEnum::DRAFT->value,
            'versao' => 1,
        ]);
    }

    // -------------------------------------------------------------------------
    // Testes Inválidos — Estado Terminal
    // -------------------------------------------------------------------------

    /**
     * Teste 4a — APPROVED não pode ser cancelada
     *
     * Tentar cancelar uma proposta aprovada deve lançar BusinessException:
     *  - HTTP 422 Unprocessable Entity
     *  - Status permanece 'approved' no banco
     */
    public function test_approved_nao_pode_ser_cancelada_e_retorna_422(): void
    {
        $proposta = $this->criarProposta('approved');

        $response = $this->postJson("/api/v1/propostas/{$proposta->id}/cancel");

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Unprocessable Entity');

        $this->assertDatabaseHas('propostas', [
            'id'     => $proposta->id,
            'status' => PropostaStatusEnum::APPROVED->value,
        ]);
    }

    /**
     * Teste 4b — APPROVED não pode ser editada via PATCH
     *
     * Tentar atualizar campos de uma proposta em estado terminal deve
     * lançar BusinessException:
     *  - HTTP 422 Unprocessable Entity
     *  - Dados no banco permanecem inalterados
     */
    public function test_approved_nao_pode_ser_editada_via_patch_e_retorna_422(): void
    {
        $proposta = $this->criarProposta('approved');
        $produtoOriginal = $proposta->produto;

        $response = $this->patchJson("/api/v1/propostas/{$proposta->id}", [
            'versao'  => 1,
            'produto' => 'Tentativa de alteração ilegal',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Unprocessable Entity');

        // Produto no banco deve permanecer o original
        $this->assertDatabaseHas('propostas', [
            'id'      => $proposta->id,
            'produto' => $produtoOriginal,
            'versao'  => 1,
        ]);
    }
}
