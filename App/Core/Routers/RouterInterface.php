<?php

namespace App\Foundation\Http;

use App\Debug\Debugger;
use App\Foundation\Exceptions\Framework\BaseException;
use App\Foundation\Exceptions\Framework\Exception;
use App\Foundation\Exceptions\Framework\Http\BaseHttpException;
use App\Foundation\Exceptions\Framework\Http\Json\JsonBaseHttpException;
use App\Foundation\Exceptions\Framework\LowLevelException;
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

        !($res instanceof Response) ? (is_array($res)
            ? $response->json($res)
            : $response->make((string)$res)) : $res;

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
     * @param array $parameters
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
}
