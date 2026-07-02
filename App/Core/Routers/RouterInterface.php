<?php

namespace App\Foundation\Http;

use App\Debug\Debugger;
use App\Foundation\Exceptions\Framework\BaseException;
use App\Foundation\Exceptions\Framework\Http\BaseHttpException;
use App\Foundation\Exceptions\Framework\Http\Json\JsonBaseHttpException;
use App\Foundation\Exceptions\Framework\LowLevelException;
use App\Foundation\Exceptions\Framework\Primitive\LogicException;
use App\Foundation\Http\Request;
use App\Foundation\Manager\InstanceManager;
use Closure;
use Throwable;

interface RouterInterface
{
    public function init(?string $root = null, array $plugins = []);
    /** Add new route */
    public function add(string $method, string $url, callable|array $action, array $middleware = []);
    /** Dispatch router */
    public function dispatch(Request $request);
    /** Add middleware */
    public function middleware(string|array $middleware);
    /** Define fallback if no match */
    public function fallback(callable|array $callback);
}

abstract class RouterBase
{
    /**
     * Parameter that was given in route
     *
     * @var string[]
     */
    protected $parameters = [];

    /**
     * @param mixed $passable
     * @param array<int, string|null> $pipes
     * @param Closure $destination
     */
    final public static function pipeline($passable, array $pipes, Closure $destination)
    {
        /** @var \App\Foundation\Http\Middleware $middleware */
        $middleware = InstanceManager::getInstance('_appMiddleware');

        $readyPipes = [];
        foreach ($pipes as $pipe) {
            if (empty($pipe)) continue;
            $readyPipes[] = $middleware->resolveAlias($pipe);
        }

        $pipeline = array_reduce(
            array_reverse($readyPipes),
            fn($next, $pipe) => fn($passable) => $pipe['instance']->handle($passable, $next, ...$pipe['parameters']),
            $destination
        );

        try {
            $res = $pipeline($passable);
        } catch (Throwable $e) {
            return self::resolveException($e);
        }

        $response = response();

        if(!$res instanceof Response){
            if(is_array($res)){
                $response->json($res);
            } else {
                if(!$response->headers->has('Content-Type')){
                    $response->headers->set('Content-Type', 'text/html');
                }
                $response->make((string)$res);
            }
        }

        return $response->send();
    }

    public static function handleFailure(int $code, string $message, ?Throwable $th = null)
    {
        /**
         * @var Request
         */
        $request = InstanceManager::getInstance(Request::class);

        $response = new Response;
        if ($request->wantsJson()) {
            $json = [
                'message' => $message,
                'code' => $code
            ];

            if ($th) {
                $json['trace'] = $th->getTrace();
            }

            return $response->json($json)->send();
        } else {
            return Debugger::showErrorPage($code, $message);
        }
    }

    public function getName()
    {
        return $this->name ?? null;
    }

    /**
     * Get the url parameter
     *
     * @param string $parameter
     * @return void
     */
    public function parameter(string $parameter)
    {
        return $this->parameters[$parameter] ?? null;
    }

    /**
     * Set parameters value of this route
     *
     * @param array $parameters
     * @return string[]
     */
    public function setParameters(array $parameters)
    {
        return $this->parameters = $parameters;
    }

    /**
     * Set parameter value of this route
     *
     * @param string $parameter
     * @param mixed $value
     * @return string[]
     */
    public function setParameter(string $parameter, mixed $value)
    {
        return $this->parameters[$parameter] = $value;
    }

    private static function resolveException(Throwable $e)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        $exception = InstanceManager::getInstance(BaseException::class);

        return match (true) {
            $exception->has($e::class) => $exception->throw($e::class, $e)->send(),

            $e instanceof JsonBaseHttpException => $e->handle()->send(),

            $e instanceof BaseHttpException =>
            Debugger::showErrorPage(
                $e->httpCode(),
                $e->getMessage(),
                $e->getSubMessage()
            ),

            $e instanceof LowLevelException =>
            Debugger::showErrorPage(
                500,
                $e->getMessage(),
                '<a style="color: white;" href="vscode://file/' . $e->getFile() . ':' . $e->getLine() . '" >File: ' . $e->getFile() . '(' . $e->getLine() . ')</a>',
                '',
                $e->getTraceAsString()
            ),

            default => throw $e,
        };
    }

    /**
     * CORS HANDLER
     */

    /**
     * Handle preflight OPTIONS request
     */
    public static function handleCORS(Request $request)
    {
        $response = response();

        self::addCorsHeaders($request, $response);

        $response->headers->set('Access-Control-Max-Age', '86400');
        
        if($request->method() == 'OPTIONS'){
            $response->make('', 204)->send();
            exit;
        }
    }

    /**
     * Add CORS headers to response
     */
    protected static function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->origin();
        $allowedOrigins = self::getAllowedOrigins();
        $allowCredentials = filter_var(env('ALLOW_CREDENTIALS', false), FILTER_VALIDATE_BOOL);
        
        if ($origin && self::isOriginAllowed($origin, $allowedOrigins)) {
            $response->headers->append('Vary', 'Origin');
            $response->headers->set('Access-Control-Allow-Origin',  $origin);
            if ($allowCredentials) {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
        }

        $response->headers->set('Access-Control-Allow-Methods', self::getAllowedMethods());
        $response->headers->set('Access-Control-Allow-Headers', self::getAllowedHeaders());

        return $response;
    }

    /**
     * Get allowed origins from configuration
     */
    protected static function getAllowedOrigins(): array
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
    protected static function isOriginAllowed(string $origin, array $allowedOrigins): bool
    {
        // Allow exact match
        if (in_array($origin, $allowedOrigins, true)) {
            return true;
        }

        // Allow wildcard subdomains (e.g., *.example.com)
        foreach ($allowedOrigins as $allowedOrigin) {
            if (strpos($allowedOrigin, '*') !== false) {
                $pattern = self::convertWildcardToRegex($allowedOrigin);
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
    protected static function convertWildcardToRegex(string $pattern): string
    {
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\*', '.*', $pattern);
        return '/^' . $pattern . '$/i';
    }

    /**
     * Validate origin format
     */
    protected static function isOriginValid(string $origin): bool
    {
        // Basic origin validation
        if (filter_var($origin, FILTER_VALIDATE_URL)) {
            $parsed = parse_url($origin);
            return isset($parsed['scheme']) && in_array($parsed['scheme'], ['http', 'https']);
        }

        return false;
    }

    /**
     * Get allowed methods
     */
    protected static function getAllowedMethods(): string
    {
        return env('ALLOWED_METHODS', 'GET, POST, PUT, DELETE, OPTIONS, PATCH, HEAD');
    }

    /**
     * Get allowed headers
     */
    protected static function getAllowedHeaders(): string
    {
        return env('ALLOWED_HEADERS', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN');
    }

    /**
     * Get exposed headers
     */
    protected static function getExposedHeaders(): string
    {
        return env('EXPOSED_HEADERS', 'Content-Length, X-Total-Count');
    }
}
