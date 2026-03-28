<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    private const HEADER_NAME = 'X-Request-Id';

    private const ATTRIBUTE_NAME = 'request_id';

    /**
     * Handle an incoming request.
     *
     * Generates or uses an existing request ID for tracing. The request ID
     * is made available in response headers and request attributes for
     * debugging and logging purposes throughout the request lifecycle.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);

        // Store in request attributes for access by controllers/services
        $request->attributes->set(self::ATTRIBUTE_NAME, $requestId);

        $response = $next($request);

        // Add request ID to response headers for client-side tracing
        $response->headers->set(self::HEADER_NAME, $requestId);

        // Log the request with its ID for debugging
        $this->logRequest($request, $requestId);

        return $response;
    }

    /**
     * Resolve the request ID from headers or generate a new one.
     *
     * Checks for X-Request-Id header first (to support distributed tracing
     * from upstream services). If not present, generates a new UUID v4.
     */
    protected function resolveRequestId(Request $request): string
    {
        $existingId = $request->header(self::HEADER_NAME);

        if ($existingId && $this->isValidRequestId($existingId)) {
            return $existingId;
        }

        return (string) Str::uuid();
    }

    /**
     * Validate that the provided request ID is valid.
     *
     * Request IDs should be UUIDs, but we also accept alphanumeric
     * strings of reasonable length to support various ID formats.
     */
    protected function isValidRequestId(?string $requestId): bool
    {
        if (empty($requestId)) {
            return false;
        }

        // Allow UUIDs (36 chars) or shorter alphanumeric IDs
        $length = strlen($requestId);

        if ($length < 8 || $length > 128) {
            return false;
        }

        // Only allow alphanumeric, hyphens, and underscores
        return (bool) preg_match('/^[a-zA-Z0-9_-]+$/', $requestId);
    }

    /**
     * Log the request details with the request ID.
     *
     * This helps with debugging by correlating logs to specific requests.
     * Logs at debug level to avoid noise in production.
     */
    protected function logRequest(Request $request, string $requestId): void
    {
        Log::debug('Request processed', [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Get the request ID for the given request.
     *
     * Helper method for retrieving the request ID from outside the middleware.
     */
    public static function getRequestId(Request $request): ?string
    {
        return $request->attributes->get(self::ATTRIBUTE_NAME);
    }
}
