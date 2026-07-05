<?php

declare(strict_types=1);

namespace App\Foundation\Database;

require_once 'QueryBuilder.php';

use App\Foundation\Database\Cast;
use App\Foundation\Traits\Strings;
use App\Foundation\Database\QueryBuilder;
use App\Foundation\Enums\Database\CastType;
use App\Foundation\Support\Collection;
use ArrayIterator;
use Countable;
use ErrorException;
use Exception;
use IteratorAggregate;
use JsonSerializable;
use Stringable;
use Traversable;

/**
 * Base Model that represent a table in database
 * 
 * @template TModel of Model
 */
class Model extends QueryBuilder implements IteratorAggregate, Countable, JsonSerializable, Stringable
{
    use Strings;

    protected $table;
    protected $fillable = [];
    protected $guarded = [];
    protected $primary = 'id';
    protected $casts = [];
    protected $hidden = [];
    protected $isFetched;
    protected $timestamps = true;
    protected $original;
    protected $dirty = null;
    protected $data;
    private Collection $collectionData;

    public function __construct($attributes = [])
    {
        parent::__construct();

        $this->collectionData = $attributes instanceof Collection ? $attributes : collect($attributes);
        $this->original = $this->data = $this->collectionData->toArray();

        if (!$this->table) {
            $tableName = $this->getTableName();
            $primary = $this->primary;
            $this->setModelTable($tableName, $primary)->___table($tableName, $primary);
        }

        if ($this->timestamps) {
            $this->fillable = array_merge($this->fillable, ['created_at', 'updated_at']);
        }
    }

    protected function cast(mixed $value, CastType|string $cast): mixed
    {
        if (is_string($cast)) {
            [$castType, $param] = CastType::parse($cast);
            return $castType->apply($value, $param);
        }

        return $cast->apply($value);
    }

    protected function applyCast(object|array|null $stdClass): object|array|null
    {
        if (is_null($stdClass)) {
            return null;
        }
        if (is_array($stdClass)) {
            return array_map(fn($v) => $this->applyCast($v), $stdClass);
        }

        $objectVars = get_object_vars($stdClass);
        foreach (array_intersect_key($this->casts, $objectVars) as $col => $cast) {
            [$castEnum, $param] = CastType::parse($cast);

            $stdClass->{$col} = $castEnum->apply($stdClass->{$col}, $param);
        }

        return $stdClass;
    }

    public function setModelTable($table, $primaryColumn)
    {
        $this->table = $table;
        $this->primary = $primaryColumn;
        return $this;
    }

    public function setFetched()
    {
        $this->isFetched = true;
    }

    public function query()
    {
        return new $this;
    }

    /**
     * Handle static method calls
     */
    public static function __callStatic($method, $args)
    {
        $instance = new static();

        $res = call_user_func_array([$instance, '___' . $method], $args);

        if ($method == 'get' || $method == 'first' || $method == 'find' || $method == 'findOrFail') {

            if ($method == 'get') {
                $newRes = [];
                foreach ($res as $r) {
                    $tmp = new static($instance->applyCast($r));
                    $tmp->isFetched = true;
                    $newRes[] = $tmp;
                }
                return $newRes;
            } else {
                $new = new static($instance->applyCast($res));
                $new->isFetched = true;
                return $new;
            }
        }

        return $res;
    }

    /**
     * Get the table name for the model
     */
    public function getTableName(): string
    {
        if ($this->table) {
            return $this->table;
        }

        $sanitized = strtolower(basename(str_replace('\\', '/', static::class)));
        $tableName = self::isPlural($sanitized) ? $sanitized : self::pluralize($sanitized);

        return $this->table = $tableName;
    }

