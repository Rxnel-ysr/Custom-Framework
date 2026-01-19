<?php

use App\Debug\Debugger;
use App\Foundation\Compiler\Compile;
use App\Foundation\Database\Connection;
use App\Foundation\Configuration\Env;
use App\Foundation\Http\Response;
use App\Foundation\Manager\Container;
use App\Foundation\Manager\InstanceManager;
use App\Foundation\Providers\AppServiceProvider;
use App\Foundation\System\Disk;
use App\Support\Facades\DI;

return (static function () {
    $__root = dirname(__DIR__, 3) . "/";
    $isWeb = PHP_SAPI !== 'cli';

    // Load environment
    Env::load($__root . '.env');

    // Configuration loading
    $cfg = [
        'database'       => require "{$__root}config/database.php",
        'router'         => require "{$__root}config/router.php",
        'compiler'       => require "{$__root}config/compiler.php",
        'app'            => require "{$__root}config/app.php",
        'config'         => require "{$__root}config/config.php",
        'router_plugins' => require "{$__root}config/router_plugins.php",
        'root'           => $__root,
    ];

    date_default_timezone_set($cfg['app']['timezone']);

    // Bind configuration to DI
    DI::bind('appConfig', fn() => $cfg);

    // Debugger initialization
    Debugger::init(
        isWeb: $isWeb,
        errorLevel: E_ALL & ~E_WARNING,
        error_page: "{$__root}App/Core/error/error.php",
        store_at_log: false,
        log_file: "{$__root}storage/logs/debug.log"
    );

    // Initialize compiler
    Compile::init(
        $cfg['compiler']['views'],
        $cfg['compiler']['cache'],
        $cfg['compiler']['ext']
    );

    // Initialize database
    Connection::set($cfg['database']);

    // Load and configure app instance
    $app = require __DIR__ . '/app.php';

    // Inject instances
    InstanceManager::setInstance('app', $app);

    // Boot and register service providers
    createInstance(Disk::class, null, 'appDisk', "{$__root}public");
    $app->setupProviders();
    $app->container->bind(Response::class, fn() => response());

    // Return fully booted App instance
    return $app;
})();
