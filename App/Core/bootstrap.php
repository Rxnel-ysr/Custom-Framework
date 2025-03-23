<?php

use App\Debug\Debugger;
use App\Utils\Guard\RateLimiter;
use App\Utils\Manager\ClassManager;

require_once 'definitions.php';
require_once UTILS_PATH . 'Debug.php';

Debugger::init(true, 0);

require_once UTILS_PATH . 'ClassManager.php';
require_once HTTP . 'Route.php';
require_once CONTROLLERS . 'Controller.php';
require_once UTILS_PATH . 'Model.php';
require_once UTILS_PATH . 'Helpers.php';

ClassManager::init();

try {

    spl_autoload_register([ClassManager::class, 'autoload']);

    load([
        ROOT . 'App/Models',
        ROOT . 'routes',
        CONTROLLERS,
    ]);

    $rate_limiter_config = config(CONFIG . 'rate-limiter.php');

    $api_limiter = new RateLimiter(
        'api',
        $rate_limiter_config['api']['request_limit'],
        $rate_limiter_config['api']['request_timeframe'],
        $rate_limiter_config['api']['ban_time'],
    );

    $web_limiter = new RateLimiter(
        'web',
        $rate_limiter_config['web']['request_limit'],
        $rate_limiter_config['web']['request_timeframe'],
        $rate_limiter_config['web']['ban_time'],
    );

    $trimmedUri = ltrim($requestUri, '/');

    if (str_starts_with($trimmedUri, 'api/') && str_starts_with($trimmedUri, 'api')) {
        $api_limiter->check();
        require_once MIDDLEWARES . 'ApiHandler.php';
    } else {
        $web_limiter->check();
        require_once MIDDLEWARES . 'WebHandler.php';
    }

    if (getRequestMethod() == 'OPTIONS') {
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

    $executionTime = timeExecution(fn() => Route::dispatch($requestUri));
    error_log("Request done within: {$executionTime}ms");
} catch (\Throwable $e) {
    if ($e->getCode() == 404) {
        echo 'Okay that work';
        Debugger::dumpErr($e, true);
    }
}
