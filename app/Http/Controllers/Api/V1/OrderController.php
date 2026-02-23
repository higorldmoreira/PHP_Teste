<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Proposta;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Orders', description: 'Gestão de pedidos gerados a partir de propostas aprovadas')]
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $service) {}

    /**
     * Lista os pedidos do usuário autenticado, com filtro opcional por status.
     */
    #[OA\Get(
        path: '/api/v1/orders',
        summary: 'Lista pedidos do usuário autenticado',
        security: [['bearerAuth' => []]],
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada de pedidos'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->query('per_page', 15);

        $orders = $this->service->paginatedForUser(
            user: $request->user(),
            status: $request->query('status'),
            perPage: $perPage,
        );

        return OrderResource::collection($orders);
    }

    /**
     * Cria um pedido a partir de uma proposta APPROVED.
     */
    #[OA\Post(
        path: '/api/v1/propostas/{proposta}/orders',
        summary: 'Cria um pedido a partir de uma proposta aprovada',
        security: [['bearerAuth' => []]],
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(name: 'proposta', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'observacoes', type: 'string', nullable: true, example: 'Entrega urgente'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pedido criado', content: new OA\JsonContent(ref: '#/components/schemas/OrderResource')),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Proposta não está aprovada ou já possui pedido ativo'),
        ]
    )]
    public function store(StoreOrderRequest $request, Proposta $proposta): JsonResponse
    {
        $order = $this->service->placeOrder(
            proposta: $proposta,
            user: $request->user(),
            data: $request->validated(),
        );

        return OrderResource::make($order->load('proposta'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Exibe um pedido específico do usuário autenticado.
     */
    #[OA\Get(
        path: '/api/v1/orders/{order}',
        summary: 'Exibe um pedido',
        security: [['bearerAuth' => []]],
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Dados do pedido', content: new OA\JsonContent(ref: '#/components/schemas/OrderResource')),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Sem permissão para visualizar este pedido'),
            new OA\Response(response: 404, description: 'Pedido não encontrado'),
        ]
    )]
    public function show(Request $request, Order $order): OrderResource
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403, 'Sem permissão para visualizar este pedido.');
        }

        return OrderResource::make($order->load('proposta'));
    }

    /**
     * Cancela um pedido.
     */
    #[OA\Post(
        path: '/api/v1/orders/{order}/cancel',
        summary: 'Cancela um pedido',
        security: [['bearerAuth' => []]],
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pedido cancelado', content: new OA\JsonContent(ref: '#/components/schemas/OrderResource')),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Sem permissão para cancelar este pedido'),
            new OA\Response(response: 422, description: 'Pedido não pode ser cancelado no status atual'),
        ]
    )]
    public function cancel(Request $request, Order $order): OrderResource
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403, 'Sem permissão para cancelar este pedido.');
        }

        $order = $this->service->cancel($order);

        return OrderResource::make($order->load('proposta'));
    }
}
