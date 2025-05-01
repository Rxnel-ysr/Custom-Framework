<?php

use App\Debug\Debugger;
use App\EXPE\Foundation\Manager\ClassManager;
use App\Foundation\Compiler\Compile;
use App\Foundation\Helpers\Env;
use App\Foundation\Http\Request;

$root = dirname(dirname(__DIR__));

require_once $root . '/App/Foundation/Manager/ClassManager_EXPE.php';
require_once $root . '/App/Foundation/Compiler/Compile.php';
require_once $root . '/App/Foundation/Helpers/Utility.php';
require_once $root . '/App/Foundation/Helpers/Helpers.php';
require_once $root . '/App/Http/Route.php';

$dependencies = require_once $root . '/config/app.php';
$configs = require_once $root . '/config/config.php';
$router_plugins = require_once $root . '/config/router_plugins.php';

ClassManager::init($root, false, true, [
    'classmap' => $root . '/config/classes.php',
    'cache_classmap' => $root . '/storage/cache/classes/classes.php',
]);
ClassManager::initAutoloader(true);

Env::load($root . '/config/.env');
Debugger::init(true, E_ALL & ~E_WARNING, $root . '/App/Core/error.php');


Compile::init(
    $root . '/resources/views',
    $root . '/storage/cache/views'
);

$request = new Request();
$app = new App($root, $request);

$app->setting($dependencies, $configs, $router_plugins);

return $app;
