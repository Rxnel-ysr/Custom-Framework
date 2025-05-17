<?php

use App\App;
use App\Debug\Debugger;
use App\EXPE\Foundation\Manager\ClassManager;
use App\Foundation\Compiler\Compile;
use App\Foundation\Database\Connection;
use App\Foundation\Helpers\Env;
use App\Foundation\Http\Request;
use App\Foundation\Manager\InstanceManager;
use App\Foundation\System\Disk;

$root = dirname(__DIR__, 2);

// echo $root . '<br>';

// var_dump(file_exists($root . '/config/classes.php'));
// die();


require_once $root . '/App/Foundation/Manager/ClassManager_EXPE.php';
require_once $root . '/App/Foundation/Compiler/Compile.php';
require_once $root . '/App/Foundation/Helpers/Utility.php';
require_once $root . '/App/Foundation/Helpers/Helpers.php';

// echo $root . '<br>';
$dependencies = require_once $root . '/config/app.php';
$configs = require_once $root . '/config/config.php';
$database = require_once $root . '/config/database.php';
$router = require_once $root . '/config/router.php';
$router_path = require_once $root . '/config/router_path.php';
$router_plugins = require_once $root . '/config/router_plugins.php' ?? [];
// echo $root . '<br>';

ClassManager::init($root, false, true, [
    'classmap' => "$root/config/classes.php",
    'cache_classmap' => "$root/storage/cache/classes/classes.php",
]);
ClassManager::initAutoloader(true);

Env::load($root . '/config/.env');
Debugger::init(true, E_ALL & ~E_WARNING, $root . '/App/Core/error.php', true, $root . '/storage/logs/debug.log');

if (!in_array($router['router'], $router['choices'])) {
    throw new Exception('Invalid choice: ' . $router['router'] . ' for routing');
}

require_once $router_path[$router['router']];


Compile::init(
    "$root/resources/views",
    "$root/storage/cache/views",
    '.rx.php'
);
$disk = new Disk($root . '/public');
$request = new Request();
$app = new App(root: $root)
    ->withRouting([
        'web' => '/routes/web.php',
        'api' => '/routes/api.php',
        'api_prefix' => 'api',
        'plugins' => $router_plugins
    ])->withConfig([
        'database' => $database,
        ...$configs
    ])
    ->withDependencies($dependencies);
InstanceManager::setInstance('app', $app);
InstanceManager::setInstance('appDisk', $disk);
Connection::set($database);

return $app;
