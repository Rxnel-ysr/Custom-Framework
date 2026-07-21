<?php

use App\Debug\Debugger;
use App\Foundation\Compiler\Compile;
use App\Foundation\Configuration\Config;
use App\Foundation\Database\Connection;
use App\Foundation\Configuration\Env;
use App\Foundation\Http\Response;
use App\Foundation\Manager\InstanceManager;
use App\Foundation\Support\Time;
use App\Foundation\System\Disk;
use App\Support\Facades\DI;

return (static function () {
    $__root = base_path();
    $isWeb = php_sapi_name() !== 'cli';
    $cache = require base_path('/config/cache.php');

    // Load environment
    Env::load($__root . '.env');

    // Configuration loading
    if ($cache['config']) {
        $cfg = (new Config("{$__root}/storage/cache/config.php"))->readCache();
        $cfg['root'] = $__root;
    } else {
        $cfg = new Config("{$__root}/storage/cache/config.php", [
            'database'       => require "{$__root}/config/database.php",
            'router'         => require "{$__root}/config/router.php",
            'compiler'       => require "{$__root}/config/compiler.php",
            'app'            => require "{$__root}/config/app.php",
            'rate_limiter'   => require "{$__root}/config/rate_limiter.php",
            'root'           => $__root
        ]);
    }

    date_default_timezone_set($cfg['app']['timezone']);

    // Bind configuration to DI
    DI::bind('appConfig', fn() => $cfg);

    // Debugger initialization
    Debugger::init(
        isWeb: $isWeb,
        errorLevel: E_ALL & ~E_WARNING,
        error_page: "{$__root}/App/Core/error/error.php",
        store_at_log: false,
        log_file: "{$__root}/storage/logs/debug.log"
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
    
    Compile::expose([
        'app' => $app
    ]);

    // Inject instances
    InstanceManager::setInstance('app', $app);

    // Boot and register service providers
    createInstance(Disk::class, null, 'appDisk', "{$__root}/public");
    $app->setupProviders();
    $app->container->bind(Response::class, fn() => response());
    Time::setTimeZone($cfg['app']['timezone']);

    // Return fully booted App instance
    return $app;
})();
