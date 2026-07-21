<?php

use App\App;
use App\Foundation\Exceptions\Framework\Database\ModelNotFoundException;
use App\Foundation\Http\Request;
use App\Foundation\Providers\AppServiceProvider;
use App\Support\Facades\DI;

return (static function (): App {
    $config = DI::get('appConfig');

    return App::configure($config['root'])
        ->withRouting([
            'web'       => '/routes/web.php',
            'api'       => '/routes/api.php',
            'api_prefix' => $config['router']['api_prefix'],
            'plugins'   => $config['router_plugins'],
        ])
        ->withMiddleware(static function ($middleware) {
            $middleware->aliases([
                'test' => \App\Http\Middlewares\Test::class,
                'bearer' => \App\Http\Middlewares\Bearer::class,
                'csrf' => \App\Http\Middlewares\CSRF::class,
            ]);
        })
        ->withExceptions(static function ($exception) {
            $exception->render(function (ModelNotFoundException $e, Request $request) {
                return response()->json($request->all());
            });
        })
        ->withProviders([
            AppServiceProvider::class
        ])
        ->withConfig($config)
        ->create();
})();
