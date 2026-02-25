<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * DTO imutável para criação de um Cliente.
 */
final readonly class CriarClienteDTO
{
    public function __construct(
        public string $nome,
        public string $email,
        public string $documento,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            nome:      (string) $data['nome'],
            email:     (string) $data['email'],
            documento: (string) $data['documento'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'nome'      => $this->nome,
            'email'     => $this->email,
            'documento' => $this->documento,
        ];
    }
}
