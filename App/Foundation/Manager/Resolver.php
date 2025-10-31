<?php

namespace App\Foundation\Manager;

use Inject;
use ReflectionClass;
use ReflectionNamedType;
use InvalidArgumentException;
use Setup;

/**
 * @template T of Object
 * @depends ./../Attributes/Setup.php
 * @depends ./../Attributes/Inject.php
 */
class Resolver
{
    /**
     * Stores instances of various classes.
     *
     * @var array<string, object>
     */
    private static array $instances = [];

    /**
     * Preventing circular depedency
     *
     * @var array<string, bool>
     */
    private static array $visited = [];

    /**
     * Retrieve a singleton instance of the given class.
     *
     * @param string $key The class name or key identifier.
     * @param mixed ...$args Optional parameters for the constructor.
     * @return object The singleton instance of the requested class.
     * @throws Exception If the class does not exist.
     */
    public static function get(string $key, ...$args): object
    {
        if (!isset(self::$instances[$key])) {
            if (!class_exists($key)) {
                throw new \Exception("Class $key does not exist.");
            }
            self::$instances[$key] = new $key(...$args);
        }
        return self::$instances[$key];
    }

    public static function has(string $key)
    {
        return isset(self::$instances[$key]);
    }

    /**
     * Retrieve a singleton instance of the given class.
     *
     * @param string $key The class name or key identifier.
     * @param object Instance to be keep
     * @return object The singleton instance of the requested class.
     */
    public static function set(string $key, object $instance): object
    {
        self::$instances[$key] = $instance;
        return $instance;
    }

    /**
     * Reset given given instace
     *
     * @param string $key The class name or key identifier.
     * @return void
     */
    public static function reset(string $key): void
    {
        unset(self::$instances[$key]);
    }

    public static function allInstances(): array
    {
        return self::$instances;
    }

    /**
     * Create and register a new instance of a class.
     *
     * @template T of object
     * @param class-string<T> $class The class name to instantiate.
     * @param callable(T):void|null $func Optional callback that receives the instance.
     * @param array $args Constructor arguments.
     * @param string|null $name Optional name for registration.
     * @return T The created instance.
     */
    public static function createInstance(string $class, ?callable $func = null, array $args = [], ?string $name = null)
    {
        if (self::has($class)) {
            return self::get($class);
        }
        $reflect = new ReflectionClass($class);
        $params = [];

        $isAssoc = !array_is_list($args);
        $constructor = $reflect->getConstructor();

        if ($constructor) {
            foreach ($constructor->getParameters() as $index => $param) {
                $paramName = $param->getName();
                $paramType = $param->getType();
                $attrs = $param->getAttributes(Inject::class);

                // 1️⃣ Inject only if attribute present
                if (!empty($attrs)) {
                    $injectAttr = $attrs[0]->newInstance();
                    $injectClass = $injectAttr->class ?? ($paramType instanceof ReflectionNamedType ? $paramType->getName() : null);
                    self::setup($injectClass);

                    if ($injectClass === null) {
                        throw new InvalidArgumentException("Unable to resolve Inject target for parameter: $paramName");
                    }

                    $params[] = self::get($injectClass);
                    continue;
                }

                // 2️⃣ Fallback to passed args or defaults
                if ($isAssoc && array_key_exists($paramName, $args)) {
                    $value = $args[$paramName];
                } elseif (!$isAssoc && array_key_exists($index, $args)) {
                    $value = $args[$index];
                } elseif ($param->isDefaultValueAvailable()) {
                    $value = $param->getDefaultValue();
                } else {
                    throw new InvalidArgumentException("Missing required parameter: $paramName");
                }

                $params[] = $value;
            }
        }

        $instance = new $class(...$params);

        if (is_callable($func)) {
            $func($instance);
        }

        self::set($name ?? $class, $instance);
        return $instance;
    }

    public static function build(array $classes)
    {
        foreach ($classes as $class) {
            self::setup($class);
        }
    }

    public static function setup(string $class)
    {
        if (isset(self::$visited[$class])) return;
        self::$visited[$class] = true;

        $reflectionClass = new ReflectionClass($class);
        $attrs = $reflectionClass->getAttributes(Setup::class);
        if (!empty($attrs)) {
            $instance = $attrs[0]->newInstance();

            foreach ($instance->before as $dep) {
                self::setup($dep);
            }

            return self::createInstance($class, null, $instance->args);
        }
    }
}
