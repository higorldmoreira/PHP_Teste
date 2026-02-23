<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClienteRequest;
use App\Http\Resources\ClienteResource;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Clientes', description: 'Gestão de clientes')]
class ClienteController extends Controller
{
    #[OA\Post(
        path: '/api/v1/clientes',
        summary: 'Cria um novo cliente',
        security: [['bearerAuth' => []]],
        tags: ['Clientes'],
        parameters: [new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nome', 'email', 'documento'],
                properties: [
                    new OA\Property(property: 'nome', type: 'string', example: 'João Silva'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'joao@example.com'),
                    new OA\Property(property: 'documento', type: 'string', example: '123.456.789-09'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Cliente criado', content: new OA\JsonContent(ref: '#/components/schemas/ClienteResource')),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Dados inválidos'),
        ]
    )]
    public function store(StoreClienteRequest $request): JsonResponse
    {
        $cliente = Cliente::create($request->validated());

        return (new ClienteResource($cliente))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/api/v1/clientes/{cliente}',
        summary: 'Exibe um cliente',
        security: [['bearerAuth' => []]],
        tags: ['Clientes'],
        parameters: [new OA\Parameter(name: 'cliente', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Dados do cliente', content: new OA\JsonContent(ref: '#/components/schemas/ClienteResource')),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 404, description: 'Cliente não encontrado'),
        ]
    )]
    public function show(Cliente $cliente): ClienteResource
    {
        return new ClienteResource($cliente);
    }
}
