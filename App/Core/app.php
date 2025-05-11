<?php

use App\Foundation\Guard\RateLimiter;
use App\Foundation\Helpers\Env;
use App\Foundation\Http\Request;
use App\Foundation\Http\Route;

class App
{
    private Request $request;
    private array $dependencies = [];
    private array $dependency_names = [];
    private array $configs = [];
    private array $router = [];
    public string $root;

    public function __construct(string $root, Request $request)
    {
        $this->root = $root;
        $this->request = $request;
    }

    public function withDependencies(array $dependencies): self
    {
        $this->dependencies = $dependencies;
        $this->dependency_names = array_map(function ($item) {
            if (!is_string($item)) {
                throw new InvalidArgumentException('Dependencies must be file paths (string).');
            }
            return basename($item, '.php');
        }, $dependencies);
        return $this;
    }

    public function withConfig(array $configs): self
    {
        $this->configs = $configs;
        return $this;
    }

    public function withRouting(array $specs): self
    {
        $this->router = $specs;

        if (!isset($specs['web'])) {
            throw new InvalidArgumentException("Routing specs must include 'web' paths.");
        }

        $this->router['api_prefix'] ??= 'api/';

        Route::init($specs['plugins'] ?? []);
        return $this;
    }


    public function start(): void
    {
        foreach ($this->dependencies as $dependency) {
            require_once $this->root . $dependency;
        }

        Env::load($this->configs['env']);
        load([$this->root . '/App/Http/Controllers']);

        $requestUri = $this->request->capture();
        $rateLimiterConfig = config($this->configs['rate_limiter']);
        $apiPrefix = '/' . trim($this->router['api_prefix'], '/') . '/';

        $isApi = str_starts_with($requestUri, $apiPrefix);
        $requestUri = $isApi ? str_replace($apiPrefix, '/', $requestUri) : $requestUri;
        $scope = $isApi ? 'api' : 'web';

        $limiter = new RateLimiter(
            $scope,
            $this->root . '/storage',
            $rateLimiterConfig[$scope]['request_limit'],
            $rateLimiterConfig[$scope]['request_timeframe'],
            $rateLimiterConfig[$scope]['ban_time']
        );

        $limiter->check();

        $middleware = $isApi ? 'ApiHandler.php' : 'WebHandler.php';
        $routeFile = $isApi ? $this->router['api'] : $this->router['web'];

        require_once $this->root . "/App/Http/Middlewares/{$middleware}";
        require_once $this->root . '/' . ltrim($routeFile, '/');

        if ($this->request->method() === 'OPTIONS') {
            response()->json(['options' => ['GET', 'POST', 'PUT', 'DELETE']]);
            exit;
        }

        error_log('Request done within: ' . timeExecution(fn() => Route::dispatch($requestUri)));
    }
}
