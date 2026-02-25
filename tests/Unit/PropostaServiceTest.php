<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\AtualizarPropostaDTO;
use App\DTOs\CriarPropostaDTO;
use App\Enums\PropostaStatusEnum;
use App\Exceptions\BusinessException;
use App\Exceptions\ConcurrencyException;
use App\Filters\PropostaFilter;
use App\Models\Cliente;
use App\Models\Proposta;
use App\Services\PropostaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropostaServiceTest extends TestCase
{
    use RefreshDatabase;

    private PropostaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PropostaService(new PropostaFilter());
    }

    // ── create ────────────────────────────────────────────────────────────────

    public function test_create_sempre_forca_status_draft_e_versao_1(): void
    {
        $cliente = Cliente::factory()->create();

        $proposta = $this->service->create(CriarPropostaDTO::fromArray([
            'cliente_id'   => $cliente->id,
            'produto'      => 'Crédito',
            'valor_mensal' => 500.00,
            'origem'       => 'api',
            // 'status' e 'versao' são omitidos pois o DTO não os aceita
        ]));

        $this->assertSame(PropostaStatusEnum::DRAFT, $proposta->status);
        $this->assertSame(1, $proposta->versao);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_incrementa_versao(): void
    {
        $proposta = Proposta::factory()->draft()->create([
            'cliente_id' => Cliente::factory()->create()->id,
            'versao'     => 1,
        ]);

        $atualizado = $this->service->update($proposta, AtualizarPropostaDTO::fromArray([
            'versao'  => 1,
            'produto' => 'Novo produto',
        ]));

        $this->assertSame('Novo produto', $atualizado->produto);
        $this->assertSame(2, $atualizado->versao);
    }

    public function test_update_lanca_concurrency_exception_com_versao_desatualizada(): void
    {
        $proposta = Proposta::factory()->draft()->create([
            'cliente_id' => Cliente::factory()->create()->id,
            'versao'     => 3,
        ]);

        $this->expectException(ConcurrencyException::class);

        $this->service->update($proposta, AtualizarPropostaDTO::fromArray([
            'versao'  => 1, // diverge de 3
            'produto' => 'Conflito',
        ]));
    }

    public function test_update_lanca_business_exception_para_proposta_terminal(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
            'versao'     => 1,
        ]);

        $this->expectException(BusinessException::class);

        $this->service->update($proposta, AtualizarPropostaDTO::fromArray(['versao' => 1, 'produto' => 'Bloqueado']));
    }

    // ── submit ────────────────────────────────────────────────────────────────

    public function test_submit_transiciona_draft_para_submitted(): void
    {
        $proposta = Proposta::factory()->draft()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        $resultado = $this->service->submit($proposta);

        $this->assertSame(PropostaStatusEnum::SUBMITTED, $resultado->status);
    }

    public function test_submit_lanca_excecao_para_nao_draft(): void
    {
        $proposta = Proposta::factory()->submitted()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        $this->expectException(BusinessException::class);

        $this->service->submit($proposta);
    }

    // ── approve ───────────────────────────────────────────────────────────────

    public function test_approve_transiciona_submitted_para_approved(): void
    {
        $proposta = Proposta::factory()->submitted()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        $resultado = $this->service->approve($proposta);

        $this->assertSame(PropostaStatusEnum::APPROVED, $resultado->status);
    }

    public function test_approve_lanca_excecao_para_nao_submitted(): void
    {
        $proposta = Proposta::factory()->draft()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        $this->expectException(BusinessException::class);

        $this->service->approve($proposta);
    }

    // ── reject ────────────────────────────────────────────────────────────────

    public function test_reject_transiciona_submitted_para_rejected(): void
    {
        $proposta = Proposta::factory()->submitted()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        $resultado = $this->service->reject($proposta);

        $this->assertSame(PropostaStatusEnum::REJECTED, $resultado->status);
    }

    // ── cancel ────────────────────────────────────────────────────────────────

    public function test_cancel_permite_cancelar_draft_e_submitted(): void
    {
        $cliente = Cliente::factory()->create();

        $draft     = Proposta::factory()->draft()->create(['cliente_id' => $cliente->id]);
        $submitted = Proposta::factory()->submitted()->create(['cliente_id' => $cliente->id]);

        $this->assertSame(PropostaStatusEnum::CANCELED, $this->service->cancel($draft)->status);
        $this->assertSame(PropostaStatusEnum::CANCELED, $this->service->cancel($submitted)->status);
    }

    public function test_cancel_lanca_excecao_para_proposta_terminal(): void
    {
        $proposta = Proposta::factory()->approved()->create([
            'cliente_id' => Cliente::factory()->create()->id,
        ]);

        $this->expectException(BusinessException::class);

        $this->service->cancel($proposta);
    }

    // ── search ────────────────────────────────────────────────────────────────

    public function test_search_filtra_por_status(): void
    {
        $cliente = Cliente::factory()->create();
        Proposta::factory()->count(2)->draft()->create(['cliente_id' => $cliente->id]);
        Proposta::factory()->count(3)->approved()->create(['cliente_id' => $cliente->id]);

        $result = $this->service->search(['status' => 'draft'], 15);

        $this->assertCount(2, $result->items());
    }

    public function test_search_sort_invalido_nao_gera_erro(): void
    {
        $cliente = Cliente::factory()->create();
        Proposta::factory()->count(2)->draft()->create(['cliente_id' => $cliente->id]);

        $result = $this->service->search(['sort' => 'campo_malicioso'], 15);

        $this->assertCount(2, $result->items());
    }
}
