<?php

declare(strict_types=1);

namespace App\Enums;

enum PropostaOrigemEnum: string
{
    case APP  = 'app';
    case SITE = 'site';
    case API  = 'api';

    public function label(): string
    {
        return match($this) {
            self::APP  => 'Aplicativo Mobile',
            self::SITE => 'Site',
            self::API  => 'Integração API',
        };
    }

    /**
     * Todos os values como array — para uso em validações:
     * Rule::in(PropostaOrigemEnum::values())
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
