<?php

namespace App\Support\Facades;

use App\Foundation\Manager\InstanceManager;

abstract class Facade
{
    /**
     * Define facade accessor
     * 
     * @return string|object Must return a class-string or instance or a class
     */
    abstract protected static function getFacadeAccessor(): string|object;

    public static function __callStatic($method, $args)
    {
        $instance = static::resolveFacadeInstance();
        return $instance->$method(...$args);
    }

    public function __call($method, $args)
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
