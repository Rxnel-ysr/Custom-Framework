<?php

namespace App\Foundation\Support;

use RuntimeException;
use JsonSerializable;
use Countable;
use IteratorAggregate;
use ArrayAccess;
use stdClass;
use Traversable;
use InvalidArgumentException;

class Collection implements JsonSerializable, Countable, IteratorAggregate, ArrayAccess
{
    private mixed $items;
    private string $valueType;
    private bool $isMultiDimensional = false;

    public function __construct(mixed $items = [])
    {
        $this->items = $items;
        $this->analyzeValueType();
    }

    // ==================== TYPE ANALYSIS ====================

    private function analyzeValueType(): void
    {
        if (is_array($this->items)) {
            if (empty($this->items)) {
                $this->valueType = 'mixed';
                $this->isMultiDimensional = false;
                return;
            }

            $firstKey = array_key_first($this->items);
            $firstValue = $this->items[$firstKey];

            // Check if it's a collection of objects/arrays
            if (is_object($firstValue) || is_array($firstValue)) {
                $this->isMultiDimensional = true;

                // Determine common type
                $types = [];
                foreach ($this->items as $item) {
                    if (is_object($item)) {
                        $types[] = get_class($item);
                    } elseif (is_array($item)) {
                        $types[] = 'array';
                    } else {
                        $types[] = gettype($item);
                    }
                }

                $uniqueTypes = array_unique($types);
                $this->valueType = count($uniqueTypes) === 1 ? $uniqueTypes[0] : 'mixed';
            } else {
                $this->valueType = gettype($firstValue);
                $this->isMultiDimensional = false;
            }
        } else {
            $this->valueType = gettype($this->items);
            $this->isMultiDimensional = false;
        }
    }

    private function getValueType(): string
    {
        return $this->valueType;
    }

    private function isMultiDimensional(): bool
    {
        return $this->isMultiDimensional;
    }

    // ==================== CORE ====================

    public static function make(mixed $items = []): self
    {
        return new self($items);
    }

    public static function range($start, $end, $step = 1): self
    {
        return new self(range($start, $end, $step));
    }

    public static function times(int $number, ?callable $callback = null): self
    {
        if ($callback === null) {
            return new self(range(1, $number));
        }

        return new self(array_map($callback, range(1, $number)));
    }

    public function all(): mixed
    {
        return $this->items;
    }

    public function toArray(): array
    {
        if ($this->valueType == 'array') {
            return $this->items;
        }

        if($this->valueType == 'object'){
            return (array) $this->items;
        }

        // Convert single value to array
        return [$this->items];
    }

    public function toArrayWithout(array $keys): array
    {
        if ($this->valueType === 'array') {
            return array_diff_key(
                $this->items,
                array_flip($keys)
            );
        }

        if($this->valueType == 'object'){
            foreach($keys as $key){
                if(isset($this->items->{$key})){
                    unset($this->items->{$key});
                }
            }
        }
        
        return (array) $this->items;
    }


    public function toJson(int $options = 0, int $depth = 512): string
    {
        return json_encode($this->jsonSerialize(), $options, $depth);
    }

    public function count(): int
    {
        if (is_array($this->items)) {
            return count($this->items);
        }

        // Single value counts as 1
        return $this->items !== null ? 1 : 0;
    }

    public function isEmpty(): bool
    {
        if (is_array($this->items)) {
            return empty($this->items);
        }

        return $this->items === null || $this->items === '';
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function jsonSerialize(): mixed
    {
        return $this->items;
    }

    public function getIterator(): Traversable
    {
        if (is_array($this->items)) {
            return new \ArrayIterator($this->items);
        }

        // Wrap single value in array for iteration
        return new \ArrayIterator([$this->items]);
    }

    public function offsetExists(mixed $offset): bool
    {
        if (is_array($this->items)) {
            return isset($this->items[$offset]);
        }

        // For single values, only offset 0 exists
        return $offset === 0;
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (is_array($this->items)) {
            return $this->items[$offset] ?? null;
        }

        // For single values, return the value for offset 0
        return $offset === 0 ? $this->items : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_array($this->items)) {
            // Convert single value to array
            $this->items = [$this->items];
        }

        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }

