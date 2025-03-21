<?php

use App\Debug\Debugger;
use App\Utils\Guard\RateLimiter;
use App\Utils\Http\Response;

require_once 'definitions.php';
require_once UTILS_PATH . 'Debug.php';

try {
    require_once UTILS_PATH . 'Utility.php';

    load([
        UTILS_PATH,
        ROOT . 'App/Models',
        HTTP . 'Route.php',
        ROOT . 'routes',
        CONTROLLERS . 'Controller.php',
        CONTROLLERS,
    ], [
        UTILS_PATH . 'Utility.php',
        UTILS_PATH . 'Env.php',
        UTILS_PATH . 'Debug.php',
    ]);   

    $rate_limiter_config = require_once CONFIG . 'rate-limiter.php';

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
} catch (Exception $e) {
    Debugger::dumpTrace($e->getTrace());
    showErrorPage(500, $e->getMessage());
}

// Due some update, performance is whopping going down from 0.0005+~0.006+ to 0.#+ haizzzzzzzzz(Maybe these project are growing in size)