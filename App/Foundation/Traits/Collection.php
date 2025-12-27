<?php

namespace App\Foundation\Traits;

use RuntimeException;
use stdClass;
use Traversable;
use InvalidArgumentException;
use JsonSerializable;
use Countable;
use IteratorAggregate;
use ArrayAccess;

trait Collection
{
    private mixed $collectionItems;
    private string $collectionValueType;
    private bool $collectionIsMultiDimensional = false;

    // ==================== INITIALIZATION ====================

    private function initCollection(mixed $items = []): void
    {
        $this->collectionItems = $items;
        $this->analyzeCollectionValueType();
    }

    private function analyzeCollectionValueType(): void
    {
        if (is_array($this->collectionItems)) {
            if (empty($this->collectionItems)) {
                $this->collectionValueType = 'mixed';
                $this->collectionIsMultiDimensional = false;
                return;
            }

            $firstKey = array_key_first($this->collectionItems);
            $firstValue = $this->collectionItems[$firstKey];

            // Check if it's a collection of objects/arrays
            if (is_object($firstValue) || is_array($firstValue)) {
                $this->collectionIsMultiDimensional = true;

                // Determine common type
                $types = [];
                foreach ($this->collectionItems as $item) {
                    if (is_object($item)) {
                        $types[] = get_class($item);
                    } elseif (is_array($item)) {
                        $types[] = 'array';
                    } else {
                        $types[] = gettype($item);
                    }
                }

                $uniqueTypes = array_unique($types);
                $this->collectionValueType = count($uniqueTypes) === 1 ? $uniqueTypes[0] : 'mixed';
            } else {
                $this->collectionValueType = gettype($firstValue);
                $this->collectionIsMultiDimensional = false;
            }
        } else {
            $this->collectionValueType = gettype($this->collectionItems);
            $this->collectionIsMultiDimensional = false;
        }
    }

    private function getCollectionValueType(): string
    {
        return $this->collectionValueType;
    }

    private function isCollectionMultiDimensional(): bool
    {
        return $this->collectionIsMultiDimensional;
    }

    // ==================== MAGIC METHODS ====================

    /**
     * Magic getter for accessing collection items
     */
    public function magicCollectionGet(string $name): mixed
    {
        if (isset($this->collectionItems->{$name})) {
            return $this->collectionItems->{$name};
        }
        
        if (is_array($this->collectionItems) && isset($this->collectionItems[$name])) {
            return $this->collectionItems[$name];
        }
        
        if (is_object($this->collectionItems) && method_exists($this->collectionItems, '__get')) {
            return $this->collectionItems->{$name};
        }
        
        // Try to get from collectionItems if it's an object with properties
        if (is_object($this->collectionItems) && property_exists($this->collectionItems, $name)) {
            return $this->collectionItems->{$name};
        }
        
        throw new RuntimeException("Property {$name} does not exist in collection.");
    }

    /**
     * Magic setter for setting collection items
     */
    public function magicCollectionSet(string $name, mixed $value): void
    {
        if ($this->collectionItems instanceof stdClass) {
            $this->collectionItems->{$name} = $value;
            $this->analyzeCollectionValueType();
            return;
        }
        
        if (is_array($this->collectionItems)) {
            $this->collectionItems[$name] = $value;
            $this->analyzeCollectionValueType();
            return;
        }
        
        if (is_object($this->collectionItems) && method_exists($this->collectionItems, '__set')) {
            $this->collectionItems->{$name} = $value;
            $this->analyzeCollectionValueType();
            return;
        }
        
        // If collectionItems is not array or stdClass, convert it to array
        if (!is_array($this->collectionItems)) {
            $this->collectionItems = [$this->collectionItems];
        }
        
        $this->collectionItems[$name] = $value;
        $this->analyzeCollectionValueType();
    }

    /**
     * Magic isset for checking collection items
     */
    public function collectionIsset(string $name): bool
    {
        if ($this->collectionItems instanceof stdClass) {
            return isset($this->collectionItems->{$name});
        }
        
        if (is_array($this->collectionItems)) {
            return isset($this->collectionItems[$name]);
        }
        
        if (is_object($this->collectionItems)) {
            return isset($this->collectionItems->{$name});
        }
        
        return false;
    }

