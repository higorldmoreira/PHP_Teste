<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * BaseService
 *
 * Classe abstrata que serve como contrato e ponto central de herança
 * para todos os Services de domínio da aplicação.
 *
 * Responsabilidades desta camada:
 *  - Encapsular regras de negócio fora dos Controllers e Models.
 *  - Orquestrar chamadas a Repositories, Jobs, Events e outros serviços.
 *  - Nunca interagir diretamente com Request/Response (isso é papel do Controller).
 *
 * @template TModel of Model
 */
abstract class BaseService
{
    /**
     * Repositório ou Model principal gerenciado por este serviço.
     * Subclasses devem tipar corretamente via @var na propriedade sobrescrita.
     *
     * @var \Illuminate\Database\Eloquent\Builder|Model
     */
    protected Model $model;

    /**
     * Retorna todos os registros, com suporte opcional a colunas específicas.
     *
     * @param  list<string>  $columns
     * @return Collection<int, TModel>
     */
    public function all(array $columns = ['*']): Collection
    {
        return $this->model->newQuery()->get($columns);
    }

    /**
     * Retorna registros paginados.
     *
     * @param  list<string>  $columns
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model->newQuery()->paginate($perPage, $columns);
    }

    /**
     * Localiza um registro pelo id.
     * Lança ModelNotFoundException se não encontrado.
     *
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int|string $id): Model
    {
        return $this->model->newQuery()->findOrFail($id);
    }

    /**
     * Cria um novo registro.
     *
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function create(array $data): Model
    {
        return $this->model->newQuery()->create($data);
    }

    /**
     * Atualiza um registro existente pelo id.
     *
     * @param  array<string, mixed>  $data
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function update(int|string $id, array $data): Model
    {
        $record = $this->findOrFail($id);
        $record->update($data);

        return $record->refresh();
    }

    /**
     * Remove um registro pelo id (soft delete se o Model usar SoftDeletes).
     *
     * @throws ModelNotFoundException
     */
    public function delete(int|string $id): bool
    {
        $record = $this->findOrFail($id);

        return (bool) $record->delete();
    }

    /**
     * Executa um callable dentro de uma transação de banco de dados.
     * Faz commit em caso de sucesso e rollback automático em exceções.
     *
     * Exemplo de uso em uma subclasse:
     *
     *   return $this->transaction(function () use ($data) {
     *       $order = $this->create($data);
     *       event(new OrderCreated($order));
     *       return $order;
     *   });
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     *
     * @throws \Throwable
     */
    protected function transaction(callable $callback): mixed
    {
        return \DB::transaction($callback);
    }

    /**
     * Retorna o nome qualificado da classe do Model gerenciado.
     */
    protected function modelClass(): string
    {
        return $this->model::class;
    }
}