        $this->analyzeValueType();
    }

    public function offsetUnset(mixed $offset): void
    {
        if (is_array($this->items) && isset($this->items[$offset])) {
            unset($this->items[$offset]);
            $this->analyzeValueType();
        }
    }

    // ==================== FILTERING (TYPE-AWARE) ====================

    public function filter(?callable $callback = null, bool $adjustKey = false): self
    {
        $wrapper = $adjustKey ? fn($values) => array_values($values) : fn($value) => $value; 
        
        if(is_null($callback)){
            return new self($wrapper(array_filter($this->items, fn($i) => !empty($i), ARRAY_FILTER_USE_BOTH)));
        }
        if (!is_array($this->items)) {
            // For single values, apply callback and return if true
            return $callback($this->items, 0) ? new self($this->items) : new self([]);
        }

        return new self($wrapper(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH)));
    }

    public function reject(callable $callback, bool $adjustKey = false): self
    {
        return $this->filter(fn($item, $key) => !$callback($item, $key), $adjustKey);
    }

    public function first(?callable $callback = null): mixed
    {
        if (!is_array($this->items)) {
            return $callback ? ($callback($this->items, 0) ? $this->items : null) : $this->items;
        }

        if ($callback === null) {
            return reset($this->items);
        }

        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return null;
    }

    public function firstOrFail(?callable $callback = null): mixed
    {
        $result = $this->first($callback);
        if ($result === null) {
            throw new RuntimeException("Item not found.");
        }
        return $result;
    }

    public function last(): mixed
    {
        if (!is_array($this->items)) {
            return $this->items;
        }

        return end($this->items);
    }

    public function where(string $key, $value): self
    {
        if (!$this->isMultiDimensional()) {
            // For scalar values, compare the value itself
            return $this->filter(fn($item) => $item == $value);
        }

        return $this->filter(fn($item) => $this->getValue($item, $key) == $value);
    }

    public function whereStrict(string $key, $value): self
    {
        if (!$this->isMultiDimensional()) {
            return $this->filter(fn($item) => $item === $value);
        }

        return $this->filter(fn($item) => $this->getValue($item, $key) === $value);
    }

    public function whereIn(string $key, array $values): self
    {
        if (!$this->isMultiDimensional()) {
            return $this->filter(fn($item) => in_array($item, $values));
        }

        return $this->filter(fn($item) => in_array($this->getValue($item, $key), $values));
    }

    public function whereNotIn(string $key, array $values): self
    {
        if (!$this->isMultiDimensional()) {
            return $this->reject(fn($item) => in_array($item, $values));
        }

        return $this->reject(fn($item) => in_array($this->getValue($item, $key), $values));
    }

    public function whereBetween(string $key, array $range): self
    {
        if (!$this->isMultiDimensional()) {
            return $this->filter(
                fn($item) => $item >= $range[0] && $item <= $range[1]
            );
        }

        return $this->filter(
            fn($item) =>
            $this->getValue($item, $key) >= $range[0] &&
                $this->getValue($item, $key) <= $range[1]
        );
    }

    public function whereNotBetween(string $key, array $range): self
    {
        if (!$this->isMultiDimensional()) {
            return $this->reject(
                fn($item) => $item >= $range[0] && $item <= $range[1]
            );
        }

        return $this->reject(
            fn($item) =>
            $this->getValue($item, $key) >= $range[0] &&
                $this->getValue($item, $key) <= $range[1]
        );
    }

    public function whereNull(string $key): self
    {
        if (!$this->isMultiDimensional()) {
            return $this->filter(fn($item) => is_null($item));
        }

        return $this->filter(fn($item) => is_null($this->getValue($item, $key)));
    }

    public function whereNotNull(string $key): self
    {
        if (!$this->isMultiDimensional()) {
            return $this->filter(fn($item) => !is_null($item));
        }

        return $this->filter(fn($item) => !is_null($this->getValue($item, $key)));
    }

    public function unique(?string $key = null): self
    {
        if (!is_array($this->items)) {
            // Single value is always unique
            return new self($this->items);
        }

        if ($key === null) {
            return new self(array_unique($this->items));
        }

        if (!$this->isMultiDimensional()) {
            // For scalar values, use array_unique directly
            return new self(array_unique($this->items));
        }

        $unique = [];
        $result = [];

        foreach ($this->items as $item) {
            $value = $this->getValue($item, $key);
            if (!in_array($value, $unique, true)) {
                $unique[] = $value;
                $result[] = $item;
            }
        }

        return new self($result);
    }

    // ==================== TRANSFORMING (TYPE-AWARE) ====================

    public function map(callable $callback): self
    {
        if (!is_array($this->items)) {
            // For single values, apply callback
            return new self($callback($this->items, 0));
        }

        return new self(array_map($callback, $this->items, array_keys($this->items)));
    }

    public function mapInto(string $className): self
    {
        return $this->map(fn($item) => new $className($item));
    }

    public function transform(callable $callback): self
    {
        if (!is_array($this->items)) {
            $this->items = $callback($this->items, 0);
        } else {
            $this->items = array_map($callback, $this->items);
        }
        $this->analyzeValueType();
        return $this;
    }

    public function reduce(callable $fn, mixed $initial = null): mixed
    {
        if (!is_array($this->items)) {
            return $fn($initial, $this->items, 0);
        }

        return array_reduce($this->items, $fn, $initial);
    }

    public function pluck(string $value, ?string $key = null): self
    {
        if (!$this->isMultiDimensional()) {
            return $this->make([$value => $this->items]);
        }

        return $this->make(array_column($this->toArray(), $value, $key));
    }

    public function implode(string $glue): string
    {
        if (!is_array($this->items)) {
            return (string) $this->items;
        }

        return implode($glue, $this->items);
    }

    public function each(callable $callback): self
    {
        if (!is_array($this->items)) {
            $callback($this->items, 0);
        } else {
            foreach ($this->items as $key => $item) {
                if ($callback($item, $key) === false) {
                    break;
                }
            }
        }
        return $this;
    }

    public function tap(callable $callback): self
    {
        $callback(new self($this->items));
        return $this;
    }

    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    /**
     * Convert the collection to an object of the specified class
     */
    public function toObj(?string $className = null, array $options = []): mixed
    {
        $options = array_merge([
            'mode' => 'constructor',
            'args' => []
        ], $options);

        if ($className === null) {
            return $this->convertToStdClass($options['deep'] ?? true);
        }

        if (!class_exists($className)) {
            throw new InvalidArgumentException("Class {$className} does not exist");
        }

        $modeParts = explode(':', $options['mode']);
        $mode = $modeParts[0];
        $method = $modeParts[1] ?? null;

        switch ($mode) {
            case 'constructor':
                return $this->instantiateViaConstructor($className, $options['args']);

            case 'property':
                return $this->instantiateAndSetProperties($className);

            case 'method':
                if ($method === null) {
                    throw new InvalidArgumentException("Method name must be specified with method:mode");
                }
                return $this->instantiateAndCallMethod($className, $method, $options['args']);

            default:
                throw new InvalidArgumentException("Invalid mode: {$mode}");
        }
    }

    protected function convertToStdClass(bool $deep = true): stdClass
    {
        if (!$this->isMultiDimensional()) {
            $object = new stdClass();
            $object->value = $this->items;
            return $object;
        }

        if (!$deep) {
            return (object)$this->items;
        }

        $object = new stdClass();
        foreach ($this->items as $key => $value) {
            if (is_array($value)) {
                $object->$key = (new self($value))->toObj();
            } else {
                $object->$key = $value;
            }
        }
        return $object;
    }

    protected function instantiateViaConstructor(string $className, array $additionalArgs = []): object
    {
        $args = array_merge([$this->items], $additionalArgs);
        return new $className(...$args);
    }

    protected function instantiateAndSetProperties(string $className): object
    {
        $object = new $className();

        if (!$this->isMultiDimensional()) {
            $object->value = $this->items;
            return $object;
        }

        foreach ($this->items as $key => $value) {
            $object->$key = is_array($value)
                ? (new self($value))->toObj()
                : $value;
        }
        return $object;
    }

    protected function instantiateAndCallMethod(string $className, string $method, array $args = []): object
    {
        $object = new $className();

        if (!method_exists($object, $method)) {
            throw new RuntimeException("Method {$method} does not exist on class {$className}");
        }

        $methodArgs = array_merge([$this->items], $args);
        $object->$method(...$methodArgs);

        return $object;
    }

    // ==================== SORTING & GROUPING (TYPE-AWARE) ====================

    public function groupBy(string $key): self
    {
        if (!$this->isMultiDimensional() || !is_array($this->items)) {
            // For scalar values, group by value itself
            return new self([$this->items => [$this->items]]);
        }

        $grouped = [];
        foreach ($this->items as $item) {
            $groupKey = $this->getValue($item, $key);
            $grouped[$groupKey][] = $item;
        }
        return new self($grouped);
    }

    public function sortBy(string $key, bool $ascending = true): self
    {
        if (!is_array($this->items)) {
            return $this;
        }

        if (!$this->isMultiDimensional()) {
            // Sort scalar values
            $ascending ? sort($this->items) : rsort($this->items);
            return $this;
        }

        usort($this->items, function ($a, $b) use ($key, $ascending) {
            $valueA = $this->getValue($a, $key);
            $valueB = $this->getValue($b, $key);
            return $ascending ? $valueA <=> $valueB : $valueB <=> $valueA;
        });
        return $this;
    }

    public function sortByDesc(string $key): self
    {
        return $this->sortBy($key, false);
    }

    public function sortDesc(): self
    {
        if (is_array($this->items)) {
            rsort($this->items);
        }
        return $this;
    }

    public function sortAsc(): self
    {
        if (is_array($this->items)) {
            sort($this->items);
        }
        return $this;
    }

    public function reverse(): self
    {
        if (is_array($this->items)) {
            return new self(array_reverse($this->items, true));
        }
        return $this;
    }

    public function shuffle(): self
    {
        if (is_array($this->items)) {
            shuffle($this->items);
        }
        return $this;
    }

    // ==================== MUTATION (TYPE-AWARE) ====================

    public function push(mixed $item): self
    {
        if (!is_array($this->items)) {
            $this->items = [$this->items, $item];
        } else {
            $this->items[] = $item;
        }
        $this->analyzeValueType();
        return $this;
    }

    public function prepend(mixed $item): self
    {
        if (!is_array($this->items)) {
            $this->items = [$item, $this->items];
        } else {
            array_unshift($this->items, $item);
        }
        $this->analyzeValueType();
        return $this;
    }

    public function pop(): mixed
    {
        if (!is_array($this->items) || empty($this->items)) {
            $item = $this->items;
            $this->items = [];
            return $item;
        }

        return array_pop($this->items);
    }

    public function shift(): mixed
    {
        if (!is_array($this->items) || empty($this->items)) {
            $item = $this->items;
            $this->items = [];
            return $item;
        }

        return array_shift($this->items);
    }

    public function merge(array|self $items): self
    {
        $items = $items instanceof self ? $items->toArray() : $items;

        if (!is_array($this->items)) {
            $this->items = [$this->items];
        }

        if (!is_array($items)) {
            $items = [$items];
        }

        return new self(array_merge($this->items, $items));
    }

    public function mergeRecursive(array|self $items): self
    {
        $items = $items instanceof self ? $items->toArray() : $items;

        if (!is_array($this->items)) {
            $this->items = [$this->items];
        }

        if (!is_array($items)) {
            $items = [$items];
        }

        return new self(array_merge_recursive($this->items, $items));
    }

    public function replace(array|self $items): self
    {
        $items = $items instanceof self ? $items->toArray() : $items;

        if (!is_array($this->items)) {
            $this->items = [$this->items];
        }

        if (!is_array($items)) {
            $items = [$items];
        }

        return new self(array_replace($this->items, $items));
    }

    public function chunk(int $size): self
    {
        if (!is_array($this->items) || empty($this->items)) {
            return new self([$this->items]);
        }

        $chunks = array_chunk($this->items, $size);
        return new self(array_map(fn($chunk) => new self($chunk), $chunks));
    }

    public function flatten(): self
    {
        if (!$this->isMultiDimensional()) {
            return $this;
        }

        $result = [];
        array_walk_recursive($this->toArray(), function ($v) use (&$result) {
            $result[] = $v;
        });
        return new self($result);
    }

    public function flattenDeep(): self
    {
        if (!$this->isMultiDimensional()) {
            return $this;
        }

        $result = [];
        $stack = $this->toArray();

        while (!empty($stack)) {
            $current = array_shift($stack);

            if (is_array($current)) {
                array_unshift($stack, ...$current);
            } else {
                $result[] = $current;
            }
        }

        return new self($result);
    }

    public function collapse(): self
    {
        if (!$this->isMultiDimensional()) {
            return $this;
        }

        $results = [];

        foreach ($this->toArray() as $values) {
            if ($values instanceof self) {
                $values = $values->toArray();
            } elseif (!is_array($values)) {
                continue;
            }

            $results = array_merge($results, $values);
        }

        return new self($results);
    }

    public function zip(array|self $items): self
    {
        $items = $items instanceof self ? $items->toArray() : $items;

        if (!is_array($this->items)) {
            $this->items = [$this->items];
        }

        if (!is_array($items)) {
            $items = [$items];
        }

        $zipped = array_map(null, $this->items, $items);
        return new self($zipped);
    }

    // ==================== KEYED ACCESS (TYPE-AWARE) ====================

    public function get(int|string $key, mixed $default = null): mixed
    {
        if ($this->valueType == 'array') {
            return $this->items[$key] ?? $default;
        }
        if($this->valueType == 'object'){
            return $this->items->{$key} ?? $default;
        }

        return $key === 0 ? $this->items : $default;
    }

    public function set(int|string $key, mixed $value): self
    {
        if (!is_array($this->items)) {
            if ($key === 0) {
                $this->items = $value;
            } else {
                $this->items->{$key} = $value;
            }
        } else {
            $this->items[$key] = $value;
        }

        $this->analyzeValueType();
        return $this;
    }

    public function has(int|string $key): bool
    {
        if (is_array($this->items)) {
            return array_key_exists($key, $this->items);
        }

        return $key === 0;
    }

    public function forget(int|string $key): self
    {
        if (is_array($this->items) && isset($this->items[$key])) {
            unset($this->items[$key]);
        } elseif ($key === 0) {
            $this->items = [];
        }

        $this->analyzeValueType();
        return $this;
    }

    public function only(array|string $keys): self
    {
        $keys = (array)$keys;

        if (!$this->isMultiDimensional()) {
            // For scalar values, include if key is 0
            return in_array(0, $keys) ? $this : new self([]);
        }

        $filtered = [];

        foreach ($this->toArray() as $item) {
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

        return new self($filtered);
    }

    public function except(array|string $keys): self
    {
        $keys = array_flip((array)$keys);

        if (!$this->isMultiDimensional()) {
            // For scalar values, exclude if key is 0
            return isset($keys[0]) ? new self([]) : $this;
        }

        $filtered = [];

        foreach ($this->toArray() as $item) {
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

        return new self($filtered);
    }

    public function keyBy(string $key): self
    {
        if (!$this->isMultiDimensional() || !is_array($this->items)) {
            // For scalar values, use value as key
            return new self([$this->items => $this->items]);
        }

        $keyed = [];
        foreach ($this->items as $item) {
            $keyed[$this->getValue($item, $key)] = $item;
        }
        return new self($keyed);
    }

    public function keys(): self
    {
        if (is_array($this->items)) {
            return new self(array_keys($this->items));
        }

        return new self([0]);
    }

    public function values(): self
    {
        if (is_array($this->items)) {
            return new self(array_values($this->items));
        }

        return new self([$this->items]);
    }

    // ==================== AGGREGATES (TYPE-AWARE) ====================

    public function sum(?string $key = null): float|int
    {
        if (!$this->isMultiDimensional()) {
            return is_numeric($this->items) ? $this->items : 0;
        }

        if ($key === null) {
            return array_sum($this->toArray());
        }

        return $this->reduce(fn($carry, $item) => $carry + $this->getValue($item, $key), 0);
    }

    public function avg(?string $key = null): float|int
    {
        $count = $this->count();
        if ($count === 0) {
            return 0;
        }

        return $this->sum($key) / $count;
    }

    public function min(?string $key = null): mixed
    {
        if (!$this->isMultiDimensional()) {
            return $this->items;
        }

        if ($key === null) {
            return min($this->toArray());
        }

        return $this->reduce(function ($carry, $item) use ($key) {
            $value = $this->getValue($item, $key);
            return $carry === null || $value < $carry ? $value : $carry;
        });
    }

    public function max(?string $key = null): mixed
    {
        if (!$this->isMultiDimensional()) {
            return $this->items;
        }

        if ($key === null) {
            return max($this->toArray());
        }

        return $this->reduce(function ($carry, $item) use ($key) {
            $value = $this->getValue($item, $key);
            return $carry === null || $value > $carry ? $value : $carry;
        });
    }

    public function median(?string $key = null): float|int
    {
        if (!$this->isMultiDimensional()) {
            return $this->items;
        }

        $values = $key ? $this->pluck($key) : $this->toArray();
        sort($values);
        $count = count($values);
        $middle = (int) floor($count / 2);

        if ($count % 2) {
            return $values[$middle];
        }

        return ($values[$middle - 1] + $values[$middle]) / 2;
    }

    public function mode(?string $key = null): array
    {
        if (!$this->isMultiDimensional()) {
            return [$this->items];
        }

        $values = $key ? $this->pluck($key) : $this->toArray();
        $frequency = array_count_values($values);
        $maxFrequency = max($frequency);
        return array_keys($frequency, $maxFrequency);
    }

    // ==================== CONDITIONAL (TYPE-AWARE) ====================

    public function contains(mixed $value): bool
    {
        if (is_callable($value)) {
            return $this->first($value) !== null;
        }

        if (!is_array($this->items)) {
            return $this->items === $value;
        }

        return in_array($value, $this->items, true);
    }

    public function containsStrict(mixed $value): bool
    {
        if (!is_array($this->items)) {
            return $this->items === $value;
        }

        return in_array($value, $this->items, true);
    }

    public function doesntContain(mixed $value): bool
    {
        return !$this->contains($value);
    }

    public function every(callable $callback): bool
    {
        if (!is_array($this->items)) {
            return $callback($this->items, 0);
        }

        foreach ($this->items as $key => $item) {
            if (!$callback($item, $key)) {
                return false;
            }
        }
        return true;
    }

    // ==================== HIGHER ORDER ====================

    public function higherOrderMap(): self
    {
        return $this->map(function ($item) {
            return function (callable $callback) use ($item) {
                return $callback($item);
            };
        });
    }

    public function higherOrderFilter(): self
    {
        return $this->map(function ($item) {
            return function (callable $callback) use ($item) {
                return $callback($item) ? $item : null;
            };
        });
    }

    // ==================== UTILITY (ENHANCED) ====================

    private function getValue(mixed $item, string $key): mixed
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

    public function dump(): self
    {
        var_dump([
            'value' => $this->items,
            'type' => $this->getValueType(),
            'is_multi_dimensional' => $this->isMultiDimensional(),
            'count' => $this->count()
        ]);
        return $this;
    }

    public function dd(): void
    {
        $this->dump();
        exit;
    }

    public function when(mixed $value, callable $callback, ?callable $default = null): self
    {
        if ($value) {
            return $callback($this);
        }

        if ($default) {
            return $default($this);
        }

        return $this;
    }

    public function unless(mixed $value, callable $callback, ?callable $default = null): self
    {
        return $this->when(!$value, $callback, $default);
    }

    public function macro(string $name, callable $macro): void
    {
        $this->{$name} = $macro->bindTo($this, self::class);
    }

    public function __call(string $method, array $parameters)
    {
        if (isset($this->{$method}) && is_callable($this->{$method})) {
            return call_user_func_array($this->{$method}, $parameters);
        }

        throw new RuntimeException("Method {$method} does not exist.");
    }

    // ==================== TYPE-SPECIFIC METHODS ====================

    /**
     * Check if collection contains only strings
     */
    public function isStrings(): bool
    {
        if (!is_array($this->items)) {
            return is_string($this->items);
        }

        foreach ($this->items as $item) {
            if (!is_string($item)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if collection contains only numeric values
     */
    public function isNumeric(): bool
    {
        if (!is_array($this->items)) {
            return is_numeric($this->items);
        }

        foreach ($this->items as $item) {
            if (!is_numeric($item)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if collection contains only integers
     */
    public function isIntegers(): bool
    {
        if (!is_array($this->items)) {
            return is_int($this->items);
        }

        foreach ($this->items as $item) {
            if (!is_int($item)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Convert all values to strings
     */
    public function toStrings(): self
    {
        return $this->map(fn($item) => (string) $item);
    }

    /**
     * Convert all values to integers
     */
    public function toIntegers(): self
    {
        return $this->map(fn($item) => (int) $item);
    }

    /**
     * Convert all values to floats
     */
    public function toFloats(): self
    {
        return $this->map(fn($item) => (float) $item);
    }

    /**
     * Get type of each item
     */
    public function types(): self
    {
        return $this->map(fn($item) => gettype($item));
    }

    public function __get($name)
    {
        return ($this->items instanceof stdClass ? $this->items->{$name}  : $this->items[$name]);
    }

    public function __toString()
    {
        return ($this->valueType == 'object' || $this->valueType == 'array') ? json_encode($this->items) : $this->item;
    }
}
/**
 * 
 * Karya Ilmiah
 * ---
 * 
 * Angket, wawancara, literatur
 * 
 * Kerangka teori adalah buku-buku literatur yang berisi pendukung teori atau referenci
 * 
 * hipotesis, adalah kesimpulan awal, yang
 * pendahuluan: latar belakanag(alasan emngapa kalian meimilih judul)
 * 
 * abstrak, letak didepan, adalah ringkasan dari materi yang akan kamu bahas
 * 
 * biasanya kertas A4, font 12 Times New Roman, 1.5 margin
 * 
 * spasi line di asbtrak itu 1, tidak terlalu banyak hanya 2-3 paragrah
 * dan jika di pembahasan lainnya 1.5 jaraknya
 * 
 * penutup = kesimpulan keseluruhan + saran
 * 
 * 
 * daftar 
 * 
 * lampiran, isi: biodata, foto, tabel pembuatan karya ilmiah
 * 
 * 
 * 
 * 
 * kaidah 5:
 * * impoersonal: menyebutkan posisi bukan seperti saya, dll
 * * kata baku: harus effective, tidka boleh singkatan
 * * kalimat effective: kalimat tidak betele tele, simple tidak berlebihan dan logis dan tidak ambigu, "kelas sebelah naik keatas aula" -> tidak effektif karna naik itu pasti ke atas
 * * 
 * 
 * 
 * catatan kaki itu sama seperti daftar pustaka tapi bisa ad dimana saja,
 * ada biasanya jika ada kutipan pada suatu halaman
 * tapi juga harus ada di daftar pustaka
 * 
 * 
 * 
 * Daftar Pustaka
 * ---
 * * Nama pengarang
 * * Tahun terbit
 * * Judul buku
 * * Tempat/Kota
 * * Penerbit
 * 
 * Format:
 * ___
 * nama belakang, nama. tahun, *Judul Buku* \/ Judul buku tempat/kota: penerbit
 * ___
 * 
 * jika ada 2 pengarang, yang no 2 tidka diapa apakan
 * dan jika panjang maka tambahkan baris dan dorong ke dalam
 * jika bergelar maka gelar akan dihilangkan
 * 
 * jika pengarang lebih dari 2, cukup ditambah penulis utama namaAkhir, namaUtama. dkk.
 * 
 * catata kaki -> foot 
 * 
 *  Catatan Kaki
 * ---
 * * Nama pengarang
 * * Tahun terbit
 * * Judul buku
 * * Tempat/Kota
 * * Penerbit
 * * halaman
 * 
 * urutan 1 -> 5 -> 2 -> 3 -> 4 -> 6
 * 
 * nama pengarang utama tidak perlu di balik dan dipisahkan oleh koma `,` 
 * 
 * format
 * ___
 * nama, *judul buku* (Tempat/Kota: Penerbit, tahun terbit), hlm. halaman.
 * ___
 * 
 * jika 2 lebh:
 * Penerbi, dkk.,
 * 
 * ___Cara menulis Judul karangan___
 * 
 *      Pengaruh Penggunaan Pestisida
 * Terhadap Kualitas Tanah dan Air di Desa
 * 
 */
function bhsIndoKaryaIliah() {}
