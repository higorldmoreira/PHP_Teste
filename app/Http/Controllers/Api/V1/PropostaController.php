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
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

class PropostaController extends Controller
{
    public function __construct(
        private readonly PropostaService $propostaService,
    ) {}

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /**
     * Lista propostas com filtros opcionais e paginação.
     *
     * Query params suportados:
     *   - status    : filtra pelo valor do enum (ex: ?status=draft)
     *   - cliente_id: filtra pelo cliente (ex: ?cliente_id=5)
     *   - sort      : campo de ordenação (ex: ?sort=created_at)
     *   - direction : asc|desc (ex: ?direction=desc) — padrão: desc
     *   - per_page  : itens por página (ex: ?per_page=25) — padrão: 15
     *
     * Eager loading com 'cliente' para evitar N+1.
     */
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

    /**
     * Cria uma nova proposta via PropostaService (força DRAFT + versao 1).
     * HTTP 201 Created.
     */
    public function store(StorePropostaRequest $request): JsonResource
    {
        $data = collect($request->validated())
            ->except('idempotency_key')
            ->all();

        $proposta = $this->propostaService->create($data);

        return (new PropostaResource($proposta->load('cliente')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Exibe uma proposta específica com cliente carregado.
     */
    public function show(Proposta $proposta): PropostaResource
    {
        return new PropostaResource($proposta->load('cliente'));
    }

    /**
     * Atualiza campos livres da proposta com Optimistic Lock.
     * 'versao' é consumido pelo service; 'status' não pode ser alterado aqui.
     */
    public function update(UpdatePropostaRequest $request, Proposta $proposta): PropostaResource
    {
        $proposta = $this->propostaService->update($proposta, $request->validated());

        return new PropostaResource($proposta->load('cliente'));
    }

    // -------------------------------------------------------------------------
    // Transições de Estado
    // -------------------------------------------------------------------------

    /**
     * DRAFT → SUBMITTED
     */
    public function submit(Proposta $proposta): PropostaResource
    {
        return new PropostaResource(
            $this->propostaService->submit($proposta)->load('cliente')
        );
    }

    /**
     * SUBMITTED → APPROVED
     */
    public function approve(Proposta $proposta): PropostaResource
    {
        return new PropostaResource(
            $this->propostaService->approve($proposta)->load('cliente')
        );
    }

    /**
     * SUBMITTED → REJECTED
     */
    public function reject(Proposta $proposta): PropostaResource
    {
        return new PropostaResource(
            $this->propostaService->reject($proposta)->load('cliente')
        );
    }

    /**
     * DRAFT | SUBMITTED → CANCELED
     */
    public function cancel(Proposta $proposta): PropostaResource
    {
        return new PropostaResource(
            $this->propostaService->cancel($proposta)->load('cliente')
        );
    }

    // -------------------------------------------------------------------------
    // Auditoria
    // -------------------------------------------------------------------------

    /**
     * Retorna o histórico de auditoria da proposta, da mais recente para a mais antiga.
     */
    public function auditoria(Proposta $proposta): AnonymousResourceCollection
    {
        $auditorias = $proposta->auditorias()
            ->latest('created_at')
            ->get();

        return AuditoriaPropostaResource::collection($auditorias);
    }
}
