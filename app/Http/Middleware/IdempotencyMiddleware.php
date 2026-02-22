<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * IdempotencyMiddleware
 *
 * Garante que requisições com o header `Idempotency-Key` sejam processadas
 * apenas uma vez. Respostas são armazenadas no Redis por 24 horas.
 *
 * Fluxo:
 *  1. Se o método HTTP não é mutante (GET/HEAD/OPTIONS), passa direto.
 *  2. Se o header `Idempotency-Key` está ausente, passa direto.
 *  3. Se a chave já existe no cache → retorna a resposta cacheada com
 *     o header `X-Idempotency-Replayed: true`.
 *  4. Se a chave é nova → processa a requisição, armazena a resposta
 *     no cache e a retorna normalmente.
 */
class IdempotencyMiddleware
{
    /** Métodos que devem ser verificados. */
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** TTL do cache: 24 horas em segundos. */
    private const TTL_SECONDS = 86_400;

    /** Prefixo de namespace no Redis para evitar colisões. */
    private const CACHE_PREFIX = 'idempotency:';

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Ignora métodos não-mutantes (GET, HEAD, OPTIONS)
        if (! in_array($request->method(), self::MUTATING_METHODS, strict: true)) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        // Header ausente → deixa a requisição seguir sem restrição
        if ($key === null || trim($key) === '') {
            return $next($request);
        }

        $cacheKey = self::CACHE_PREFIX . $key;

        // Resposta já cacheada → replay imediato
        /** @var array{status: int, headers: array<string, string>, content: string}|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $this->buildReplayResponse($cached);
        }

        /** @var SymfonyResponse $response */
        $response = $next($request);

        // Armazena apenas respostas bem-sucedidas (2xx) para evitar cachear
        // erros transitórios como 500, 503 etc.
        if ($response->isSuccessful()) {
            Cache::put($cacheKey, [
                'status'  => $response->getStatusCode(),
                'headers' => $this->extractHeaders($response),
                'content' => $response->getContent() ?: '',
            ], self::TTL_SECONDS);
        }

        return $response;
    }

    /**
     * Reconstrói a resposta a partir dos dados cacheados e adiciona
     * o header de replay.
     *
     * @param  array{status: int, headers: array<string, string>, content: string}  $cached
     */
    private function buildReplayResponse(array $cached): Response
    {
        $response = new Response(
            $cached['content'],
            $cached['status'],
            $cached['headers'],
        );

        $response->headers->set('X-Idempotency-Replayed', 'true');

        return $response;
    }

    /**
     * Extrai os headers relevantes da resposta para persistência.
     * Exclui headers que não devem ser reproduzidos (ex.: Set-Cookie, Transfer-Encoding).
     *
     * @return array<string, string>
     */
    private function extractHeaders(SymfonyResponse $response): array
    {
        $skip = ['set-cookie', 'transfer-encoding', 'content-encoding'];

        $headers = [];
        foreach ($response->headers->all() as $name => $values) {
            if (in_array(strtolower($name), $skip, strict: true)) {
                continue;
            }
            $headers[$name] = implode(', ', $values);
        }

        return $headers;
    }
}
