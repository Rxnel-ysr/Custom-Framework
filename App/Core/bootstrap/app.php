<?php

use App\App;
use App\Foundation\Http\Middleware;
use App\Foundation\Manager\ClassContainer;
use App\Foundation\Providers\AppServiceProvider;
use App\Support\Facades\DI;

return (static function () {
    $config = DI::get('appConfig');

    return App::configure($config['root'])
        ->withRouting([
            'web'       => '/routes/web.php',
            'api'       => '/routes/api.php',
            'api_prefix' => $config['router']['api_prefix'],
            'plugins'   => $config['router_plugins'],
        ])
        ->withMiddleware(function (Middleware $middleware) {
            $middleware->aliases([
                'test' => \App\Http\Middlewares\Test::class
            ]);
        })
        ->withServices([
            'container' => ClassContainer::class
        ])
        ->withProviders([
            AppServiceProvider::class
        ])
        ->withConfig($config['config'])
        ->withDependencies($config['dependencies']);
})();
