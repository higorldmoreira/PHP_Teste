<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\AtualizarPropostaDTO;
use App\DTOs\CriarPropostaDTO;
use App\Enums\PropostaStatusEnum;
use App\Exceptions\BusinessException;
use App\Exceptions\ConcurrencyException;
use App\Filters\PropostaFilter;
use App\Models\Proposta;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
 */
class PropostaService
{
    public function __construct(
        private readonly PropostaFilter $filter,
    ) {}

    /**
     * Pesquisa propostas aplicando filtros opcionais, ordenação e paginação.
     *
     * @param  array<string, mixed>  $filters  Aceita 'status', 'cliente_id', 'sort', 'direction', 'per_page'.
     */
    public function search(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Proposta::with('cliente');

        $query = $this->filter->apply($query, $filters);

        return $query->paginate(min($perPage, 100));
    }

    /**
     * Cria uma nova proposta forçando status DRAFT e versão 1.
     *
     * @throws \Throwable
     */
    public function create(CriarPropostaDTO $dto): Proposta
    {
        return DB::transaction(function () use ($dto): Proposta {
            /** @var Proposta $proposta */
            $proposta = Proposta::create([
                ...$dto->toArray(),
                'status' => PropostaStatusEnum::DRAFT->value,
                'versao' => 1,
            ]);

            Log::info('proposta.created', [
                'proposta_id' => $proposta->id,
                'cliente_id'  => $proposta->cliente_id,
                'origem'      => $proposta->origem->value,
            ]);

            return $proposta;
        });
    }

    /**
     * Atualiza campos da proposta com lock otimista no nível do banco.
     *
     * Fluxo:
     *  1. Rejeita se a proposta está em estado terminal.
     *  2. Emite UPDATE ... WHERE versao = $dto->versao.
     *     Se 0 linhas afetadas → versão desatualizada → ConcurrencyException (409).
     *  3. Retorna o modelo atualizado.
     *
     * @throws BusinessException      Se a proposta estiver em estado terminal.
     * @throws ConcurrencyException   Se a versão enviada divergir da persistida.
     * @throws \Throwable
     */
    public function update(Proposta $proposta, AtualizarPropostaDTO $dto): Proposta
    {
        return DB::transaction(function () use ($proposta, $dto): Proposta {
            if ($proposta->status->isTerminal()) {
                throw BusinessException::because(
                    "A proposta #{$proposta->id} está em estado terminal"
                    . " ({$proposta->status->value}) e não pode ser editada."
                );
            }

            $changedFields = $dto->changedFields();

            if (empty($changedFields)) {
                return $proposta;
            }

            // lockForUpdate() garante atomicidade no check de versão e dispara o Observer
            // (::where()->update() é mais eficiente mas bypassa os eventos do Eloquent)
            /** @var Proposta $locked */
            $locked = Proposta::lockForUpdate()->findOrFail($proposta->id);

            if ($locked->versao !== $dto->versao) {
                Log::warning('proposta.concurrency_conflict', [
                    'proposta_id'   => $proposta->id,
                    'versao_client' => $dto->versao,
                    'versao_db'     => $locked->versao,
                ]);

                throw ConcurrencyException::staleVersion();
            }

            // fill + save → dispara PropostaObserver::updated() → evento PropostaCamposAlterados
            $locked->fill($changedFields);
            $locked->versao += 1;
            $locked->save();

            Log::info('proposta.updated', [
                'proposta_id' => $locked->id,
                'fields'      => array_keys($changedFields),
                'versao_from' => $dto->versao,
            ]);

            return $locked->refresh();
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
     * Aplica a transição de status com lock pessimista para evitar
     * double-transition concorrente e incrementa a versão atomicamente.
     *
     * @throws \Throwable
     */
    private function transition(Proposta $proposta, PropostaStatusEnum $novoStatus): Proposta
    {
        return DB::transaction(function () use ($proposta, $novoStatus): Proposta {
            // lockForUpdate() impede que duas transações paralelas leiam o mesmo
            // registro e apliquem transições simultâneas
            /** @var Proposta $locked */
            $locked = Proposta::lockForUpdate()->findOrFail($proposta->id);

            $statusAnterior = $locked->status;
            $locked->status = $novoStatus;
            $locked->versao += 1;
            $locked->save();

            Log::info('proposta.status_changed', [
                'proposta_id' => $locked->id,
                'de'          => $statusAnterior->value,
                'para'        => $novoStatus->value,
                'versao'      => $locked->versao,
            ]);

            return $locked->refresh();
        });
    }
}