    /**
     * Handle instance method calls
     */
    public function __call($method, $args)
    {
        if (method_exists($this, '___' . $method) && !$this->isFetched) {
            $res = call_user_func_array([clone $this, '___' . $method], $args);

            if ($method == 'get' || $method == 'first' || $method == 'find' || $method == 'findOrFail') {

                if ($method == 'get') {
                    $newRes = [];
                    foreach ($res as $r) {
                        $tmp = new static($this->applyCast($r));
                        $tmp->isFetched = true;
                        $newRes[] = $tmp;
                    }
                    return collect($newRes);
                } else {
                    $new = new static($this->applyCast($res));
                    $new->isFetched = true;
                    return $new;
                }
            }

            return $res;
        }

        $availableCollectionMethods = [
            // Core Methods
            'all' => fn($args) => $this->collectionData->all(...$args),
            'toArray' => fn($args) => $this->collectionData->toArray(...$args),
            'toJson' => fn($args) => $this->collectionData->toJson(...$args),
            'count' => fn($args) => $this->collectionData->count(...$args),
            'isEmpty' => fn($args) => $this->collectionData->isEmpty(...$args),
            'isNotEmpty' => fn($args) => $this->collectionData->isNotEmpty(...$args),
            'jsonSerialize' => fn($args) => $this->collectionData->jsonSerialize(...$args),
            'getIterator' => fn($args) => $this->collectionData->getIterator(...$args),

            // Filtering Methods
            'filter' => fn($args) => new static($this->collectionData->filter(...$args)),
            'reject' => fn($args) => $this->collectionData->reject(...$args),
            'first' => fn($args) => $this->collectionData->first(...$args),
            'firstOrFail' => fn($args) => $this->collectionData->firstOrFail(...$args),
            'last' => fn($args) => $this->collectionData->last(...$args),
            'where' => fn($args) => $this->collectionData->where(...$args),
            'whereStrict' => fn($args) => $this->collectionData->whereStrict(...$args),
            'whereIn' => fn($args) => $this->collectionData->whereIn(...$args),
            'whereNotIn' => fn($args) => $this->collectionData->whereNotIn(...$args),
            'whereBetween' => fn($args) => $this->collectionData->whereBetween(...$args),
            'whereNotBetween' => fn($args) => $this->collectionData->whereNotBetween(...$args),
            'whereNull' => fn($args) => $this->collectionData->whereNull(...$args),
            'whereNotNull' => fn($args) => $this->collectionData->whereNotNull(...$args),
            'unique' => fn($args) => $this->collectionData->unique(...$args),

            // Transforming Methods
            'map' => fn($args) => new static($this->collectionData->map(...$args)),
            'mapInto' => fn($args) => $this->collectionData->mapInto(...$args),
            'transform' => fn($args) => $this->collectionData->transform(...$args),
            'reduce' => fn($args) => $this->collectionData->reduce(...$args),
            'pluck' => fn($args) => $this->collectionData->pluck(...$args),
            'implode' => fn($args) => $this->collectionData->implode(...$args),
            'each' => fn($args) => $this->collectionData->each(...$args),
            'tap' => fn($args) => $this->collectionData->tap(...$args),
            'pipe' => fn($args) => $this->collectionData->pipe(...$args),
            'toObj' => fn($args) => $this->collectionData->toObj(...$args),

            // Sorting & Grouping Methods
            'groupBy' => fn($args) => $this->collectionData->groupBy(...$args),
            'sortBy' => fn($args) => $this->collectionData->sortBy(...$args),
            'sortByDesc' => fn($args) => $this->collectionData->sortByDesc(...$args),
            'sortDesc' => fn($args) => $this->collectionData->sortDesc(...$args),
            'sortAsc' => fn($args) => $this->collectionData->sortAsc(...$args),
            'reverse' => fn($args) => $this->collectionData->reverse(...$args),
            'shuffle' => fn($args) => $this->collectionData->shuffle(...$args),

            // Mutation Methods
            'push' => fn($args) => $this->collectionData->push(...$args),
            'prepend' => fn($args) => $this->collectionData->prepend(...$args),
            'pop' => fn($args) => $this->collectionData->pop(...$args),
            'shift' => fn($args) => $this->collectionData->shift(...$args),
            'merge' => fn($args) => $this->collectionData->merge(...$args),
            'mergeRecursive' => fn($args) => $this->collectionData->mergeRecursive(...$args),
            'replace' => fn($args) => $this->collectionData->replace(...$args),
            'chunk' => fn($args) => $this->collectionData->chunk(...$args),
            'flatten' => fn($args) => $this->collectionData->flatten(...$args),
            'flattenDeep' => fn($args) => $this->collectionData->flattenDeep(...$args),
            'collapse' => fn($args) => $this->collectionData->collapse(...$args),
            'zip' => fn($args) => $this->collectionData->zip(...$args),

            'set' => fn($args) => $this->collectionData->set(...$args),
            'has' => fn($args) => $this->collectionData->has(...$args),
            'forget' => fn($args) => $this->collectionData->forget(...$args),
            'only' => fn($args) => $this->collectionData->only(...$args),
            'except' => fn($args) => $this->collectionData->except(...$args),
            'keyBy' => fn($args) => $this->collectionData->keyBy(...$args),
            'keys' => fn($args) => $this->collectionData->keys(...$args),
            'values' => fn($args) => $this->collectionData->values(...$args),

            // Aggregate Methods
            'sum' => fn($args) => $this->collectionData->sum(...$args),
            'avg' => fn($args) => $this->collectionData->avg(...$args),
            'min' => fn($args) => $this->collectionData->min(...$args),
            'max' => fn($args) => $this->collectionData->max(...$args),
            'median' => fn($args) => $this->collectionData->median(...$args),
            'mode' => fn($args) => $this->collectionData->mode(...$args),

            // Conditional Methods
            'contains' => fn($args) => $this->collectionData->contains(...$args),
            'containsStrict' => fn($args) => $this->collectionData->containsStrict(...$args),
            'doesntContain' => fn($args) => $this->collectionData->doesntContain(...$args),
            'every' => fn($args) => $this->collectionData->every(...$args),
        ];

        switch (true) {
            case $this->collectionData->isNotEmpty() && isset($availableCollectionMethods[$method]):
                return $availableCollectionMethods[$method]($args);
            default:
                return self::__callStatic($method, $args);;
        };
    }

