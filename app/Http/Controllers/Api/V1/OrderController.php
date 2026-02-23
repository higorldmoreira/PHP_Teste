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
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Pedidos', description: 'Gestão de pedidos gerados a partir de propostas aprovadas')]
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $service) {}

    #[OA\Get(
        path: '/api/v1/orders',
        summary: 'Lista pedidos paginados',
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(name: 'status',   in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada de pedidos'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $this->service->paginate(
            status: $request->query('status'),
            perPage: (int) $request->query('per_page', 15),
        );

        return OrderResource::collection($orders);
    }

    #[OA\Post(
        path: '/api/v1/propostas/{proposta}/orders',
        summary: 'Cria um pedido a partir de uma proposta aprovada',
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
            new OA\Response(response: 201, description: 'Pedido criado',          content: new OA\JsonContent(ref: '#/components/schemas/OrderResource')),
            new OA\Response(response: 422, description: 'Proposta nao aprovada ou ja possui pedido ativo'),
        ]
    )]
    public function store(StoreOrderRequest $request, Proposta $proposta): JsonResponse
    {
        $order = $this->service->placeOrder(
            proposta: $proposta,
            data: $request->validated(),
        );

        return OrderResource::make($order->load('proposta'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/api/v1/orders/{order}',
        summary: 'Exibe um pedido',
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Dados do pedido', content: new OA\JsonContent(ref: '#/components/schemas/OrderResource')),
            new OA\Response(response: 404, description: 'Pedido nao encontrado'),
        ]
    )]
    public function show(Order $order): OrderResource
    {
        return OrderResource::make($order->load('proposta'));
    }

    #[OA\Post(
        path: '/api/v1/orders/{order}/cancel',
        summary: 'Cancela um pedido',
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pedido cancelado',       content: new OA\JsonContent(ref: '#/components/schemas/OrderResource')),
            new OA\Response(response: 422, description: 'Pedido não pode ser cancelado no status atual'),
        ]
    )]
    public function cancel(Order $order): OrderResource
    {
        return OrderResource::make($this->service->cancel($order)->load('proposta'));
    }
}