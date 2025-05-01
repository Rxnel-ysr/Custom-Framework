<?php

namespace App\Support\Facades;

use App\Support\Facades\Facade;

/**
 * @method getBearerToken
 */
class Request extends Facade
{
    public static function __callStatic($name, $arguments)
    {
        $instance = self::getInstance(\App\Foundation\Http\Request::class);
        if (method_exists($instance, $name)) {
            return call_user_func([$instance, $name], ...$arguments);
        }
    }
}
