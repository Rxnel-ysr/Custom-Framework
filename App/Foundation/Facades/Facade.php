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
 * 
 * @depends App\Foundation\Manager\InstanceManager
 * 
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
        $instance = is_object($accessor)
            ? $accessor
            : InstanceManager::getInstance($accessor);
        static::afterCreate($instance);
        return $instance;
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

    /**
     * Undocumented function
     *
     * @param string[] $methods
     * @param array[] $args
     * @return void
     */
    public static function callMethods(array $methods, array $args): void
    {
        $instance = static::resolveFacadeInstance();

        foreach ($methods as $i => $method) {
            if (!method_exists($instance, $method)) {
                throw new \BadMethodCallException("Method {$method} does not exist.");
            }

            $instance->{$method}(...($args[$i] ?? []));
        }
    }

    /**
     * Runs after instantiation
     *
     * @param T $instance
     * @return void|mixed
     */
    public static function afterCreate($instance)
    {}
}
