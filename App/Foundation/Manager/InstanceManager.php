<?php

namespace App\Foundation\Manager;

class InstanceManager
{
    /**
     * Stores instances of various classes.
     *
     * @var array<string, object>
     */
    private static array $instances = [];

    /**
     * Retrieve a singleton instance of the given class.
     *
     * @template T
     * 
     * @param class-string<T> $key The class name or key identifier.
     * @param mixed ...$args Optional parameters for the constructor.
     * @return T The singleton instance of the requested class.
     * @throws \Exception If the class does not exist.
     */
    public static function getInstance(string $key, ...$args): object
    {
        if (!isset(self::$instances[$key])) {
            if (!class_exists($key)) {
                throw new \Exception("Class $key does not exist.");
            }
            self::$instances[$key] = new $key(...$args);
        }
        return self::$instances[$key];
    }

    /**
     * Retrieve a singleton instance of the given class.
     *
     * @param string $key The class name or key identifier.
     * @param object $instance Instance to be keep
     * @return object The singleton instance of the requested class.
     */
    public static function setInstance(string $key, object $instance): object
    {
        self::$instances[$key] = $instance;
        return self::$instances[$key];
    }

    /**
     * Remove an instance from the storage (reset it).
     *
     * @param string $key The class name or key identifier.
     * @return void
     */
    public static function resetInstance(string $key): void
    {
        unset(self::$instances[$key]);
    }

    /**
     * Get all currently stored instances.
     *
     * @return array<string, object> The array of instantiated classes.
     */
    public static function allInstances(): array
    {
        return self::$instances;
    }
}
