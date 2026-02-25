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
 * Implementação com Cache::lock() (atomic distributed lock):
 *  1. Se o método HTTP não é mutante (GET/HEAD/OPTIONS), passa direto.
 *  2. Se o header `Idempotency-Key` está ausente, passa direto.
 *  3. Se a chave já existe no cache → retorna a resposta cacheada com
 *     o header `X-Idempotency-Replayed: true`.
 *  4. Adquire lock distribuído para garantir que somente um processo execute
 *     a requisição quando múltiplos retries chegam simultaneamente.
 *  5. Após processar, armazena a resposta no cache e libera o lock.
 *
 * Isso evita o race condition onde dois requests simultâneos com a mesma chave
 * criavam dois recursos antes de qualquer um gravar no cache.
 */
class IdempotencyMiddleware
{
    /** Métodos que devem ser verificados. */
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** TTL do cache: 24 horas em segundos. */
    private const TTL_SECONDS = 86_400;

    /** TTL do lock distribuído (máximo aguardo por uma resposta em andamento). */
    private const LOCK_SECONDS = 30;

    /** Prefixo de namespace no Redis para evitar colisões. */
    private const CACHE_PREFIX = 'idempotency:';
    private const LOCK_PREFIX  = 'idempotency_lock:';

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
        $lockKey  = self::LOCK_PREFIX  . $key;

        // Resposta já cacheada → replay imediato (antes de tentar lock)
        /** @var array{status: int, headers: array<string, string>, content: string}|null $cached */
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $this->buildReplayResponse($cached);
        }

        // Adquire lock distribuído — garante um único processamento mesmo com
        // retries simultâneos chegando antes de qualquer cache ser gravado
        $lock = Cache::lock($lockKey, self::LOCK_SECONDS);

        try {
            // block(waitSeconds) aguarda até o lock ser liberado pelo primeiro request
            $lock->block(self::LOCK_SECONDS);

            // Verifica novamente após adquirir o lock — outro processo pode ter
            // concluído e gravado no cache enquanto esperávamos (double-check)
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
        } finally {
            $lock->release();
        }
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
