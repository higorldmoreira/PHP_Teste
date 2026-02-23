<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PropostaStatusEnum;
use App\Models\Cliente;
use App\Models\Proposta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropostaSearchTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Teste 1 — Filtro por status
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/propostas?status=draft deve retornar apenas propostas DRAFT.
     */
    public function test_filtra_propostas_por_status(): void
    {
        $cliente = Cliente::factory()->create();

        // 3 DRAFTs + 2 APPROVEDs
        Proposta::factory()->count(3)->draft()->create(['cliente_id' => $cliente->id]);
        Proposta::factory()->count(2)->approved()->create(['cliente_id' => $cliente->id]);

        $response = $this->getJson('/api/v1/propostas?status=draft');

        $response->assertStatus(200);

        // Exatamente 3 itens retornados
        $response->assertJsonCount(3, 'data');

        // Todos os itens devem ter status 'draft'
        $response->assertJsonPath('data.0.status', PropostaStatusEnum::DRAFT->value);
        $response->assertJsonPath('data.1.status', PropostaStatusEnum::DRAFT->value);
        $response->assertJsonPath('data.2.status', PropostaStatusEnum::DRAFT->value);

        // Nenhum 'approved' no resultado
        collect($response->json('data'))->each(function (array $item): void {
            $this->assertSame(PropostaStatusEnum::DRAFT->value, $item['status']);
        });
    }

    // -------------------------------------------------------------------------
    // Teste 2 — Filtro por cliente
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/propostas?cliente_id=X deve retornar apenas propostas
     * pertencentes ao cliente X.
     */
    public function test_filtra_propostas_por_cliente(): void
    {
        $clienteAlvo  = Cliente::factory()->create();
        $clienteOutro = Cliente::factory()->create();

        // 2 propostas do cliente-alvo, 3 do outro
        Proposta::factory()->count(2)->draft()->create(['cliente_id' => $clienteAlvo->id]);
        Proposta::factory()->count(3)->draft()->create(['cliente_id' => $clienteOutro->id]);

        $response = $this->getJson("/api/v1/propostas?cliente_id={$clienteAlvo->id}");

        $response->assertStatus(200);

        // Exatamente 2 itens — somente do cliente-alvo
        $response->assertJsonCount(2, 'data');

        // Todos os itens pertencem ao cliente-alvo
        collect($response->json('data'))->each(function (array $item) use ($clienteAlvo): void {
            $this->assertSame($clienteAlvo->id, $item['cliente_id']);
        });
    }

    // -------------------------------------------------------------------------
    // Teste 3 — Paginação
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/propostas?per_page=2 deve paginar com 2 itens por página
     * e retornar os metadados de paginação do Laravel (meta + links).
     */
    public function test_paginacao_retorna_meta_e_links(): void
    {
        $cliente = Cliente::factory()->create();

        // 5 propostas no total para garantir mais de uma página
        Proposta::factory()->count(5)->draft()->create(['cliente_id' => $cliente->id]);

        $response = $this->getJson('/api/v1/propostas?per_page=2');

        $response->assertStatus(200);

        // Página atual com 2 itens
        $response->assertJsonCount(2, 'data');

        // Metadados de paginação presentes
        $response->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta'  => [
                'current_page',
                'from',
                'last_page',
                'per_page',
                'to',
                'total',
            ],
        ]);

        // Valores esperados
        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.per_page', 2);
        $response->assertJsonPath('meta.total', 5);
        $response->assertJsonPath('meta.last_page', 3);
    }

    // -------------------------------------------------------------------------
    // Teste 4 — Ordenação por campo
    // -------------------------------------------------------------------------

    public function test_ordena_propostas_por_campo(): void
    {
        $cliente = Cliente::factory()->create();

        Proposta::factory()->draft()->create(['cliente_id' => $cliente->id, 'valor_mensal' => 100.00]);
        Proposta::factory()->draft()->create(['cliente_id' => $cliente->id, 'valor_mensal' => 300.00]);
        Proposta::factory()->draft()->create(['cliente_id' => $cliente->id, 'valor_mensal' => 200.00]);

        $response = $this->getJson('/api/v1/propostas?sort=valor_mensal&direction=asc');

        $response->assertStatus(200);
        $items = $response->json('data');

        $this->assertSame(100.0, (float) $items[0]['valor_mensal']);
        $this->assertSame(200.0, (float) $items[1]['valor_mensal']);
        $this->assertSame(300.0, (float) $items[2]['valor_mensal']);
    }

    // -------------------------------------------------------------------------
    // Teste 5 — Sort inválido cai no padrão
    // -------------------------------------------------------------------------

    public function test_sort_invalido_usa_padrao(): void
    {
        $cliente = Cliente::factory()->create();
        Proposta::factory()->count(3)->draft()->create(['cliente_id' => $cliente->id]);

        // Campo inexistente na allowlist não deve causar erro
        $response = $this->getJson('/api/v1/propostas?sort=campo_invalido&direction=desc');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }
}
