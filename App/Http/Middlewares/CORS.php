<?php

namespace App\Http\Middlewares;

use App\Foundation\Http\Middleware;
use App\Foundation\Http\Request;
use App\Foundation\Http\Response;
use Closure;

class CORS extends Middleware
{
    /**
     * Handle CORS requests
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // Handle preflight OPTIONS requests
        if ($request->method() === 'OPTIONS') {
            return $this->handlePreflight($request);
        }

        return $next($request);
    }

    /**
     * Handle preflight OPTIONS request
     */
    protected function handlePreflight(Request $request): mixed
    {
        $response = response();

        $this->addCorsHeaders($request, $response);

        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response->make('', 204);
    }

    /**
     * Add CORS headers to response
     */
    protected function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->origin();
        $allowedOrigins = $this->getAllowedOrigins();
        $allowCredentials = filter_var(env('ALLOW_CREDENTIALS', false), FILTER_VALIDATE_BOOL);
        // dd($origin)
        if ($this->shouldAllowAnyOrigin()) {
            if ($allowCredentials) {
                if ($origin && $this->isOriginValid($origin)) {
                    $response->headers->set('Access-Control-Allow-Origin', $origin);
                    $response->headers->set('Vary', 'Origin');
                }
            } else {
                $response->headers->set('Access-Control-Allow-Origin', '*');
            }
        } elseif ($origin && $this->isOriginAllowed($origin, $allowedOrigins)) {
            $response->headers->set('Vary', 'Origin');
            $response->headers->set('Access-Control-Allow-Origin',  $allowCredentials ? $origin : '*');
        }

        if ($allowCredentials) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        $response->headers->set('Access-Control-Allow-Methods', $this->getAllowedMethods());
        $response->headers->set('Access-Control-Allow-Headers', $this->getAllowedHeaders());

        return $response;
    }

    /**
     * Get allowed origins from configuration
     */
    protected function getAllowedOrigins(): array
    {
        $origins = env('ALLOWED_ORIGINS', '');

        if (empty($origins)) {
            return [];
        }

        return array_map('trim', explode(',', $origins));
    }

    /**
     * Check if origin is allowed
     */
    protected function isOriginAllowed(string $origin, array $allowedOrigins): bool
    {
        // Allow exact match
        if (in_array($origin, $allowedOrigins, true)) {
            return true;
        }

        // Allow wildcard subdomains (e.g., *.example.com)
        foreach ($allowedOrigins as $allowedOrigin) {
            if (strpos($allowedOrigin, '*') !== false) {
                $pattern = $this->convertWildcardToRegex($allowedOrigin);
                if (preg_match($pattern, $origin)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Convert wildcard pattern to regex
     */
    protected function convertWildcardToRegex(string $pattern): string
    {
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\*', '.*', $pattern);
        return '/^' . $pattern . '$/i';
    }

    /**
     * Validate origin format
     */
    protected function isOriginValid(string $origin): bool
    {
        // Basic origin validation
        if (filter_var($origin, FILTER_VALIDATE_URL)) {
            $parsed = parse_url($origin);
            return isset($parsed['scheme']) && in_array($parsed['scheme'], ['http', 'https']);
        }

        return false;
    }

    /**
     * Check if we should allow any origin
     */
    protected function shouldAllowAnyOrigin(): bool
    {
        return filter_var(env('ALLOW_CORS_FROM_ANYWHERE', false), FILTER_VALIDATE_BOOL);
    }

    /**
     * Get allowed methods
     */
    protected function getAllowedMethods(): string
    {
        return env('ALLOWED_METHODS', 'GET, POST, PUT, DELETE, OPTIONS, PATCH, HEAD');
    }

    /**
     * Get allowed headers
     */
    protected function getAllowedHeaders(): string
    {
        return env('ALLOWED_HEADERS', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN');
    }

    /**
     * Get exposed headers
     */
    protected function getExposedHeaders(): string
    {
        return env('EXPOSED_HEADERS', 'Content-Length, X-Total-Count');
    }
}
