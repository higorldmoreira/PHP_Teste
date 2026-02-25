<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * Endpoint de saúde — usado por load balancers, Kubernetes probes
 * e ferramentas de monitoramento (Uptime Robot, Datadog, etc.).
 *
 * GET /api/health → 200 OK   (todos os serviços respondendo)
 * GET /api/health → 503 Service Unavailable  (algum serviço degradado)
 */
#[OA\Get(
    path: '/api/health',
    summary: 'Verifica a saúde da aplicação',
    tags: ['Health'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Todos os serviços operacionais',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'ok'),
                    new OA\Property(property: 'db',     type: 'string', example: 'up'),
                    new OA\Property(property: 'cache',  type: 'string', example: 'up'),
                ]
            )
        ),
        new OA\Response(
            response: 503,
            description: 'Um ou mais serviços degradados',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'degraded'),
                    new OA\Property(property: 'db',     type: 'string', example: 'down'),
                    new OA\Property(property: 'cache',  type: 'string', example: 'up'),
                ]
            )
        ),
    ]
)]
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $db    = $this->checkDb();
        $cache = $this->checkCache();

        $allUp  = $db === 'up' && $cache === 'up';
        $status = $allUp ? 'ok' : 'degraded';
        $code   = $allUp ? 200 : 503;

        return response()->json(
            ['status' => $status, 'db' => $db, 'cache' => $cache],
            $code,
        );
    }

    private function checkDb(): string
    {
        try {
            DB::connection()->getPdo();
            return 'up';
        } catch (Throwable) {
            return 'down';
        }
    }

    private function checkCache(): string
    {
        try {
            // set + get confirma escrita e leitura sem acumular TTLs longos a cada probe
            Cache::store()->set('__health_ping', 1);
            return Cache::store()->get('__health_ping') === 1 ? 'up' : 'down';
        } catch (Throwable) {
            return 'down';
        }
    }
}
