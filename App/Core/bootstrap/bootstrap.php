<?php

use App\Debug\Debugger;
use App\Foundation\Compiler\Compile;
use App\Foundation\Database\Connection;
use App\Foundation\Configuration\Env;
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
        'database'       => require $__root . 'config/database.php',
        'router'         => require $__root . 'config/router.php',
        'compiler'       => require $__root . 'config/compiler.php',
        'dependencies'   => require $__root . 'config/app.php',
        'config'         => require $__root . 'config/config.php',
        'router_plugins' => require $__root . 'config/router_plugins.php',
        'root'           => $__root,
    ];

    // Bind configuration to DI
    DI::bind('appConfig', fn() => $cfg);

    // Debugger initialization
    Debugger::init(
        isWeb: $isWeb,
        errorLevel: E_ALL & ~E_WARNING,
        error_page: $__root . 'App/Core/error copy.php',
        store_at_log: false,
        log_file: $__root . 'storage/logs/debug.log'
    );

    // Web-only setup
    if ($isWeb) {
        $nonce = base64_encode(random_bytes(16));

        if (!empty($_ENV['CSP'])) {
            header("Content-Security-Policy: default-src 'self'; media-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'nonce-$nonce' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;");
        }

        DI::bind('nonce', fn() => $nonce);

        // Validate router choice
        $choices = array_fill_keys($cfg['router']['choices'], true);
        $selected = $cfg['router']['router'] ?? null;

        if (empty($selected) || !isset($choices[$selected])) {
            throw new InvalidArgumentException("Invalid router: {$selected}");
        }

        // Boot route handler
        require $cfg['router']['path'][$selected];
    }

    // Initialize compiler
    Compile::init(
        $cfg['compiler']['views'],
        $cfg['compiler']['cache'],
        $cfg['compiler']['ext']
    );

    // Initialize database
    Connection::set($cfg['database']);

    // Initialize system resources
    $disk = new Disk($__root . 'public');

    // Load and configure app instance
    $app = require __DIR__ . '/app.php';

    // Inject instances
    InstanceManager::setInstance('app', $app);
    InstanceManager::setInstance('appDisk', $disk);

    // Boot and register service providers
    createInstance(AppServiceProvider::class, function ($provider) {
        $provider->register();
        $provider->boot();
    });

    // Return fully booted App instance
    return $app;
})();
