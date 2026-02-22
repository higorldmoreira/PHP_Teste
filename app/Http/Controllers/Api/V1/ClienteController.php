<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClienteRequest;
use App\Http\Resources\ClienteResource;
use App\Models\Cliente;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

class ClienteController extends Controller
{
    /**
     * Cria um novo cliente.
     * HTTP 201 Created com o resource do cliente criado.
     */
    public function store(StoreClienteRequest $request): JsonResource
    {
        $cliente = Cliente::create($request->validated());

        return (new ClienteResource($cliente))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Exibe um cliente pelo id.
     * Usa Route Model Binding implícito — o Laravel resolve automaticamente.
     */
    public function show(Cliente $cliente): ClienteResource
    {
        return new ClienteResource($cliente);
    }
}
