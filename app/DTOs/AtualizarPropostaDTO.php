<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * DTO imutável para atualização de uma Proposta com Optimistic Lock.
 *
 * O campo `versao` é obrigatório para validar o lock otimista.
 * Os campos de conteúdo são opcionais — apenas os presentes serão atualizados.
 */
final readonly class AtualizarPropostaDTO
{
    public function __construct(
        public int      $versao,
        public ?string  $produto      = null,
        public ?float   $valorMensal  = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            versao:      (int) $data['versao'],
            produto:     isset($data['produto'])      ? (string) $data['produto']      : null,
            valorMensal: isset($data['valor_mensal']) ? (float)  $data['valor_mensal'] : null,
        );
    }

    /**
     * Retorna apenas os campos de conteúdo que foram preenchidos.
     * Nunca inclui 'versao' — esse campo é gerenciado internamente pelo service.
     *
     * @return array<string, mixed>
     */
    public function changedFields(): array
    {
        $fields = [];

        if ($this->produto !== null) {
            $fields['produto'] = $this->produto;
        }

        if ($this->valorMensal !== null) {
            $fields['valor_mensal'] = $this->valorMensal;
        }

        return $fields;
    }
}
