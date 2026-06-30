<?php

namespace App;

use App\Foundation\Exceptions\Framework\BaseException;
use App\Support\Facades\Route;
use App\Foundation\Http\Request;
use App\Foundation\Http\Middleware;
use App\Foundation\Guard\RateLimiterSQL;
use App\Foundation\Manager\InstanceManager;
use Experimental\App\Foundation\CLI\Command;
use App\Foundation\Exceptions\Framework\Primitive\InvalidArgumentException;
use App\Foundation\Manager\ClassContainer;
use Closure;

class App
{
    private array $dependencies = [];
    private array $dependency_names = [];
    private array $providers = [];
    public array $configs = [];
    private array $router = [];
    public string $root;
    protected $services = [];

    public function __construct(string $root)
    {
        $this->root = DIRECTORY_SEPARATOR . trim($root, DIRECTORY_SEPARATOR);
    }

    public static function configure($root): App
    {
        return createInstance(App::class, null, App::class, $root);
    }

    /**
     * Create or retrieve class instance using app container
     * 
     * @template TClass
     * @param class-string<TClass> $class
     * @return TClass
     */
    public function make(string $class)
    {
        return $this->services['container']->make($class);
    }

    /**
     * Bind a abstract to a implementation
     *
     * @param string $abstract
     * @param string|callable|null|null $concrete
     * @param boolean $shared
     * @return void
     */
    public function bind(string $abstract, string|callable|null $concrete = null, bool $shared = false)
    {
        $this->services['container']->bind($abstract, $concrete, $shared);
    }

    public function __get($name)
    {
        return $this->services[$name] ?? null;
    }

    public function setService($name, $service)
    {
        $this->services[$name] = $service;
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

    /**
     * Undocumented function
     *
     * @param Closure(BaseException $exception) $closure
     * @return App
     */
    public function withExceptions(Closure $closure)
    {
        $closure(createInstance(BaseException::class));
        return $this;
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

    public function withProviders(array $providers)
    {
        $this->providers = array_map(fn($item) => createInstance($item), $providers);
        return $this;
    }

    public function withServices(array $services = [])
    {
        foreach ($services as $name => $service) {
            $this->services[$name] = createInstance($service, null, $name);
        }
        return $this;
    }

    public function setupProviders()
    {
        foreach ($this->providers as $provider) {
            $provider->register($this);
            $provider->boot($this);
        }
    }

    /**
     * @param Closure(Middleware $middleware) $callable
     * @return App
     */
    public function withMiddleware(Closure $callable)
    {
        createInstance(Middleware::class, $callable, '_appMiddleware')->setup();
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
        $isApi = false;
        $apiPrefix = '/' . trim($this->router['api_prefix'], '/') . '/';
        $isApi = str_starts_with($requestUri, $apiPrefix);
        
        if (!str_starts_with($requestUri, '/__')) {
            $rateLimiterConfig = require $this->configs['rate_limiter'];
            $requestUri = $isApi ? str_replace($apiPrefix, '/', $requestUri) : $requestUri;
            $scope = $isApi ? 'api' : 'web';

            $result = (new RateLimiterSQL(
                $scope,
                $this->root . '/storage/request.sqlite',
                $rateLimiterConfig[$scope]['request_limit'],
                $rateLimiterConfig[$scope]['request_timeframe'],
                $rateLimiterConfig[$scope]['ban_time']
            ))->check();

            if (!$result['allowed']) {
                if ($result['reason'] === 'paused') {
                    http_response_code(429);
                    header('Retry-After: ' . $result['retry_after']);
                    die('Too much request, slow down.');
                }
                if ($result['reason'] === 'banned') {
                    http_response_code(403);
                    die('You are banned until ' . date('Y-m-d H:i:s', $result['banned_until']));
                }
            }
        }

        $routeFile = $isApi ? $this->router['api'] : $this->router['web'];

        Route::group(['prefix' => $isApi ? $apiPrefix : ''], function () use ($routeFile) {
            require $this->root . '/' . ltrim($routeFile, '/');
        });

        InstanceManager::setInstance(Request::class, $request);
        // error_log('Request done within: ' . timeExecution(fn() => Route::dispatch($requestUri)));
        // echo '<br>' . timeExecution(fn() => Route::dispatch($requestUri)) . 'ms';
        ob_start();
        return Route::dispatch($request);
        // $res = ob_get_clean();
        // file_put_contents($this->root . '/public/result.txt',$res);
    }

    public function handleCommand()
    {
        foreach ($this->dependencies as $dependency) {
            require_once $this->root . DIRECTORY_SEPARATOR . $dependency;
        }
        return Command::standBy($GLOBALS['argv']);
    }

    /**
     * @return App
     */
    public function create()
    {
        $this->services['container'] = createInstance(ClassContainer::class, null, 'container');
        return $this;
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
