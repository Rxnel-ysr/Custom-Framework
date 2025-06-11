<?php

namespace App;

use App\Foundation\Guard\RateLimiter;
use App\Foundation\Helpers\Env;
use App\Foundation\Http\Request;
use App\Foundation\Http\Route;
use App\Foundation\Manager\InstanceManager;
use InvalidArgumentException;

// use App\EXPE\Foundation\Http\Route;


class App
{
    private array $dependencies = [];
    private array $dependency_names = [];
    public array $configs = [];
    private array $router = [];
    public string $root;

    public function __construct(string $root)
    {
        $this->root = DIRECTORY_SEPARATOR . trim($root, DIRECTORY_SEPARATOR);
    }

    /**
     * @param string|null $key
     * @return mixed[]|mixed|null
     */
    public function getConfig(?string $key = null): mixed
    {
        return $key !== null
            ? ($this->configs[$key] ?? null)
            : $this->configs;
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

        Route::init($this->root, $specs['plugins'] ?? []);
        return $this;
    }


    public function handle(Request $request)
    {
        // ob_start();
        foreach ($this->dependencies as $dependency) {
            require_once $this->root . DIRECTORY_SEPARATOR . $dependency;
        }

        Env::load($this->configs['env']);
        load([$this->root . '/App/Http/Controllers']);

        $requestUri = $request->url();
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

        !str_starts_with($requestUri, '/__') && $limiter->check();

        $middleware = $isApi ? 'ApiHandler.php' : 'WebHandler.php';
        $routeFile = $isApi ? $this->router['api'] : $this->router['web'];

        require $this->root . "/App/Http/Middlewares/{$middleware}";
        require $this->root . '/' . ltrim($routeFile, '/');

        if ($request->method() === 'OPTIONS') {
            return response()->json(['options' => ['GET', 'HEAD', 'POST', 'PATCH', 'PUT', 'DELETE']]);
            // exit;
        }

        InstanceManager::setInstance('App\Foundation\Http\Request', $request);
        // error_log('Request done within: ' . timeExecution(fn() => Route::dispatch($requestUri)));
        // echo '<br>' . timeExecution(fn() => Route::dispatch($requestUri)) . 'ms';
        ob_start();
        Route::dispatch($requestUri);
        // $res = ob_get_clean();
        // file_put_contents($this->root . '/public/result.txt',$res);
    }
}
