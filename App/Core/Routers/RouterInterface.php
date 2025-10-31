<?php

use App\Foundation\Http\Middleware;
use App\Foundation\Http\Request;
use App\Foundation\Manager\InstanceManager;
use App\Foundation\Http\MiddlewareException;

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
     * @param mixed $passable
     * @param array<int, string|null> $pipes
     * @param Closure $destination
     */
    public static function pipeline($passable, array $pipes, Closure $destination): mixed
    {
        /** @var App\Foundation\Http\Middleware $middleware */
        $middleware = InstanceManager::getInstance('_appMiddleware');

        $pipes = array_map(
            fn($a) => $middleware->resolveAlias($a),
            array_filter(
                $pipes,
                fn($p) => !empty($p)
            )
        );

        try {
            $pipeline = array_reduce(
                array_reverse($pipes),
                fn($next, $pipe) => fn($passable) => $pipe['instance']->handle($passable, $next, ...$pipe['parameters']),
                $destination
            );
        } catch (Throwable $e) {
            throw new MiddlewareException($e->getMessage(), 1, $e);
        }

        return $pipeline($passable);
    }

    public function getName()
    {
        return $this->name ?? null;
    }
}
