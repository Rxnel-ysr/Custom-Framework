<?php

namespace App\Foundation\Http;

use App\Debug\Debugger;
use App\Foundation\Exceptions\Framework\LowLevelException;
use App\Foundation\Exceptions\Http\BaseHttpException;
use App\Foundation\Exceptions\Http\Json\JsonBaseHttpException;
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
     * @param mixed $passable
     * @param array<int, string|null> $pipes
     * @param Closure $destination
     */
    public static function pipeline($passable, array $pipes, Closure $destination): mixed
    {
        /** @var \App\Foundation\Http\Middleware $middleware */
        $middleware = InstanceManager::getInstance('_appMiddleware');

        $pipes = array_map(
            fn($a) => $middleware->resolveAlias($a),
            array_filter(
                $pipes,
                fn($p) => !empty($p)
            )
        );

        $pipeline = array_reduce(
            array_reverse($pipes),
            fn($next, $pipe) => fn($passable) => $pipe['instance']->handle($passable, $next, ...$pipe['parameters']),
            $destination
        );

        try {
            $res = $pipeline($passable);
        } catch (Throwable $e) {
            return self::resolveException($e);
        }

        switch (true) {
            case is_string($res):
                return response()->make($res);
            case is_array($res):
                return response()->json($res);
            case $res instanceof Response:
                return $res->send();
            default:
                return $res;
        }
    }

    public function getName()
    {
        return $this->name ?? null;
    }

    private static function resolveException(Throwable $e)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        return match (true) {
            $e instanceof JsonBaseHttpException => $e->handle(),

            $e instanceof BaseHttpException =>
                Debugger::showErrorPage(
                    $e->httpCode(),
                    $e->getMessage(),
                    $e->getSubMessage()
                ),
            
            $e instanceof LowLevelException => 
                Debugger::dumpErr($e, false, true),

            default => throw $e,
        };
    }
}
