<?php
require_once './App/Core/definitions.php';

return array(
    \App\Utils\Database\Migration::class => ROOT . 'App/Utils/Migration.php',
    \App\Utils\Database\Connection::class => ROOT . 'App/Utils/Connection.php',
    \App\Utils\Database\QueryBuilder::class => ROOT . 'App/Utils/QueryBuilder.php',
    \App\Utils\Http\Request::class => ROOT . 'App/Utils/Request.php',
    \App\Utils\Http\Response::class => ROOT . 'App/Utils/Response.php',
    \App\Utils\Guard\Validator::class => ROOT . 'App/Utils/Validator.php',
    \App\Utils\Model::class => ROOT . 'App/Utils/Model.php',
);
