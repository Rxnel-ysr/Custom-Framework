<?php

namespace App\Foundation\Manager;

use Inject;
use Setup;
use ReflectionClass;
use ReflectionNamedType;
use InvalidArgumentException;
use Generator;
use Exception;
use Dep;

#[Dep('./../Attributes/Setup.php')]
#[Dep('./../Attributes/Inject.php')]
/**
 * @depends ./../Attributes/Setup.php
 * @depends ./../Attributes/Inject.php
 */
class Resolver
{
    /**
     * Stores instances of various classes, keyed by class and constructor signature.
     *
     * @var array<string, object>
     */
    private static array $instances = [];

    /**
     * Prevent circular dependency recursion.
     *
     * @var array<string, bool>
     */
    private static array $visited = [];

    /* -----------------------------------------------------------------
        Instance Handling
    ----------------------------------------------------------------- */

    private static function makeKey(string $class, array $args = []): string
    {
        return $class . ':' . md5(serialize($args));
    }

    private static function getInstance(string $class, array $args = []): ?object
    {
        return self::$instances[self::makeKey($class, $args)] ?? null;
    }

    private static function setInstance(string $class, array $args, object $instance): void
    {
        self::$instances[self::makeKey($class, $args)] = $instance;
    }

    public static function get(string $key, ...$args): object
    {
        $keyHash = self::makeKey($key, $args);
        if (!isset(self::$instances[$keyHash])) {
            if (!class_exists($key)) {
                throw new Exception("Class $key does not exist.");
            }
            self::$instances[$keyHash] = new $key(...$args);
        }
        return self::$instances[$keyHash];
    }

    public static function has(string $key, array $args = []): bool
    {
        return isset(self::$instances[self::makeKey($key, $args)]);
    }

    public static function set(string $key, object $instance, array $args = []): object
    {
        self::setInstance($key, $args, $instance);
        return $instance;
    }

    public static function reset(string $key, array $args = []): void
    {
        unset(self::$instances[self::makeKey($key, $args)]);
    }

    public static function allInstances(): array
    {
        return self::$instances;
    }

    /* -----------------------------------------------------------------
        Instance Creation & Dependency Resolution
    ----------------------------------------------------------------- */

    /**
     * Create and register a new instance of a class.
     *
     * @template A of object
     * @param class-string<A> $class The class name to instantiate.
     * @param callable(A):void|null $func Optional callback that receives the instance.
     * @param array $args Constructor arguments.
     * @param string|null $name Optional custom key for registration.
     * @return A The created or cached instance.
     */
    public static function make(string $class, ?callable $func = null, array $args = [], ?string $name = null)
    {
        $key = $name ?? $class;

        // Return existing instance if same class and identical args already exist
        if ($existing = self::getInstance($key, $args)) {
            return $existing;
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

                // Inject attribute-based dependencies
                if (!empty($attrs)) {
                    $injectAttr = $attrs[0]->newInstance();
                    $injectClass = $injectAttr->class ?? ($paramType instanceof ReflectionNamedType ? $paramType->getName() : null);
                    if ($injectClass === null) {
                        throw new InvalidArgumentException("Unable to resolve Inject target for parameter: $paramName");
                    }
                    self::setup($injectClass, $injectAttr);
                    $params[] = self::getInstance($injectClass, $injectAttr->args ?? []) ?? self::get($injectClass, ...($injectAttr->args ?? []));
                    continue;
                }

                // Fallback to user-passed args or defaults
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

        $instance = $reflect->newInstanceArgs($params);

        if (is_callable($func)) {
            $func($instance);
        }

        self::setInstance($key, $args, $instance);
        return $instance;
    }

    /**
     * Build all default setups.
     *
     * @template B of object
     * @param class-string<B>[] $classes
     * @return B[]
     */
    public static function buildDefault(array $classes): array
    {
        $res = [];
        foreach ($classes as $class) {
            $res[] = self::setup($class);
        }
        return $res;
    }

    /**
     * Setup dependency resolution for annotated classes.
     *
     * @template C of object
     * @param class-string<C> $class
     * @param Inject|array<string, mixed> $inject
     * @return C
     */
    public static function setup(string $class, Inject|array $inject = [])
    {
        if (isset(self::$visited[$class])) return;
        self::$visited[$class] = true;

        $reflectionClass = new ReflectionClass($class);
        $attrs = $reflectionClass->getAttributes(Setup::class);

        if (!empty($attrs)) {
            $setupAttr = $attrs[0]->newInstance();

            foreach ($setupAttr->before as $dep) {
                self::setup($dep);
            }

            $args = $inject instanceof Inject ? $inject->args : (!empty($inject) ? $inject : $setupAttr->args);
            return self::make($class, null, $args);
        }
    }
}
