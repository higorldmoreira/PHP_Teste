<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

/**
 * Gera Idempotency Keys (UUID v4) para uso em requisições mutantes.
 *
 * Endpoints que exigem Idempotency-Key (POST /propostas, POST /clientes)
 * precisam de um UUID único por operação. Este endpoint elimina a
 * necessidade de fontes externas para gerar esses UUIDs.
 *
 * GET  /api/idempotency-key       → 1 UUID
 * GET  /api/idempotency-key?qty=5 → até 10 UUIDs
 */
#[OA\Get(
    path: '/api/idempotency-key',
    summary: 'Gera um ou mais Idempotency Keys (UUID v4)',
    description: 'Retorna UUIDs prontos para uso no header `Idempotency-Key` das requisições POST. '
        . 'Cada chave deve ser usada em apenas UMA requisição. '
        . 'Máximo de 10 chaves por chamada.',
    tags: ['Utilitários'],
    parameters: [
        new OA\Parameter(
            name: 'qty',
            in: 'query',
            required: false,
            description: 'Quantidade de UUIDs a gerar (1–10). Default: 1.',
            schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 10, default: 1)
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'UUIDs gerados com sucesso',
            content: new OA\JsonContent(ref: '#/components/schemas/IdempotencyKeyResource')
        ),
        new OA\Response(
            response: 422,
            description: 'Parâmetro qty inválido',
            content: new OA\JsonContent(ref: '#/components/schemas/ErrorBusiness')
        ),
    ]
)]
class IdempotencyKeyController extends Controller
{
    private const MAX_QTY = 10;

    public function __invoke(Request $request): JsonResponse
    {
        $qty = (int) $request->query('qty', 1);

        if ($qty < 1 || $qty > self::MAX_QTY) {
            return response()->json([
                'error'   => 'Unprocessable Entity',
                'message' => sprintf(
                    'O parâmetro qty deve ser entre 1 e %d.',
                    self::MAX_QTY
                ),
            ], 422);
        }

        $keys = array_map(
            static fn (): string => Str::uuid()->toString(),
            range(1, $qty)
        );

        return response()->json([
            'keys'  => $keys,
            'qty'   => count($keys),
            'usage' => sprintf('Idempotency-Key: %s', $keys[0]),
        ]);
    }
}
