<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePropostaRequest;
use App\Http\Requests\UpdatePropostaRequest;
use App\Http\Resources\AuditoriaPropostaResource;
use App\Http\Resources\PropostaResource;
use App\Models\Proposta;
use App\Services\PropostaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Propostas', description: 'Gestão de propostas com máquina de estados, optimistic lock e auditoria')]
class PropostaController extends Controller
{
    public function __construct(
        private readonly PropostaService $propostaService,
    ) {}

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/propostas',
        summary: 'Lista propostas com filtros e paginação',
        security: [['bearerAuth' => []]],
        tags: ['Propostas'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draft', 'submitted', 'approved', 'rejected', 'canceled'])),
            new OA\Parameter(name: 'cliente_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'created_at')),
            new OA\Parameter(name: 'direction', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'desc')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada de propostas'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Proposta::with('cliente');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($clienteId = $request->query('cliente_id')) {
            $query->where('cliente_id', (int) $clienteId);
        }

        $sortField     = $request->query('sort', 'created_at');
        $sortDirection = $request->query('direction', 'desc');

        // Allowlist de campos ordenáveis para evitar injeção via query string
        $allowedSorts = ['created_at', 'updated_at', 'valor_mensal', 'status', 'versao'];
        if (! in_array($sortField, $allowedSorts, strict: true)) {
            $sortField = 'created_at';
        }

        $sortDirection = $sortDirection === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortField, $sortDirection);

        $perPage = min((int) $request->query('per_page', 15), 100);

