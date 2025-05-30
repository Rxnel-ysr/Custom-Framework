<?php

namespace App\Support\Facades;

use App\Foundation\Manager\InstanceManager;

/**
 * @template T of object
 * 
 * @method static T getFacadeRoot()
 * @method static T resolveFacadeInstance()
 * 
 * @phpstan-template T of object
 * @psalm-template T of object
 */
abstract class Facade
{
    /**
     * @return class-string<T>|T
     */
    abstract protected static function getFacadeAccessor(): string|object;

    /**
     * @return T
     */
    protected static function resolveFacadeInstance()
    {
        $accessor = static::getFacadeAccessor();
        return is_object($accessor)
            ? $accessor
            : InstanceManager::getInstance($accessor);
    }

    /**
     * @return mixed
     */
    public static function __callStatic(string $method, array $args)
    {
        $instance = static::resolveFacadeInstance();
        return $instance->$method(...$args);
    }

    /**
     * @return mixed
     */
    public function __call(string $method, array $args)
    {
        $instance = static::resolveFacadeInstance();
        return $instance->$method(...$args);
    }
}
