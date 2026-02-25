<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * ApiVersionMiddleware
 *
 * Injeta o header `X-Api-Version: v1` em todas as respostas das rotas v1,
 * facilitando debugging de contratos e versionamento por clientes.
 */
class ApiVersionMiddleware
{
    public function handle(Request $request, Closure $next, string $version = 'v1'): SymfonyResponse
    {
        /** @var SymfonyResponse $response */
        $response = $next($request);

        $response->headers->set('X-Api-Version', $version);

        return $response;
    }
}