        return PropostaResource::collection($query->paginate($perPage));
    }

    #[OA\Post(
        path: '/api/v1/propostas',
        summary: 'Cria uma nova proposta (status inicial: DRAFT)',
        security: [['bearerAuth' => []]],
        tags: ['Propostas'],
        parameters: [new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['cliente_id', 'produto', 'valor_mensal', 'origem'],
                properties: [
                    new OA\Property(property: 'cliente_id', type: 'integer', example: 1),
                    new OA\Property(property: 'produto', type: 'string', example: 'Crédito Pessoal'),
                    new OA\Property(property: 'valor_mensal', type: 'number', format: 'float', example: 1200.00),
                    new OA\Property(property: 'origem', type: 'string', enum: ['app', 'site', 'api'], example: 'api'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Proposta criada', content: new OA\JsonContent(ref: '#/components/schemas/PropostaResource')),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Dados inválidos'),
        ]
    )]
    public function store(StorePropostaRequest $request): JsonResponse
    {
        $data = collect($request->validated())
            ->except('idempotency_key')
            ->all();

        $proposta = $this->propostaService->create($data);

        return (new PropostaResource($proposta->load('cliente')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[OA\Get(path: '/api/v1/propostas/{proposta}', summary: 'Exibe uma proposta', security: [['bearerAuth' => []]], tags: ['Propostas'], parameters: [new OA\Parameter(name: 'proposta', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Dados da proposta', content: new OA\JsonContent(ref: '#/components/schemas/PropostaResource')), new OA\Response(response: 401, description: 'Não autenticado'), new OA\Response(response: 404, description: 'Não encontrada')])]
    public function show(Proposta $proposta): PropostaResource
    {
        return new PropostaResource($proposta->load('cliente'));
    }

    #[OA\Patch(
        path: '/api/v1/propostas/{proposta}',
        summary: 'Atualiza campos livres com Optimistic Lock',
        security: [['bearerAuth' => []]],
        tags: ['Propostas'],
        parameters: [new OA\Parameter(name: 'proposta', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['versao'], properties: [new OA\Property(property: 'versao', type: 'integer', example: 1), new OA\Property(property: 'produto', type: 'string', example: 'Empréstimo'), new OA\Property(property: 'valor_mensal', type: 'number', example: 999.99)])),
        responses: [
            new OA\Response(response: 200, description: 'Proposta atualizada', content: new OA\JsonContent(ref: '#/components/schemas/PropostaResource')),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 409, description: 'Conflito de versão', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConcurrency')),
            new OA\Response(response: 422, description: 'Estado terminal', content: new OA\JsonContent(ref: '#/components/schemas/ErrorBusiness')),
        ]
    )]
    public function update(UpdatePropostaRequest $request, Proposta $proposta): PropostaResource
    {
        $proposta = $this->propostaService->update($proposta, $request->validated());

        return new PropostaResource($proposta->load('cliente'));
    }

    // -------------------------------------------------------------------------
    // Transições de Estado
    // -------------------------------------------------------------------------

    #[OA\Post(path: '/api/v1/propostas/{proposta}/submit', summary: 'DRAFT → SUBMITTED', security: [['bearerAuth' => []]], tags: ['Propostas'], parameters: [new OA\Parameter(name: 'proposta', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Proposta submetida', content: new OA\JsonContent(ref: '#/components/schemas/PropostaResource')), new OA\Response(response: 401, description: 'Não autenticado'), new OA\Response(response: 422, description: 'Transição inválida', content: new OA\JsonContent(ref: '#/components/schemas/ErrorBusiness'))])]
    public function submit(Proposta $proposta): PropostaResource
    {
        return new PropostaResource(
            $this->propostaService->submit($proposta)->load('cliente')
        );
    }

    #[OA\Post(path: '/api/v1/propostas/{proposta}/approve', summary: 'SUBMITTED → APPROVED', security: [['bearerAuth' => []]], tags: ['Propostas'], parameters: [new OA\Parameter(name: 'proposta', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Proposta aprovada', content: new OA\JsonContent(ref: '#/components/schemas/PropostaResource')), new OA\Response(response: 401, description: 'Não autenticado'), new OA\Response(response: 422, description: 'Transição inválida', content: new OA\JsonContent(ref: '#/components/schemas/ErrorBusiness'))])]
    public function approve(Proposta $proposta): PropostaResource
    {
        return new PropostaResource(
            $this->propostaService->approve($proposta)->load('cliente')
        );
    }

    #[OA\Post(path: '/api/v1/propostas/{proposta}/reject', summary: 'SUBMITTED → REJECTED', security: [['bearerAuth' => []]], tags: ['Propostas'], parameters: [new OA\Parameter(name: 'proposta', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Proposta rejeitada', content: new OA\JsonContent(ref: '#/components/schemas/PropostaResource')), new OA\Response(response: 401, description: 'Não autenticado'), new OA\Response(response: 422, description: 'Transição inválida', content: new OA\JsonContent(ref: '#/components/schemas/ErrorBusiness'))])]
    public function reject(Proposta $proposta): PropostaResource
    {
        return new PropostaResource(
            $this->propostaService->reject($proposta)->load('cliente')
        );
    }

    #[OA\Post(path: '/api/v1/propostas/{proposta}/cancel', summary: 'DRAFT|SUBMITTED → CANCELED', security: [['bearerAuth' => []]], tags: ['Propostas'], parameters: [new OA\Parameter(name: 'proposta', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Proposta cancelada', content: new OA\JsonContent(ref: '#/components/schemas/PropostaResource')), new OA\Response(response: 401, description: 'Não autenticado'), new OA\Response(response: 422, description: 'Transição inválida', content: new OA\JsonContent(ref: '#/components/schemas/ErrorBusiness'))])]
    public function cancel(Proposta $proposta): PropostaResource
    {
        return new PropostaResource(
            $this->propostaService->cancel($proposta)->load('cliente')
        );
    }

    // -------------------------------------------------------------------------
    // Auditoria
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/propostas/{proposta}/auditoria',
        summary: 'Histórico de auditoria da proposta',
        security: [['bearerAuth' => []]],
        tags: ['Propostas'],
        parameters: [new OA\Parameter(name: 'proposta', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Histórico de auditoria', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/AuditoriaResource'))),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function auditoria(Proposta $proposta): AnonymousResourceCollection
    {
        $auditorias = $proposta->auditorias()
            ->reorder()                        // descarta o orderBy ASC da relação
            ->orderByDesc('created_at')
            ->orderByDesc('id')               // desempate determinístico por PK
            ->get();

        return AuditoriaPropostaResource::collection($auditorias);
    }
}
