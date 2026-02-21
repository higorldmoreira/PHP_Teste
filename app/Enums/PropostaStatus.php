<?php

declare(strict_types=1);

namespace App\Enums;

enum PropostaStatus: string
{
    case Rascunho  = 'rascunho';
    case Enviada   = 'enviada';
    case EmAnalise = 'em_analise';
    case Aprovada  = 'aprovada';
    case Recusada  = 'recusada';
    case Cancelada = 'cancelada';
    case Expirada  = 'expirada';

    public function label(): string
    {
        return match($this) {
            self::Rascunho  => 'Rascunho',
            self::Enviada   => 'Enviada',
            self::EmAnalise => 'Em análise',
            self::Aprovada  => 'Aprovada',
            self::Recusada  => 'Recusada',
            self::Cancelada => 'Cancelada',
            self::Expirada  => 'Expirada',
        };
    }

    /** Status que permitem edição da proposta. */
    public function isEditavel(): bool
    {
        return in_array($this, [self::Rascunho, self::Recusada], strict: true);
    }

    /** Status que permitem cancelamento. */
    public function isCancelavel(): bool
    {
        return in_array($this, [self::Enviada, self::EmAnalise], strict: true);
    }

    /** Status terminais — sem transição possível. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Aprovada, self::Cancelada, self::Expirada], strict: true);
    }

    /**
     * Para usar em Form Requests:  Rule::in(PropostaStatus::values())
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