    /**
     * Magic unset for removing collection items
     */
    public function collectionUnset(string $name): void
    {
        if ($this->collectionItems instanceof stdClass && isset($this->collectionItems->{$name})) {
            unset($this->collectionItems->{$name});
            $this->analyzeCollectionValueType();
            return;
        }
        
        if (is_array($this->collectionItems) && isset($this->collectionItems[$name])) {
            unset($this->collectionItems[$name]);
            $this->analyzeCollectionValueType();
            return;
        }
        
        if (is_object($this->collectionItems) && isset($this->collectionItems->{$name})) {
            unset($this->collectionItems->{$name});
            $this->analyzeCollectionValueType();
            return;
        }
    }

    // ==================== CORE METHODS ====================

    public function collectionMake(mixed $items = []): self
    {
        $instance = new static();
        $instance->initCollection($items);
        return $instance;
    }

    public static function collectionRange($start, $end, $step = 1): self
    {
        $instance = new static();
        $instance->initCollection(range($start, $end, $step));
        return $instance;
    }

    public static function collectionTimes(int $number, ?callable $callback = null): self
    {
        $instance = new static();
        
        if ($callback === null) {
            $instance->initCollection(range(1, $number));
        } else {
            $instance->initCollection(array_map($callback, range(1, $number)));
        }
        
        return $instance;
    }

    public function collectionAll(): mixed
    {
        return $this->collectionItems;
    }

    public function collectionToArray(): array
    {
        if (is_array($this->collectionItems)) {
            return $this->collectionItems;
        }

        // Convert single value to array
        return [$this->collectionItems];
    }

    public function collectionToJson(int $options = 0, int $depth = 512): string
    {
        return json_encode($this->collectionJsonSerialize(), $options, $depth);
    }

    public function collectionCount(): int
    {
        if (is_array($this->collectionItems)) {
            return count($this->collectionItems);
        }

        // Single value counts as 1
        return $this->collectionItems !== null ? 1 : 0;
    }

    public function collectionIsEmpty(): bool
    {
        if (is_array($this->collectionItems)) {
            return empty($this->collectionItems);
        }

        return $this->collectionItems === null || $this->collectionItems === '';
    }

    public function collectionIsNotEmpty(): bool
    {
        return !$this->collectionIsEmpty();
    }

    public function collectionJsonSerialize(): mixed
    {
        return $this->collectionItems;
    }

    public function collectionGetIterator(): Traversable
    {
        if (is_array($this->collectionItems)) {
            return new \ArrayIterator($this->collectionItems);
        }

        // Wrap single value in array for iteration
        return new \ArrayIterator([$this->collectionItems]);
    }

    public function collectionOffsetExists(mixed $offset): bool
    {
        if (is_array($this->collectionItems)) {
            return isset($this->collectionItems[$offset]);
        }

        // For single values, only offset 0 exists
        return $offset === 0;
    }

    public function collectionOffsetGet(mixed $offset): mixed
    {
        if (is_array($this->collectionItems)) {
            return $this->collectionItems[$offset] ?? null;
        }

        // For single values, return the value for offset 0
        return $offset === 0 ? $this->collectionItems : null;
    }

    public function collectionOffsetSet(mixed $offset, mixed $value): void
    {
        if (!is_array($this->collectionItems)) {
            // Convert single value to array
            $this->collectionItems = [$this->collectionItems];
        }

        if (is_null($offset)) {
            $this->collectionItems[] = $value;
        } else {
            $this->collectionItems[$offset] = $value;
        }

        $this->analyzeCollectionValueType();
    }

    public function collectionOffsetUnset(mixed $offset): void
    {
        if (is_array($this->collectionItems) && isset($this->collectionItems[$offset])) {
            unset($this->collectionItems[$offset]);
            $this->analyzeCollectionValueType();
        }
    }

    // ==================== FILTERING (TYPE-AWARE) ====================

    public function collectionFilter(callable $callback): self
    {
        if (!is_array($this->collectionItems)) {
            // For single values, apply callback and return if true
            return $callback($this->collectionItems, 0) ? $this->collectionMake($this->collectionItems) : $this->collectionMake([]);
        }

        return $this->collectionMake(array_filter($this->collectionItems, $callback, ARRAY_FILTER_USE_BOTH));
    }

    public function collectionReject(callable $callback): self
    {
        return $this->collectionFilter(fn($item, $key) => !$callback($item, $key));
    }

