<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\PropostaOrigemEnum;

/**
 * DTO imutável para criação de uma Proposta.
 *
 * Substitui o array genérico em PropostaService::create(),
 * garantindo tipagem estrita e autocompletion nos IDEs.
 */
final readonly class CriarPropostaDTO
{
    public function __construct(
        public int               $clienteId,
        public string            $produto,
        public float             $valorMensal,
        public PropostaOrigemEnum $origem,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            clienteId:   (int) $data['cliente_id'],
            produto:     (string) $data['produto'],
            valorMensal: (float) $data['valor_mensal'],
            origem:      $data['origem'] instanceof PropostaOrigemEnum
                         ? $data['origem']
                         : PropostaOrigemEnum::from((string) $data['origem']),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'cliente_id'   => $this->clienteId,
            'produto'      => $this->produto,
            'valor_mensal' => $this->valorMensal,
            'origem'       => $this->origem->value,
        ];
    }
}
