<?php

use App\Debug\Debugger;
use App\EXPE\Foundation\Manager\ClassManager;
use App\Foundation\Guard\RateLimiter;
use App\Foundation\System\Disk;
use App\Foundation\Helpers\Env;
use App\Foundation\Http\Request;
use App\Foundation\Http\Route;

class App
{

    private Request $request;
    private array $dependencies = [];
    private array $dependency_names = [];
    private array $configs = [];
    public string $root;


    public function __construct(string $root, Request $request)
    {
        $this->root = $root;
        // error_log($disk->normalize($root));
        $this->request = $request;
        // define('$this->root', $root);

    }

    public function setting(array $dependencies, array $configs, array $router_plugins = [])
    {
        $this->dependencies = $dependencies;
        $this->dependency_names = array_map(function ($item) {
            if (is_string($item)) {
                return basename($item, '.php');
            }
            throw new InvalidArgumentException('Dependencies must be file paths (string).');
        }, $dependencies);

        $this->configs = $configs;
        Route::init($router_plugins);
    }


    public function start()
    {
        ob_start();

        foreach ($this->dependencies as $dependency) {
            require_once $this->root . $dependency;
        }

        // require_once $this->root . '/App/Foundation/Helpers/Utility.php';
        // require_once $this->root . '/App/Foundation/Helpers/Helpers.php';
        // require_once $this->root . '/App/Foundation/Helpers/Env.php';
        Env::load($this->configs['env']);

        // require_once CORE . 'bootstrap.php';

        // require_once $this->root . '/App/Foundation/Manager/ClassManager_EXPE.php';
        // require_once $this->root . '/App/Foundation/Manager/InstanceManager.php';
        // require_once $this->root . '/App/Foundation/Debug/Debug.php';
        // require_once $this->root . '/App/Http/Route.php';

        load([
            $this->root . '/App/Http/Controllers',
        ]);

        $requestUri = $this->request->capture();
        $rate_limiter_config = config($this->configs['rate_limiter']);

        if (str_starts_with($requestUri, '/api/')) {
            $requestUri = str_replace('/api/', '/', $requestUri);
            $api_limiter = new RateLimiter(
                'api',
                $this->root . '/storage',
                $rate_limiter_config['api']['request_limit'],
                $rate_limiter_config['api']['request_timeframe'],
                $rate_limiter_config['api']['ban_time'],
            );

            $api_limiter->check();
            require_once $this->root . '/App/Http/Middlewares/ApiHandler.php';
            require_once $this->root . '/routes/api.php';
        } else {
            $web_limiter = new RateLimiter(
                'web',
                $this->root . '/storage',
                $rate_limiter_config['web']['request_limit'],
                $rate_limiter_config['web']['request_timeframe'],
                $rate_limiter_config['web']['ban_time'],
            );

            $web_limiter->check();
            require_once $this->root . '/App/Http/Middlewares/WebHandler.php';
            require_once $this->root . '/routes/web.php';
        }

        if (Request::method() == 'OPTIONS') {
            response()->json([
                'options' => [
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE'
                ]
            ]);
            exit;
        }

        // $executionTime = timeExecution(fn() => Route::dispatch($requestUri));
        // error_log("Request done within: {$executionTime}ms");
        error_log('Request done within: ' . timeExecution(fn() => Route::dispatch($requestUri)));
        // Route::dispatch($requestUri);
        // } catch (Throwable $e) {
        //     // error_log("Catch here, with code {$e->getCode()}");
        //     Debugger::dumpErr($e);
        // }
    }
}