    public function __set($name, $value)
    {
        if ($this->collectionData->get($name) !== $value) {
            $this->dirty[$name] = $value;
            $this->data[$name] = $value;
        }
        return $this->collectionData->set($name, $value);
    }

    public function __get($name)
    {
        return $this->collectionData->get($name, null);
    }

    public function all()
    {
        return $this->collectionData;
    }

    public function dirty()
    {
        return $this->dirty;
    }

    public function isDirty()
    {
        return !empty($this->dirty);
    }

    public function getProp()
    {
        return $this->collectionData;
    }

    public function getPrimary()
    {
        return $this->primary;
    }

    public function __invoke()
    {
        response()->json($this->collectionData);
    }

    public function update(array $data)
    {
        if ($this->isFetched) {
            return (clone $this)->where($this->primary, $this->collectionData->get($this->primary))->update($data);
        }

        return (clone $this)->update($data);
    }

    public function save()
    {
        $currentTime = date('Y-m-d H:i:s');
        $array = $this->isFetched ? $this->dirty() : $this->all()->toArray();
        if ($this->isFetched) {
            if ($this->isDirty()) {
                (clone $this)->___where(
                    $this->primary,
                    $this->collectionData->get($this->primary)
                )->___update(
                    $this->timestamps ? array_merge($array, ['updated_at' => $currentTime]) : $array
                );
            }
        } else {
            (clone $this)->___insert(
                $this->timestamps ? array_merge($array, ['updated_at' => $currentTime, 'created_at' => $currentTime]) : $array
            );
        }

        return $this;
    }


    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->collectionData);
    }

    public function count(): int
    {
        return count($this->collectionData);
    }

    public function jsonSerialize(): mixed
    {
        return $this->collectionData->toArrayWithout($this->hidden);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    public function escapeWhenCastedToString(bool $escape = true)
    {
        $this->escapedWhenCastingToString = $escape;
    }

    public function toJson()
    {
        return json_encode($this->collectionData->toArrayWithout($this->hidden));
    }

    /**
     * Add cast definitions using the Cast enum
     */
    public function addCast(string $column, Cast|string $cast): self
    {
        $this->casts[$column] = $cast;
        return $this;
    }

    /**
     * Get the Cast enum for a column
     */
    public function getCast(string $column): ?Cast
    {
        if (!isset($this->casts[$column])) {
            return null;
        }

        $cast = $this->casts[$column];
        return is_string($cast) ? Cast::parse($cast) : $cast;
    }
}
