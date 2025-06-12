<?php

use App\App;
use App\Debug\Debugger;
use App\EXPE\Foundation\Manager\ClassManager;
use App\Foundation\Compiler\Compile;
use App\Foundation\Database\Connection;
use App\Foundation\Helpers\Env;
use App\Foundation\Http\Request;
use App\Foundation\Manager\InstanceManager;
use App\Foundation\Providers\AppServiceProvider;
use App\Foundation\Reactive\Reactive;
use App\Foundation\System\Disk;
use App\Support\Facades\DI;

// Define root path once
const BASE_PATH = __DIR__ . '/../../';
$isWeb = PHP_SAPI !== 'cli';


// Load Core
require_once BASE_PATH . 'App/Foundation/Manager/ClassManager_EXPE.php';
require_once BASE_PATH . 'App/Providers/AppServiceProvider.php';
require_once BASE_PATH . 'App/Core/Routers/RouterInterface.php';
require_once BASE_PATH . 'App/Foundation/Compiler/Compile.php';
require_once BASE_PATH . 'App/Foundation/Helpers/Utility.php';
require_once BASE_PATH . 'App/Foundation/Helpers/Helpers.php';

// Load Configuration Files
$cfg = [
    'dependencies'    => require BASE_PATH . 'config/app.php',
    'config'          => require BASE_PATH . 'config/config.php',
    'database'        => require BASE_PATH . 'config/database.php',
    'router'          => require BASE_PATH . 'config/router.php',
    'router_path'     => require BASE_PATH . 'config/router_path.php',
    'router_plugins'  => require BASE_PATH . 'config/router_plugins.php' ?? [],
    'compiler'        => require BASE_PATH . 'config/compiler.php',
    'auto-loader'     => require BASE_PATH . 'config/autoloader.php',
];


// Init Class Manager
ClassManager::set(BASE_PATH, $cfg['auto-loader']['debug'], true, [
    'classmap'      => $cfg['auto-loader']['classmap'],
    'cache_classmap' => $cfg['auto-loader']['cache'],
]);
ClassManager::initAutoloader($cfg['auto-loader']['auto-resolve']);


// Load Environment
Env::load(BASE_PATH . 'config/.env');

// Init Debugger
Debugger::init(
    isWeb: $isWeb,
    errorLevel: E_ALL & ~E_WARNING,
    error_page: BASE_PATH . 'App/Core/error.php',
    store_at_log: true,
    log_file: BASE_PATH . 'storage/logs/debug.log'
);

if ($isWeb) {
    $nonce = base64_encode(random_bytes(16));
    header("Content-Security-Policy: default-src 'self'; media-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'nonce-$nonce' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;");
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
    require_once $cfg['router_path'][$router['router']];
}

// Compile Views
Compile::init(
    $cfg['compiler']['views'],
    $cfg['compiler']['cache'],
    $cfg['compiler']['ext'],
);


// System Resources
$disk = new Disk(BASE_PATH . 'public');

// Initialize App
$app = (new App(root: BASE_PATH))
->withRouting([
        'web'       => '/routes/web.php',
        'api'       => '/routes/api.php',
        'api_prefix' => $cfg['router']['api_prefix'],
        'plugins'   => $cfg['router_plugins'],
    ])
    ->withConfig([
        'database' => $cfg['database'],
        ...$cfg['config']
    ])
    ->withDependencies($cfg['dependencies']);

// Inject Instances
InstanceManager::setInstance('app', $app);
InstanceManager::setInstance('appDisk', $disk);

// Boot and Register provider
AppServiceProvider::boot();
AppServiceProvider::register();

// Setup Database
Connection::set($cfg['database']);

// Return the ready App instance
return $app;
