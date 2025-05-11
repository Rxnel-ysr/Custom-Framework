<?php

namespace App\Support\Facades;

use App\Foundation\Manager\InstanceManager;
use Exception;

abstract class Facade
{
    protected static function getFacadeAccessor()
    {
        throw new Exception("No accessor defined.");
    }

    public static function __callStatic($method, $args)
    {
        $instance = static::resolveFacadeInstance();
        return $instance->$method(...$args);
    }

    protected static function resolveFacadeInstance()
    {
        $accessor = static::getFacadeAccessor();
        if (is_object($accessor)) return $accessor;
        return InstanceManager::getInstance($accessor);
    }
}
