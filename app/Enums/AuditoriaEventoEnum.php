<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditoriaEventoEnum: string
{
    case CREATED          = 'created';
    case UPDATED_FIELDS   = 'updated_fields';
    case STATUS_CHANGED   = 'status_changed';
    case DELETED_LOGICAL  = 'deleted_logical';

    public function label(): string
    {
        return match($this) {
            self::CREATED         => 'Proposta criada',
            self::UPDATED_FIELDS  => 'Campos alterados',
            self::STATUS_CHANGED  => 'Status alterado',
            self::DELETED_LOGICAL => 'Exclusão lógica (soft delete)',
        };
    }

    /**
     * Todos os values como array — para uso em validações:
     * Rule::in(AuditoriaEventoEnum::values())
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
