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

// Define root path once
const BASE_PATH = __DIR__ . '/../../';

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
];


// Init Class Manager
ClassManager::set(BASE_PATH, true, true, [
    'classmap'      => BASE_PATH . 'config/classes.php',
    'cache_classmap' => BASE_PATH . 'storage/cache/classes/classes.php',
]);
ClassManager::initAutoloader(true);


// Load Environment
Env::load(BASE_PATH . 'config/.env');

// Init Debugger
Debugger::init(
    isWeb: true,
    errorLevel: E_ALL & ~E_WARNING,
    error_page: BASE_PATH . 'App/Core/error.php',
    store_at_log: true,
    log_file: BASE_PATH . 'storage/logs/debug.log'
);

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

// Compile Views
Compile::init(
    views_dir: BASE_PATH . 'resources/views',
    cache_dir: BASE_PATH . 'storage/cache/views',
    file_ext: '.rx.php'
);

// System Resources
$disk = new Disk(BASE_PATH . 'public');
$request = new Request();

// Initialize App
$app = (new App(root: BASE_PATH))
    ->withRouting([
        'web'       => '/routes/web.php',
        'api'       => '/routes/api.php',
        'api_prefix' => 'api',
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
