<?php

use App\App;
use App\Debug\Debugger;
use App\Foundation\Compiler\Compile;
use App\Foundation\Database\Connection;
use App\Foundation\Configuration\Env;
use App\Foundation\Manager\InstanceManager;
use App\Foundation\Providers\AppServiceProvider;
use App\Foundation\System\Disk;
use App\Support\Facades\DI;

// Define root path once
$__root = dirname(__DIR__, 3) . '/';
$isWeb = PHP_SAPI !== 'cli';

Env::load($__root . '.env');

// Load Other Configuration Files
$cfg = [
    'database'        => require $__root . 'config/database.php',
    'router'          => require $__root . 'config/router.php',
    'compiler'        => require $__root . 'config/compiler.php',
    ...$cfg
];

// Load Environment

// Init Debugger
Debugger::init(
    isWeb: $isWeb,
    errorLevel: E_ALL & ~E_WARNING,
    error_page: $__root . 'App/Core/error copy.php',
    store_at_log: false,
    log_file: $__root . 'storage/logs/debug.log'
);

if ($isWeb) {
    $nonce = base64_encode(random_bytes(16));
    if (isset($_ENV['CSP']) && $_ENV['CSP'] == true) {
        header("Content-Security-Policy: default-src 'self'; media-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'nonce-$nonce' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;");
    }
    DI::bind('nonce', fn() => $nonce);
    // Validate Routing
    $router = $cfg['router'];
    // echo $cfg['router_path'][$router['router']]. '<br>';
    // var_dump($router['router']);
    // exit;
    if (!in_array($router['router'], $router['choices'])) {
        throw new InvalidArgumentException("Invalid router: {$router['router']}");
    }
    // Boot the route handler
    require $cfg['router']['path'][$router['router']];
}

// Compile Views
Compile::init(
    $cfg['compiler']['views'],
    $cfg['compiler']['cache'],
    $cfg['compiler']['ext'],
);

// Setup Database
Connection::set($cfg['database']);

// System Resources
$disk = new Disk($__root . 'public');


$app = require __DIR__ . '/app.php';

// Inject Instances
InstanceManager::setInstance('app', $app);
InstanceManager::setInstance('appDisk', $disk);

// Boot and Register provider
createInstance(AppServiceProvider::class, function ($c) {
    $c->register();
    $c->boot();
});

// Return the ready App instance
return $app;
