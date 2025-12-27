<?php

namespace App\Foundation\Manager;

use App\Foundation\Exceptions\Framework\HighLevelException;
use App\Foundation\Exceptions\Framework\LowLevelException;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use Throwable;

class ClassContainer
{
    /**
     * @var array<string, array{concrete: string|callable|null, shared: bool}>
     */
    protected array $bindings = [];

    /**
     * @var array<string, mixed>
     */
    protected array $instances = [];

    /**
     * @var array<string, bool> Track currently resolving classes for circular dependency detection
     */
    protected array $resolving = [];

    /**
     * @var array<string, bool> Track singleton resolutions in progress
     */
    protected array $singletonResolving = [];

    /**
     * Maximum recursion depth to prevent infinite loops
     */
    protected int $maxRecursionDepth = 100;

    /**
     * Current recursion depth
     */
    protected int $currentDepth = 0;

    /**
     * Reflection classes cache
     *
     * @var array
     */
    protected array $classRefCache = [];

    /**
     * {@inheritdoc}
     */
    public function bind(string $abstract, string|callable|null $concrete = null, bool $shared = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];

        // Remove existing instance if rebinding
        unset($this->instances[$abstract]);
    }

    /**
     * {@inheritdoc}
     */
    public function singleton(string $abstract, string|callable|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * {@inheritdoc}
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;

        // Also bind as shared if not already bound
        if (!isset($this->bindings[$abstract])) {
            $this->bindings[$abstract] = [
                'concrete' => fn() => $instance,
                'shared' => true
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id): mixed
    {
        try {
            return $this->resolve($id);
        } catch (Throwable $e) {
            throw new NotFoundException(
                sprintf('No entry or class found for "%s"', $id),
                0,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return isset($this->instances[$id])
            || isset($this->bindings[$id])
            || class_exists($id)
            || interface_exists($id);
    }

    /**
     * {@inheritdoc}
     */
    public function make(string $class, array $parameters = []): mixed
    {
        return $this->resolve($class, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function call(callable $callable, array $parameters = []): mixed
    {
        try {
            $this->currentDepth++;
            $this->guardAgainstMaxRecursionDepth();

            if (is_array($callable)) {
                [$object, $method] = $callable;
                $reflection = new ReflectionMethod($object, $method);
            } elseif (is_string($callable) && str_contains($callable, '::')) {
                $reflection = new ReflectionMethod($callable);
            } else {
                $reflection = new ReflectionFunction($callable);
            }

            $dependencies = $this->resolveDependencies(
                $reflection->getParameters(),
                $parameters
            );

            return call_user_func_array($callable, $dependencies);
        } catch (ReflectionException $e) {
            throw new ClassContainerException(
                sprintf('Failed to reflect callable: %s', $e->getMessage()),
                0,
                $e
            );
        } finally {
            $this->currentDepth--;
        }
    }

    /**
     * Resolve a dependency from the container
     *
     * @param string $abstract
     * @param array $parameters
     * @return mixed
     * @throws ClassContainerException
     */
    protected function resolve(string $abstract, array $parameters = []): mixed
    {
        $this->currentDepth++;
        $this->guardAgainstMaxRecursionDepth();

        try {
            // Check for circular dependency
            if (isset($this->resolving[$abstract])) {
                throw new CircularDependencyException(
                    sprintf('Circular dependency detected while resolving "%s"', $abstract)
                );
            }

            // Check for shared instance first
            if (isset($this->instances[$abstract])) {
                $this->currentDepth--;
                return $this->instances[$abstract];
            }

            // Mark as currently resolving
            $this->resolving[$abstract] = true;

            $object = $this->build($abstract, $parameters);

            // If it's a shared binding, store the instance
            if (isset($this->bindings[$abstract]) && $this->bindings[$abstract]['shared']) {
                $this->instances[$abstract] = $object;
            }

            return $object;
        } catch (CircularDependencyException $e) {
            throw $e;
        } catch (HighLevelException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ClassContainerException(
                sprintf('Failed to resolve "%s": %s', $abstract, $e->getMessage()),
                0,
                $e
            );
        } finally {
            unset($this->resolving[$abstract]);
            $this->currentDepth--;
        }
    }

    /**
     * Build a concrete instance
     *
     * @param string $abstract
     * @param array $parameters
     * @return mixed
     * @throws ReflectionException|ClassContainerException
     */
    protected function build(string $abstract, array $parameters = []): mixed
    {
        // If we have a binding, use it
        if (isset($this->bindings[$abstract])) {
            $binding = $this->bindings[$abstract];

            if (is_callable($binding['concrete'])) {
                return $binding['concrete']($this, $parameters);
            }

            $abstract = $binding['concrete'];
        }

        // If not a class, check if it's an alias to a class
        if (!class_exists($abstract) && !interface_exists($abstract)) {
            throw new NotFoundException(
                sprintf('Class or interface "%s" does not exist', $abstract)
            );
        }

        // For interfaces, check if there's a binding
        if (interface_exists($abstract) && !isset($this->bindings[$abstract])) {
            throw new ClassContainerException(
                sprintf('Cannot instantiate interface "%s" without binding', $abstract)
            );
        }

        $reflection = $this->reflectClass($abstract);

        // Check if class is instantiable
        if (!$reflection->isInstantiable()) {
            throw new ClassContainerException(
                sprintf('Class "%s" is not instantiable', $abstract)
            );
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $dependencies = $this->resolveDependencies(
            $constructor->getParameters(),
            $parameters
        );

        return $reflection->newInstanceArgs($dependencies);
    }

    protected function reflectClass(string $class): ReflectionClass
    {
        return $this->classRefCache[$class]
            ??= new ReflectionClass($class);
    }

    /**
     * Resolve method/function dependencies
     *
     * @param ReflectionParameter[] $parameters
     * @param array $provided
     * @return array
     * @throws ClassContainerException
     */
    protected function resolveDependencies(array $parameters, array $provided = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependency = $this->resolveParameter($parameter, $provided);

            if ($dependency instanceof UnresolvedParameter) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } elseif ($parameter->allowsNull()) {
                    $dependencies[] = null;
                } else {
                    throw new ClassContainerException(
                        sprintf(
                            'Unable to resolve dependency "%s" of type "%s"',
                            $parameter->getName(),
                            $parameter->getType()?->getName() ?? 'mixed'
                        )
                    );
                }
            } else {
                $dependencies[] = $dependency;
            }
        }

        return $dependencies;
    }

    /**
     * Resolve a single parameter
     *
     * @param ReflectionParameter $parameter
     * @param array $provided
     * @return mixed|UnresolvedParameter
     * @throws ClassContainerException
     */
    protected function resolveParameter(ReflectionParameter $parameter, array $provided): mixed
    {
        $name = $parameter->getName();
        $type = $parameter->getType();

        // Check if explicitly provided
        if (array_key_exists($name, $provided)) {
            return $provided[$name];
        }

        // Check if provided by type
        if ($type && !$type->isBuiltin()) {
            $typeName = $type->getName();

            // Check for variadic parameters
            if ($parameter->isVariadic()) {
                throw new ClassContainerException(
                    'Variadic dependency injection is not supported'
                );
            }

            try {
                return $this->resolve($typeName);
            } catch (NotFoundException $e) {
                return new UnresolvedParameter();
            }
        }

        // Check if it's a class and resolve it by name
        if (class_exists($name) || interface_exists($name)) {
            try {
                return $this->resolve($name);
            } catch (NotFoundException $e) {
                return new UnresolvedParameter();
            }
        }

        return new UnresolvedParameter();
    }

    /**
     * Guard against infinite recursion
     *
     * @throws ClassContainerException
     */
    protected function guardAgainstMaxRecursionDepth(): void
    {
        if ($this->currentDepth > $this->maxRecursionDepth) {
            throw new ClassContainerException(
                sprintf(
                    'Maximum recursion depth of %d exceeded. Possible circular dependency.',
                    $this->maxRecursionDepth
                )
            );
        }
    }

    /**
     * Set maximum recursion depth
     *
     * @param int $depth
     * @return void
     */
    public function setMaxRecursionDepth(int $depth): void
    {
        $this->maxRecursionDepth = $depth;
    }

    /**
     * Clear all bindings and instances
     *
     * @return void
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->resolving = [];
        $this->singletonResolving = [];
        $this->currentDepth = 0;
    }

    /**
     * Get all registered bindings
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Get all resolved instances
     *
     * @return array
     */
    public function getInstances(): array
    {
        return $this->instances;
    }
}

class ClassContainerException extends LowLevelException {}

class NotFoundException extends ClassContainerException {}

class CircularDependencyException extends ClassContainerException {}

/**
 * Internal marker class for unresolved parameters
 */
class UnresolvedParameter extends LowLevelException {}
