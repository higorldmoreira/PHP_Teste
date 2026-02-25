<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * DTO imutável para criação de um pedido (Order).
 */
final readonly class CriarOrderDTO
{
    public function __construct(
        public ?string $observacoes = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            observacoes: isset($data['observacoes']) ? (string) $data['observacoes'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter(
            ['observacoes' => $this->observacoes],
            fn($v) => $v !== null,
        );
    }
}
