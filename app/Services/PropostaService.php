<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PropostaStatusEnum;
use App\Exceptions\BusinessException;
use App\Exceptions\ConcurrencyException;
use App\Models\Proposta;
use Illuminate\Support\Facades\DB;

/**
 * PropostaService
 *
 * Camada de serviço responsável por toda a lógica de negócio do ciclo
 * de vida de uma Proposta: criação, atualização com lock otimista e
 * máquina de estados de status.
 *
 * Regras:
 *  - Todo método que persiste dados usa DB::transaction() via $this->transaction().
 *  - Propostas em estado terminal (APPROVED, REJECTED, CANCELED) são imutáveis.
 *  - Conflito de versão lança ConcurrencyException (HTTP 409).
 *  - Transição inválida lança BusinessException (HTTP 422).
 *
 */
class PropostaService
{

    /**
     * Cria uma nova proposta forçando status DRAFT e versão 1,
     * independentemente do que vier em $data.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \Throwable
     */
    public function create(array $data): Proposta
    {
        return DB::transaction(function () use ($data): Proposta {
            /** @var Proposta $proposta */
            $proposta = Proposta::create([
                ...$data,
                'status' => PropostaStatusEnum::DRAFT->value,
                'versao' => 1,
            ]);

            return $proposta;
        });
    }

    /**
     * Atualiza campos da proposta após validar lock otimista.
     *
     * Fluxo:
     *  1. Rejeita se a proposta está em estado terminal.
     *  2. Compara $data['versao'] com $proposta->versao — lança
     *     ConcurrencyException se divergirem.
     *  3. Incrementa versao e salva os dados.
     *
     * @param  array<string, mixed>  $data  Deve conter a chave 'versao'.
     *
     * @throws BusinessException      Se a proposta estiver em estado terminal.
     * @throws ConcurrencyException   Se a versão enviada divergir da persistida.
     * @throws \Throwable
     */
    public function update(Proposta $proposta, array $data): Proposta
    {
        return DB::transaction(function () use ($proposta, $data): Proposta {
            if ($proposta->status->isTerminal()) {
                throw BusinessException::because(
                    "A proposta #{$proposta->id} está em estado terminal"
                    . " ({$proposta->status->value}) e não pode ser editada."
                );
            }

            $versaoEnviada = (int) ($data['versao'] ?? -1);

            if ($versaoEnviada !== $proposta->versao) {
                throw ConcurrencyException::versaoDesatualizada();
            }

            // Remove 'versao' e campos imutáveis de $data para não sobrescrever
            // via fillable acidentalmente antes de incrementar
            unset($data['versao'], $data['status']);

            $proposta->fill($data);
            $proposta->versao += 1;
            $proposta->save();

            return $proposta->refresh();
        });
    }

    /**
     * DRAFT → SUBMITTED
     *
     * Submete a proposta para análise. Somente propostas em DRAFT
     * podem ser submetidas.
     *
     * @throws BusinessException
     * @throws \Throwable
     */
    public function submit(Proposta $proposta): Proposta
    {
        if ($proposta->status !== PropostaStatusEnum::DRAFT) {
            throw BusinessException::because(
                "Apenas propostas em rascunho (DRAFT) podem ser submetidas. "
                . "Status atual: {$proposta->status->value}."
            );
        }

        return $this->transition($proposta, PropostaStatusEnum::SUBMITTED);
    }

    /**
     * SUBMITTED → APPROVED
     *
     * Aprova a proposta. Somente propostas aguardando análise (SUBMITTED)
     * podem ser aprovadas.
     *
     * @throws BusinessException
     * @throws \Throwable
     */
    public function approve(Proposta $proposta): Proposta
    {
        if ($proposta->status !== PropostaStatusEnum::SUBMITTED) {
            throw BusinessException::because(
                "Apenas propostas submetidas (SUBMITTED) podem ser aprovadas. "
                . "Status atual: {$proposta->status->value}."
            );
        }

        return $this->transition($proposta, PropostaStatusEnum::APPROVED);
    }

    /**
     * SUBMITTED → REJECTED
     *
     * Rejeita a proposta. Somente propostas aguardando análise (SUBMITTED)
     * podem ser rejeitadas.
     *
     * @throws BusinessException
     * @throws \Throwable
     */
    public function reject(Proposta $proposta): Proposta
    {
        if ($proposta->status !== PropostaStatusEnum::SUBMITTED) {
            throw BusinessException::because(
                "Apenas propostas submetidas (SUBMITTED) podem ser rejeitadas. "
                . "Status atual: {$proposta->status->value}."
            );
        }

        return $this->transition($proposta, PropostaStatusEnum::REJECTED);
    }

    /**
     * DRAFT | SUBMITTED → CANCELED
     *
     * Cancela a proposta. Propostas já em estado terminal não podem
     * ser canceladas (isTerminal() cobre APPROVED, REJECTED e CANCELED).
     *
     * @throws BusinessException
     * @throws \Throwable
     */
    public function cancel(Proposta $proposta): Proposta
    {
        if ($proposta->status->isTerminal()) {
            throw BusinessException::because(
                "A proposta #{$proposta->id} já está em estado terminal"
                . " ({$proposta->status->value}) e não pode ser cancelada."
            );
        }

        return $this->transition($proposta, PropostaStatusEnum::CANCELED);
    }

    /**
     * Aplica a transição de status e incrementa a versão atomicamente.
     * Centraliza a persistência para evitar duplicação nos métodos públicos.
     *
     * @throws \Throwable
     */
    private function transition(Proposta $proposta, PropostaStatusEnum $novoStatus): Proposta
    {
        return DB::transaction(function () use ($proposta, $novoStatus): Proposta {
            $proposta->status = $novoStatus;
            $proposta->versao += 1;
            $proposta->save();

            return $proposta->refresh();
        });
    }
}
