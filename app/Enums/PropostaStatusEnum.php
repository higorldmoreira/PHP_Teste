<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\StatusEnumInterface;

enum PropostaStatusEnum: string implements StatusEnumInterface
{
    case DRAFT     = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED  = 'approved';
    case REJECTED  = 'rejected';
    case CANCELED  = 'canceled';

    public function label(): string
    {
        return match($this) {
            self::DRAFT     => 'Rascunho',
            self::SUBMITTED => 'Enviada',
            self::APPROVED  => 'Aprovada',
            self::REJECTED  => 'Rejeitada',
            self::CANCELED  => 'Cancelada',
        };
    }

    /**
     * Retorna true para status que não permitem mais nenhuma transição.
     * Útil para impedir edições, cancelamentos ou reenvios de propostas encerradas.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::APPROVED,
            self::REJECTED,
            self::CANCELED,
        ], strict: true);
    }

    /** Retorna true se a proposta ainda pode ser editada (apenas DRAFT). */
    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    /** Retorna true se a proposta pode ser cancelada (DRAFT ou SUBMITTED). */
    public function isCancelable(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::SUBMITTED,
        ], strict: true);
    }

    /** Alias de isCancelable() — satisfaz o contrato StatusEnumInterface. */
    public function isCancellable(): bool
    {
        return $this->isCancelable();
    }

    /**
     * Todos os values como array — para uso em validações:
     * Rule::in(PropostaStatusEnum::values())
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
