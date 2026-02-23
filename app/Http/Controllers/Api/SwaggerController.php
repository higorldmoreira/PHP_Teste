<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;

/**
 * Controlador vazio que serve de âncora para as anotações globais do Swagger.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'PHP Teste — API de Propostas',
    description: 'API REST para gestão de clientes, propostas financeiras e pedidos. ' .
        'Implementa máquina de estados, optimistic locking, idempotência e auditoria automática.',
    contact: new OA\Contact(
        name: 'Higor Moreira',
        email: 'higor@example.com'
    ),
)]
#[OA\Server(
    url: '/',
    description: 'API v1'
)]

// ── Schemas reutilizáveis ─────────────────────────────────────────────────────

#[OA\Schema(
    schema: 'ClienteResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'nome', type: 'string', example: 'João Silva'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'joao@example.com'),
        new OA\Property(property: 'documento', type: 'string', example: '123.456.789-09'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'PropostaResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'cliente_id', type: 'integer', example: 1),
        new OA\Property(property: 'produto', type: 'string', example: 'Crédito Pessoal'),
        new OA\Property(property: 'valor_mensal', type: 'number', format: 'float', example: 1200.00),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'submitted', 'approved', 'rejected', 'canceled'], example: 'draft'),
        new OA\Property(property: 'origem', type: 'string', enum: ['app', 'site', 'api'], example: 'api'),
        new OA\Property(property: 'versao', type: 'integer', example: 1),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'OrderResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'proposta_id', type: 'integer', example: 1),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'rejected', 'shipped', 'delivered', 'cancelled'], example: 'pending'),
        new OA\Property(property: 'status_label', type: 'string', example: 'Aguardando pagamento'),
        new OA\Property(property: 'valor_total', type: 'number', format: 'float', example: 1200.00),
        new OA\Property(property: 'observacoes', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'AuditoriaResource',
    properties: [
        new OA\Property(property: 'actor', type: 'string', example: 'user:1'),
        new OA\Property(property: 'evento', type: 'string', enum: ['created', 'updated_fields', 'status_changed', 'deleted_logical']),
        new OA\Property(property: 'payload', type: 'object'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'ErrorBusiness',
    properties: [
        new OA\Property(property: 'error', type: 'string', example: 'Unprocessable Entity'),
        new OA\Property(property: 'message', type: 'string', example: 'Transição de status inválida.'),
    ]
)]
#[OA\Schema(
    schema: 'ErrorConcurrency',
    properties: [
        new OA\Property(property: 'error', type: 'string', example: 'Conflict'),
        new OA\Property(property: 'message', type: 'string', example: 'A versão enviada está desatualizada.'),
    ]
)]
class SwaggerController
{
    // Este controller existe apenas para hospedar as anotações globais do OpenAPI.
}
