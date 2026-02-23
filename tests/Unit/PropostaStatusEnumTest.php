<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PropostaStatusEnum;
use PHPUnit\Framework\TestCase;

class PropostaStatusEnumTest extends TestCase
{
    // ── isTerminal ────────────────────────────────────────────────────────────

    public function test_approved_e_terminal(): void
    {
        $this->assertTrue(PropostaStatusEnum::APPROVED->isTerminal());
    }

    public function test_rejected_e_terminal(): void
    {
        $this->assertTrue(PropostaStatusEnum::REJECTED->isTerminal());
    }

    public function test_canceled_e_terminal(): void
    {
        $this->assertTrue(PropostaStatusEnum::CANCELED->isTerminal());
    }

    public function test_draft_nao_e_terminal(): void
    {
        $this->assertFalse(PropostaStatusEnum::DRAFT->isTerminal());
    }

    public function test_submitted_nao_e_terminal(): void
    {
        $this->assertFalse(PropostaStatusEnum::SUBMITTED->isTerminal());
    }

    // ── isEditable ────────────────────────────────────────────────────────────

    public function test_somente_draft_e_editavel(): void
    {
        $this->assertTrue(PropostaStatusEnum::DRAFT->isEditable());

        foreach ([PropostaStatusEnum::SUBMITTED, PropostaStatusEnum::APPROVED, PropostaStatusEnum::REJECTED, PropostaStatusEnum::CANCELED] as $status) {
            $this->assertFalse($status->isEditable(), "Status {$status->value} não deveria ser editável");
        }
    }

    // ── isCancelable ──────────────────────────────────────────────────────────

    public function test_draft_e_submitted_sao_cancelaveis(): void
    {
        $this->assertTrue(PropostaStatusEnum::DRAFT->isCancelable());
        $this->assertTrue(PropostaStatusEnum::SUBMITTED->isCancelable());
    }

    public function test_terminais_nao_sao_cancelaveis(): void
    {
        foreach ([PropostaStatusEnum::APPROVED, PropostaStatusEnum::REJECTED, PropostaStatusEnum::CANCELED] as $status) {
            $this->assertFalse($status->isCancelable(), "Status {$status->value} não deveria ser cancelável");
        }
    }

    // ── label ─────────────────────────────────────────────────────────────────

    public function test_labels_retornam_texto_legivel(): void
    {
        $this->assertSame('Rascunho', PropostaStatusEnum::DRAFT->label());
        $this->assertSame('Enviada', PropostaStatusEnum::SUBMITTED->label());
        $this->assertSame('Aprovada', PropostaStatusEnum::APPROVED->label());
        $this->assertSame('Rejeitada', PropostaStatusEnum::REJECTED->label());
        $this->assertSame('Cancelada', PropostaStatusEnum::CANCELED->label());
    }

    // ── values ────────────────────────────────────────────────────────────────

    public function test_values_retorna_todos_os_valores_string(): void
    {
        $expected = ['draft', 'submitted', 'approved', 'rejected', 'canceled'];
        $this->assertSame($expected, PropostaStatusEnum::values());
    }
}
