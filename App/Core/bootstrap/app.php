<?php

use App\App;
use App\Foundation\Http\Middleware;
use App\Http\Middlewares\Test;

$__root = dirname(__DIR__, 3) . '/';

$cfg = [
    'dependencies'    => require $__root . 'config/app.php',
    'config' => require $__root . 'config/config.php',
    'router' => require $__root . 'config/router.php',
    'router_plugins' => require $__root . 'config/router_plugins.php',
];

return App::configure($__root)
    ->withRouting([
        'web'       => '/routes/web.php',
        'api'       => '/routes/api.php',
        'api_prefix' => $cfg['router']['api_prefix'],
        'plugins'   => $cfg['router_plugins'],
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->aliases([
            'test' => Test::class
        ]);
    })
    ->withConfig([
        ...$cfg['config']
    ])
    ->withDependencies($cfg['dependencies']);
