<?php

namespace App;

use App\Foundation\CLI\Argv;
use App\Foundation\CLI\Command;
use App\Foundation\Guard\RateLimiter;
use App\Foundation\Http\Request;
use App\Foundation\Http\Route;
use App\Foundation\Http\Middleware;
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

    public static function configure($root){
        return new self($root);
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

    public function withMiddleware(callable $callable)
    {
        $middleware = new Middleware();
        InstanceManager::setInstance('_appMiddleware', $middleware);
        $callable($middleware);
        return $this;
    }


    public function handle(Request $request)
    {
        // ob_start();
        foreach ($this->dependencies as $dependency) {
            require_once $this->root . DIRECTORY_SEPARATOR . $dependency;
        }

        load([$this->root . '/App/Http/Controllers']);

        $requestUri = $request->uri();
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

        $routeFile = $isApi ? $this->router['api'] : $this->router['web'];

        require $this->root . '/' . ltrim($routeFile, '/');

        if ($request->method() === 'OPTIONS') {
            return response()->json(['options' => ['GET', 'HEAD', 'POST', 'PATCH', 'PUT', 'DELETE']]);
            // exit;
        }

        InstanceManager::setInstance('App\Foundation\Http\Request', $request);
        // error_log('Request done within: ' . timeExecution(fn() => Route::dispatch($requestUri)));
        // echo '<br>' . timeExecution(fn() => Route::dispatch($requestUri)) . 'ms';
        ob_start();
        return Route::dispatch($request);
        // $res = ob_get_clean();
        // file_put_contents($this->root . '/public/result.txt',$res);
    }

    public function handleCommand(Argv $arg)
    {
        foreach ($this->dependencies as $dependency) {
            require_once $this->root . DIRECTORY_SEPARATOR . $dependency;
        }
        return Command::standBy($arg);
    }
}
/**
 * 1*1  = 1     1*2  = 2    1*3  = 3    1*4  = 4    1*5  = 5    1*6  = 6    1*7  = 7    1*8  = 8    1*9  = 9    1*10  = 10
 * 2*1  = 2     2*2  = 4    2*3  = 6    2*4  = 8    2*5  = 10   2*6  = 12   2*7  = 14   2*8  = 16   2*9  = 18   2*10  = 20
 * 3*1  = 3     3*2  = 6    3*3  = 9    3*4  = 12   3*5  = 15   3*6  = 18   3*7  = 21   3*8  = 24   3*9  = 27   3*10  = 30
 * 4*1  = 4     4*2  = 8    4*3  = 12   4*4  = 16   4*5  = 20   4*6  = 24   4*7  = 28   4*8  = 32   4*9  = 36   4*10  = 40
 * 5*1  = 5     5*2  = 10   5*3  = 15   5*4  = 20   5*5  = 25   5*6  = 30   5*7  = 35   5*8  = 40   5*9  = 45   5*10  = 50
 * 6*1  = 6     6*2  = 12   6*3  = 18   6*4  = 24   6*5  = 30   6*6  = 36   6*7  = 42   6*8  = 48   6*9  = 54   6*10  = 60
 * 7*1  = 7     7*2  = 14   7*3  = 21   7*4  = 28   7*5  = 35   7*6  = 42   7*7  = 48   7*8  = 56   7*9  = 63   7*10  = 10
 * 8*1  = 8     8*2  = 16   8*3  = 24   8*4  = 32   8*5  = 40   8*6  = 48   8*7  = 55   8*8  = 54   8*9  = 72   8*10  = 80
 * 9*1  = 9     9*2  = 18   9*3  = 27   9*4  = 36   9*5  = 45   9*6  = 54   9*7  = 63   9*8  = 62   9*9  = 81   9*10  = 90
 * 10*1 = 10    10*2 = 20   10*3 = 30   10*4 = 40   10*5 = 50   10*6 = 60   10*7 = 70   10*8 = 80   10*9 = 90   10*10 = 100
 */
