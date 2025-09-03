<?php

namespace App\Foundation\Manager;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionException;

class Container
{
    protected $bindings = [];
    protected $instances = [];
    protected $dependencies = [];

    /**
     * Bind a dependency
     *
     * @param string $key
     * @param callable $resolver
     * @param boolean $shared
     * @return (array{resolver: callable, shared: bool})
     */
    public function bind(string $key, callable $resolver, bool $shared = false): array
    {
        return $this->bindings[$key] = [
            'resolver' => $resolver,
            'shared' => $shared
        ];
    }

    public function singleton(string $key, callable $resolver): void
    {
        $this->bind($key, $resolver, true);
    }

    public function get(string $key)
    {
        // Check if we already have a shared instance
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        if (!isset($this->bindings[$key])) {
            throw new ContainerException("No binding found for {$key}");
        }

        $resolved = $this->bindings[$key]['resolver']($this);

        // If it's a shared binding, store the instance
        if ($this->bindings[$key]['shared']) {
            $this->instances[$key] = $resolved;
        }

        return $resolved;
    }

    public function instance(string $key, $instance): void
    {
        $this->instances[$key] = $instance;
    }

    public function bindClass(string $abstract, string $concrete, bool $shared = false)
    {
        return $this->bind($abstract, fn() => $this->make($concrete), $shared);
    }

    public function make(string $class)
    {
        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if (!$constructor) return new $class();

        $params = $constructor->getParameters();
        $dependencies = $this->resolveParameters($params);

        return $reflection->newInstanceArgs($dependencies);
    }


    public function call(callable $func, array $parameters = [])
    {
        try {
            $reflection = new ReflectionFunction($func);
            $dependencies = $this->resolveParameters($reflection->getParameters(), $parameters);

            return call_user_func_array($func, $dependencies);
        } catch (ReflectionException $e) {
            throw new ContainerException("Failed to call function: " . $e->getMessage());
        }
    }

    public function callMethod($object, string $method, array $parameters = [])
    {
        try {
            $reflection = new ReflectionMethod($object, $method);
            $dependencies = $this->resolveParameters($reflection->getParameters(), $parameters);

            return $reflection->invokeArgs($object, $dependencies);
        } catch (ReflectionException $e) {
            throw new ContainerException("Failed to call method: " . $e->getMessage());
        }
    }

    protected function resolveParameters(array $parameters, array $provided = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            // If parameter was provided, use it
            if (array_key_exists($name, $provided)) {
                $dependencies[] = $provided[$name];
                continue;
            }

            // If parameter has a type hint, try to resolve it
            if ($type && !$type->isBuiltin()) {
                $typeName = $type->getName();
                if (isset($this->bindings[$typeName]) || isset($this->instances[$typeName])) {
                    $dependencies[] = $this->get($typeName);
                    continue;
                }
            }

            // If parameter is optional, use its default value
            if ($parameter->isOptional()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            throw new ContainerException("Cannot resolve parameter {$name}");
        }

        return $dependencies;
    }
}

class ContainerException extends \Exception {}
