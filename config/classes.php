<?php
require_once './App/Core/definitions.php';

return [
    App\utils\Env::class => ROOT . 'App/Utils/Env.php',
    App\Utils\Model::class => ROOT . 'App/Utils/Model.php',
    App\Utils\Http\Request::class => ROOT . 'App/Utils/Request.php',
    App\Utils\Http\Controller::class => ROOT . 'App/Http/Controllers/Controller.php',
    App\Utils\Http\Response::class => ROOT .  ' App/Utils/Response.php',
    App\utils\Guard\CSRF::class => ROOT . 'App/Utils/CSRF.php',
    App\Utils\Guard\Validator::class => ROOT . 'App/Utils/Validator.php',
    App\Utils\Guard\RateLimiter::class => ROOT . 'App/Utils/RateLimiter.php',
    App\Utils\Database\Migration::class => ROOT . 'App/Utils/Migration.php',
    App\Utils\Database\Connection::class => ROOT . 'App/Utils/Connection.php',
    App\Utils\Database\QueryBuilder::class => ROOT . 'App/Utils/QueryBuilder.php',
    App\Utils\Manager\ClassManager::class => ROOT . 'App/Utils/ClassManager.php',
    App\Utils\Manager\InstanceManager::class => ROOT . 'App/Utils/InstanceManager.php',
    Route::class => ROOT . 'App/Http/Route.php',
    Test::class => ROOT . 'App/Utils/Test.php',
];