    public function collectionFirst(?callable $callback = null): mixed
    {
        if (!is_array($this->collectionItems)) {
            return $callback ? ($callback($this->collectionItems, 0) ? $this->collectionItems : null) : $this->collectionItems;
        }

        if ($callback === null) {
            return reset($this->collectionItems);
        }

        foreach ($this->collectionItems as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return null;
    }

    public function collectionFirstOrFail(?callable $callback = null): mixed
    {
        $result = $this->collectionFirst($callback);
        if ($result === null) {
            throw new RuntimeException("Item not found.");
        }
        return $result;
    }

    public function collectionLast(): mixed
    {
        if (!is_array($this->collectionItems)) {
            return $this->collectionItems;
        }

        return end($this->collectionItems);
    }

    public function collectionWhere(string $key, $value): self
    {
        if (!$this->isCollectionMultiDimensional()) {
            // For scalar values, compare the value itself
            return $this->collectionFilter(fn($item) => $item == $value);
        }

        return $this->collectionFilter(fn($item) => $this->getCollectionValue($item, $key) == $value);
    }

    public function collectionWhereStrict(string $key, $value): self
    {
        if (!$this->isCollectionMultiDimensional()) {
            return $this->collectionFilter(fn($item) => $item === $value);
        }

        return $this->collectionFilter(fn($item) => $this->getCollectionValue($item, $key) === $value);
    }

    public function collectionWhereIn(string $key, array $values): self
    {
        if (!$this->isCollectionMultiDimensional()) {
            return $this->collectionFilter(fn($item) => in_array($item, $values));
        }

        return $this->collectionFilter(fn($item) => in_array($this->getCollectionValue($item, $key), $values));
    }

    public function collectionWhereNotIn(string $key, array $values): self
    {
        if (!$this->isCollectionMultiDimensional()) {
            return $this->collectionReject(fn($item) => in_array($item, $values));
        }

        return $this->collectionReject(fn($item) => in_array($this->getCollectionValue($item, $key), $values));
    }

    public function collectionWhereBetween(string $key, array $range): self
    {
        if (!$this->isCollectionMultiDimensional()) {
            return $this->collectionFilter(
                fn($item) => $item >= $range[0] && $item <= $range[1]
            );
        }

        return $this->collectionFilter(
            fn($item) =>
            $this->getCollectionValue($item, $key) >= $range[0] &&
                $this->getCollectionValue($item, $key) <= $range[1]
        );
    }

    public function collectionWhereNotBetween(string $key, array $range): self
    {
        if (!$this->isCollectionMultiDimensional()) {
            return $this->collectionReject(
                fn($item) => $item >= $range[0] && $item <= $range[1]
            );
        }

        return $this->collectionReject(
            fn($item) =>
            $this->getCollectionValue($item, $key) >= $range[0] &&
                $this->getCollectionValue($item, $key) <= $range[1]
        );
    }

    public function collectionWhereNull(string $key): self
    {
        if (!$this->isCollectionMultiDimensional()) {
            return $this->collectionFilter(fn($item) => is_null($item));
        }

        return $this->collectionFilter(fn($item) => is_null($this->getCollectionValue($item, $key)));
    }

    public function collectionWhereNotNull(string $key): self
    {
        if (!$this->isCollectionMultiDimensional()) {
            return $this->collectionFilter(fn($item) => !is_null($item));
        }

        return $this->collectionFilter(fn($item) => !is_null($this->getCollectionValue($item, $key)));
    }

    public function collectionUnique(?string $key = null): self
    {
        if (!is_array($this->collectionItems)) {
            // Single value is always unique
            return $this->collectionMake($this->collectionItems);
        }

        if ($key === null) {
            return $this->collectionMake(array_unique($this->collectionItems));
        }

        if (!$this->isCollectionMultiDimensional()) {
            // For scalar values, use array_unique directly
            return $this->collectionMake(array_unique($this->collectionItems));
        }

        $unique = [];
        $result = [];

        foreach ($this->collectionItems as $item) {
            $value = $this->getCollectionValue($item, $key);
            if (!in_array($value, $unique, true)) {
                $unique[] = $value;
                $result[] = $item;
            }
        }

        return $this->collectionMake($result);
    }

    // ==================== TRANSFORMING (TYPE-AWARE) ====================

    public function collectionMap(callable $callback): self
    {
        if (!is_array($this->collectionItems)) {
            // For single values, apply callback
            return $this->collectionMake($callback($this->collectionItems, 0));
        }

        return $this->collectionMake(array_map($callback, $this->collectionItems, array_keys($this->collectionItems)));
    }

    public function collectionMapInto(string $className): self
    {
        return $this->collectionMap(fn($item) => new $className($item));
    }

    public function collectionTransform(callable $callback): self
    {
        if (!is_array($this->collectionItems)) {
            $this->collectionItems = $callback($this->collectionItems, 0);
        } else {
            $this->collectionItems = array_map($callback, $this->collectionItems);
        }
        $this->analyzeCollectionValueType();
        return $this;
    }

    public function collectionReduce(callable $fn, mixed $initial = null): mixed
    {
        if (!is_array($this->collectionItems)) {
            return $fn($initial, $this->collectionItems, 0);
        }

        return array_reduce($this->collectionItems, $fn, $initial);
    }

    public function collectionPluck(string $value, ?string $key = null): self
    {
        if (!$this->isCollectionMultiDimensional()) {
            return $this->collectionMake([$value => $this->collectionItems]);
        }

        return $this->collectionMake(array_column($this->collectionToArray(), $value, $key));
    }

    public function collectionImplode(string $glue): string
    {
        if (!is_array($this->collectionItems)) {
            return (string) $this->collectionItems;
        }

        return implode($glue, $this->collectionItems);
    }

    public function collectionEach(callable $callback): self
    {
        if (!is_array($this->collectionItems)) {
            $callback($this->collectionItems, 0);
        } else {
            foreach ($this->collectionItems as $key => $item) {
                if ($callback($item, $key) === false) {
                    break;
                }
            }
        }
        return $this;
    }

    public function collectionTap(callable $callback): self
    {
        $callback($this);
        return $this;
    }

    public function collectionPipe(callable $callback): mixed
    {
        return $callback($this);
    }

    /**
     * Convert the collection to an object of the specified class
     */
    public function collectionToObj(?string $className = null, array $options = []): mixed
    {
        $options = array_merge([
            'mode' => 'constructor',
            'args' => []
        ], $options);

        if ($className === null) {
            return $this->convertCollectionToStdClass($options['deep'] ?? true);
        }

        if (!class_exists($className)) {
            throw new InvalidArgumentException("Class {$className} does not exist");
        }

        $modeParts = explode(':', $options['mode']);
        $mode = $modeParts[0];
        $method = $modeParts[1] ?? null;

        switch ($mode) {
            case 'constructor':
                return $this->instantiateCollectionViaConstructor($className, $options['args']);

            case 'property':
                return $this->instantiateCollectionAndSetProperties($className);

            case 'method':
                if ($method === null) {
                    throw new InvalidArgumentException("Method name must be specified with method:mode");
                }
                return $this->instantiateCollectionAndCallMethod($className, $method, $options['args']);

            default:
                throw new InvalidArgumentException("Invalid mode: {$mode}");
        }
    }

    protected function convertCollectionToStdClass(bool $deep = true): stdClass
    {
        if (!$this->isCollectionMultiDimensional()) {
            $object = new stdClass();
            $object->value = $this->collectionItems;
            return $object;
        }

        if (!$deep) {
            return (object)$this->collectionItems;
        }

        $object = new stdClass();
        foreach ($this->collectionItems as $key => $value) {
            if (is_array($value)) {
                $collectionInstance = $this->collectionMake($value);
                $object->$key = $collectionInstance->collectionToObj();
            } else {
                $object->$key = $value;
            }
        }
        return $object;
    }

    protected function instantiateCollectionViaConstructor(string $className, array $additionalArgs = []): object
    {
        $args = array_merge([$this->collectionItems], $additionalArgs);
        return new $className(...$args);
    }

    protected function instantiateCollectionAndSetProperties(string $className): object
    {
        $object = new $className();

        if (!$this->isCollectionMultiDimensional()) {
            $object->value = $this->collectionItems;
            return $object;
        }

        foreach ($this->collectionItems as $key => $value) {
            $object->$key = is_array($value)
                ? $this->collectionMake($value)->collectionToObj()
                : $value;
        }
        return $object;
    }

    protected function instantiateCollectionAndCallMethod(string $className, string $method, array $args = []): object
    {
        $object = new $className();

        if (!method_exists($object, $method)) {
            throw new RuntimeException("Method {$method} does not exist on class {$className}");
        }

        $methodArgs = array_merge([$this->collectionItems], $args);
        $object->$method(...$methodArgs);

        return $object;
    }

    // ==================== SORTING & GROUPING (TYPE-AWARE) ====================

    public function collectionGroupBy(string $key): self
    {
        if (!$this->isCollectionMultiDimensional() || !is_array($this->collectionItems)) {
            // For scalar values, group by value itself
            return $this->collectionMake([$this->collectionItems => [$this->collectionItems]]);
        }

        $grouped = [];
        foreach ($this->collectionItems as $item) {
            $groupKey = $this->getCollectionValue($item, $key);
            $grouped[$groupKey][] = $item;
        }
        return $this->collectionMake($grouped);
    }

    public function collectionSortBy(string $key, bool $ascending = true): self
    {
        if (!is_array($this->collectionItems)) {
            return $this;
        }

        if (!$this->isCollectionMultiDimensional()) {
            // Sort scalar values
            $ascending ? sort($this->collectionItems) : rsort($this->collectionItems);
            return $this;
        }

        usort($this->collectionItems, function ($a, $b) use ($key, $ascending) {
            $valueA = $this->getCollectionValue($a, $key);
            $valueB = $this->getCollectionValue($b, $key);
            return $ascending ? $valueA <=> $valueB : $valueB <=> $valueA;
        });
        return $this;
    }

    public function collectionSortByDesc(string $key): self
    {
        return $this->collectionSortBy($key, false);
    }

    public function collectionSortDesc(): self
    {
        if (is_array($this->collectionItems)) {
            rsort($this->collectionItems);
        }
        return $this;
    }

    public function collectionSortAsc(): self
    {
        if (is_array($this->collectionItems)) {
            sort($this->collectionItems);
        }
        return $this;
    }

    public function collectionReverse(): self
    {
        if (is_array($this->collectionItems)) {
            return $this->collectionMake(array_reverse($this->collectionItems, true));
        }
        return $this;
    }

    public function collectionShuffle(): self
    {
        if (is_array($this->collectionItems)) {
            shuffle($this->collectionItems);
        }
        return $this;
    }

    // ==================== MUTATION (TYPE-AWARE) ====================

    public function collectionPush(mixed $item): self
    {
        if (!is_array($this->collectionItems)) {
            $this->collectionItems = [$this->collectionItems, $item];
        } else {
            $this->collectionItems[] = $item;
        }
        $this->analyzeCollectionValueType();
        return $this;
    }

    public function collectionPrepend(mixed $item): self
    {
        if (!is_array($this->collectionItems)) {
            $this->collectionItems = [$item, $this->collectionItems];
        } else {
            array_unshift($this->collectionItems, $item);
        }
        $this->analyzeCollectionValueType();
        return $this;
    }

    public function collectionPop(): mixed
    {
        if (!is_array($this->collectionItems) || empty($this->collectionItems)) {
            $item = $this->collectionItems;
            $this->collectionItems = [];
            return $item;
        }

        return array_pop($this->collectionItems);
    }

    public function collectionShift(): mixed
    {
        if (!is_array($this->collectionItems) || empty($this->collectionItems)) {
            $item = $this->collectionItems;
            $this->collectionItems = [];
            return $item;
        }

        return array_shift($this->collectionItems);
    }

    public function collectionMerge(array|self $items): self
    {
        $items = $items instanceof self && method_exists($items, 'collectionToArray') 
            ? $items->collectionToArray() 
            : $items;

        if (!is_array($this->collectionItems)) {
            $this->collectionItems = [$this->collectionItems];
        }

        if (!is_array($items)) {
            $items = [$items];
        }

        return $this->collectionMake(array_merge($this->collectionItems, $items));
    }

    public function collectionMergeRecursive(array|self $items): self
    {
        $items = $items instanceof self && method_exists($items, 'collectionToArray') 
            ? $items->collectionToArray() 
            : $items;

        if (!is_array($this->collectionItems)) {
            $this->collectionItems = [$this->collectionItems];
        }

        if (!is_array($items)) {
            $items = [$items];
        }

        return $this->collectionMake(array_merge_recursive($this->collectionItems, $items));
    }

    public function collectionReplace(array|self $items): self
    {
        $items = $items instanceof self && method_exists($items, 'collectionToArray') 
            ? $items->collectionToArray() 
            : $items;

        if (!is_array($this->collectionItems)) {
            $this->collectionItems = [$this->collectionItems];
        }

        if (!is_array($items)) {
            $items = [$items];
        }

        return $this->collectionMake(array_replace($this->collectionItems, $items));
    }

    public function collectionChunk(int $size): self
    {
        if (!is_array($this->collectionItems) || empty($this->collectionItems)) {
            return $this->collectionMake([$this->collectionItems]);
        }

        $chunks = array_chunk($this->collectionItems, $size);
        return $this->collectionMake(array_map(fn($chunk) => $this->collectionMake($chunk), $chunks));
    }

    public function collectionFlatten(): self
    {
        if (!$this->isCollectionMultiDimensional()) {
            return $this;
        }

        $result = [];
        array_walk_recursive($this->collectionToArray(), function ($v) use (&$result) {
            $result[] = $v;
        });
        return $this->collectionMake($result);
    }

    public function collectionFlattenDeep(): self
    {
        if (!$this->isCollectionMultiDimensional()) {
            return $this;
        }

        $result = [];
        $stack = $this->collectionToArray();

        while (!empty($stack)) {
            $current = array_shift($stack);

            if (is_array($current)) {
                array_unshift($stack, ...$current);
            } else {
                $result[] = $current;
            }
        }

        return $this->collectionMake($result);
    }

    public function collectionCollapse(): self
    {
        if (!$this->isCollectionMultiDimensional()) {
            return $this;
        }

        $results = [];

        foreach ($this->collectionToArray() as $values) {
            if ($values instanceof self && method_exists($values, 'collectionToArray')) {
                $values = $values->collectionToArray();
            } elseif (!is_array($values)) {
                continue;
            }

            $results = array_merge($results, $values);
        }

        return $this->collectionMake($results);
    }

    public function collectionZip(array|self $items): self
    {
        $items = $items instanceof self && method_exists($items, 'collectionToArray') 
            ? $items->collectionToArray() 
            : $items;

        if (!is_array($this->collectionItems)) {
            $this->collectionItems = [$this->collectionItems];
        }

        if (!is_array($items)) {
            $items = [$items];
        }

        $zipped = array_map(null, $this->collectionItems, $items);
        return $this->collectionMake($zipped);
    }

    // ==================== KEYED ACCESS (TYPE-AWARE) ====================

    public function collectionGet(int|string $key, mixed $default = null): mixed
    {
        if (is_array($this->collectionItems)) {
            return $this->collectionItems[$key] ?? $default;
        }

        return $key === 0 ? $this->collectionItems : $default;
    }

    public function collectionSet(int|string $key, mixed $value): self
    {
        if (!is_array($this->collectionItems)) {
            if ($key === 0) {
                $this->collectionItems = $value;
            } else {
                $this->collectionItems = [$key => $value];
            }
        } else {
            $this->collectionItems[$key] = $value;
        }

        $this->analyzeCollectionValueType();
        return $this;
    }

    public function collectionHas(int|string $key): bool
    {
        if (is_array($this->collectionItems)) {
            return array_key_exists($key, $this->collectionItems);
        }

        return $key === 0;
    }

    public function collectionForget(int|string $key): self
    {
        if (is_array($this->collectionItems) && isset($this->collectionItems[$key])) {
            unset($this->collectionItems[$key]);
        } elseif ($key === 0) {
            $this->collectionItems = [];
        }

        $this->analyzeCollectionValueType();
        return $this;
    }

    public function collectionOnly(array|string $keys): self
    {
        $keys = (array)$keys;

        if (!$this->isCollectionMultiDimensional()) {
            // For scalar values, include if key is 0
            return in_array(0, $keys) ? $this : $this->collectionMake([]);
        }

        $filtered = [];

        foreach ($this->collectionToArray() as $item) {
            if (is_array($item)) {
                $filtered[] = array_intersect_key($item, array_flip($keys));
            } elseif (is_object($item)) {
                $filteredItem = [];
                foreach ($keys as $key) {
                    if (property_exists($item, $key)) {
                        $filteredItem[$key] = $item->$key;
                    }
                }
                $filtered[] = $filteredItem;
            }
        }

        return $this->collectionMake($filtered);
    }

    public function collectionExcept(array|string $keys): self
    {
        $keys = array_flip((array)$keys);

        if (!$this->isCollectionMultiDimensional()) {
            // For scalar values, exclude if key is 0
            return isset($keys[0]) ? $this->collectionMake([]) : $this;
        }

        $filtered = [];

        foreach ($this->collectionToArray() as $item) {
            if (is_array($item)) {
                $filtered[] = array_diff_key($item, $keys);
            } elseif (is_object($item)) {
                $filteredItem = [];
                foreach ($item as $key => $value) {
                    if (!isset($keys[$key])) {
                        $filteredItem[$key] = $value;
                    }
                }
                $filtered[] = $filteredItem;
            }
        }

        return $this->collectionMake($filtered);
    }

    public function collectionKeyBy(string $key): self
    {
        if (!$this->isCollectionMultiDimensional() || !is_array($this->collectionItems)) {
            // For scalar values, use value as key
            return $this->collectionMake([$this->collectionItems => $this->collectionItems]);
        }

        $keyed = [];
        foreach ($this->collectionItems as $item) {
            $keyed[$this->getCollectionValue($item, $key)] = $item;
        }
        return $this->collectionMake($keyed);
    }

    public function collectionKeys(): self
    {
        if (is_array($this->collectionItems)) {
            return $this->collectionMake(array_keys($this->collectionItems));
        }

        return $this->collectionMake([0]);
    }

    public function collectionValues(): self
    {
        if (is_array($this->collectionItems)) {
            return $this->collectionMake(array_values($this->collectionItems));
        }

        return $this->collectionMake([$this->collectionItems]);
    }

    // ==================== AGGREGATES (TYPE-AWARE) ====================

    public function collectionSum(?string $key = null): float|int
    {
        if (!$this->isCollectionMultiDimensional()) {
            return is_numeric($this->collectionItems) ? $this->collectionItems : 0;
        }

        if ($key === null) {
            return array_sum($this->collectionToArray());
        }

        return $this->collectionReduce(fn($carry, $item) => $carry + $this->getCollectionValue($item, $key), 0);
    }

    public function collectionAvg(?string $key = null): float|int
    {
        $count = $this->collectionCount();
        if ($count === 0) {
            return 0;
        }

        return $this->collectionSum($key) / $count;
    }

    public function collectionMin(?string $key = null): mixed
    {
        if (!$this->isCollectionMultiDimensional()) {
            return $this->collectionItems;
        }

        if ($key === null) {
            return min($this->collectionToArray());
        }

        return $this->collectionReduce(function ($carry, $item) use ($key) {
            $value = $this->getCollectionValue($item, $key);
            return $carry === null || $value < $carry ? $value : $carry;
        });
    }

    public function collectionMax(?string $key = null): mixed
    {
        if (!$this->isCollectionMultiDimensional()) {
            return $this->collectionItems;
        }

        if ($key === null) {
            return max($this->collectionToArray());
        }

        return $this->collectionReduce(function ($carry, $item) use ($key) {
            $value = $this->getCollectionValue($item, $key);
            return $carry === null || $value > $carry ? $value : $carry;
        });
    }

    public function collectionMedian(?string $key = null): float|int
    {
        if (!$this->isCollectionMultiDimensional()) {
            return $this->collectionItems;
        }

        $values = $key ? $this->collectionPluck($key) : $this->collectionToArray();
        sort($values);
        $count = count($values);
        $middle = (int) floor($count / 2);

        if ($count % 2) {
            return $values[$middle];
        }

        return ($values[$middle - 1] + $values[$middle]) / 2;
    }

    public function collectionMode(?string $key = null): array
    {
        if (!$this->isCollectionMultiDimensional()) {
            return [$this->collectionItems];
        }

        $values = $key ? $this->collectionPluck($key) : $this->collectionToArray();
        $frequency = array_count_values($values);
        $maxFrequency = max($frequency);
        return array_keys($frequency, $maxFrequency);
    }

    // ==================== CONDITIONAL (TYPE-AWARE) ====================

    public function collectionContains(mixed $value): bool
    {
        if (is_callable($value)) {
            return $this->collectionFirst($value) !== null;
        }

        if (!is_array($this->collectionItems)) {
            return $this->collectionItems === $value;
        }

        return in_array($value, $this->collectionItems, true);
    }

    public function collectionContainsStrict(mixed $value): bool
    {
        if (!is_array($this->collectionItems)) {
            return $this->collectionItems === $value;
        }

        return in_array($value, $this->collectionItems, true);
    }

    public function collectionDoesntContain(mixed $value): bool
    {
        return !$this->collectionContains($value);
    }

    public function collectionEvery(callable $callback): bool
    {
        if (!is_array($this->collectionItems)) {
            return $callback($this->collectionItems, 0);
        }

        foreach ($this->collectionItems as $key => $item) {
            if (!$callback($item, $key)) {
                return false;
            }
        }
        return true;
    }

    // ==================== HIGHER ORDER ====================

    public function collectionHigherOrderMap(): self
    {
        return $this->collectionMap(function ($item) {
            return function (callable $callback) use ($item) {
                return $callback($item);
            };
        });
    }

    public function collectionHigherOrderFilter(): self
    {
        return $this->collectionMap(function ($item) {
            return function (callable $callback) use ($item) {
                return $callback($item) ? $item : null;
            };
        });
    }

    // ==================== UTILITY (ENHANCED) ====================

    private function getCollectionValue(mixed $item, string $key): mixed
    {
        if (is_array($item)) {
            return $item[$key] ?? null;
        }

        if (is_object($item)) {
            if (property_exists($item, $key)) {
                return $item->$key;
            }

            // Try getter method
            $getter = 'get' . ucfirst($key);
            if (method_exists($item, $getter)) {
                return $item->$getter();
            }

            // Try magic get
            if (method_exists($item, '__get')) {
                return $item->$key;
            }

            return null;
        }

        // For scalar values, return the value itself if key is 'value' or 0
        return ($key === 'value' || $key === 0) ? $item : null;
    }

    public function collectionDump(): self
    {
        var_dump([
            'value' => $this->collectionItems,
            'type' => $this->getCollectionValueType(),
            'is_multi_dimensional' => $this->isCollectionMultiDimensional(),
            'count' => $this->collectionCount()
        ]);
        return $this;
    }

    public function collectionDd(): void
    {
        $this->collectionDump();
        exit;
    }

    public function collectionWhen(mixed $value, callable $callback, ?callable $default = null): self
    {
        if ($value) {
            return $callback($this);
        }

        if ($default) {
            return $default($this);
        }

        return $this;
    }

    public function collectionUnless(mixed $value, callable $callback, ?callable $default = null): self
    {
        return $this->collectionWhen(!$value, $callback, $default);
    }

    public function collectionMacro(string $name, callable $macro): void
    {
        $this->{$name} = $macro->bindTo($this, static::class);
    }

    // ==================== TYPE-SPECIFIC METHODS ====================

    /**
     * Check if collection contains only strings
     */
    public function collectionIsStrings(): bool
    {
        if (!is_array($this->collectionItems)) {
            return is_string($this->collectionItems);
        }

        foreach ($this->collectionItems as $item) {
            if (!is_string($item)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if collection contains only numeric values
     */
    public function collectionIsNumeric(): bool
    {
        if (!is_array($this->collectionItems)) {
            return is_numeric($this->collectionItems);
        }

        foreach ($this->collectionItems as $item) {
            if (!is_numeric($item)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if collection contains only integers
     */
    public function collectionIsIntegers(): bool
    {
        if (!is_array($this->collectionItems)) {
            return is_int($this->collectionItems);
        }

        foreach ($this->collectionItems as $item) {
            if (!is_int($item)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Convert all values to strings
     */
    public function collectionToStrings(): self
    {
        return $this->collectionMap(fn($item) => (string) $item);
    }

    /**
     * Convert all values to integers
     */
    public function collectionToIntegers(): self
    {
        return $this->collectionMap(fn($item) => (int) $item);
    }

    /**
     * Convert all values to floats
     */
    public function collectionToFloats(): self
    {
        return $this->collectionMap(fn($item) => (float) $item);
    }

    /**
     * Get type of each item
     */
    public function collectionTypes(): self
    {
        return $this->collectionMap(fn($item) => gettype($item));
    }
}