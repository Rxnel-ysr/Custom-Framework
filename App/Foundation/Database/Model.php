<?php

declare(strict_types=1);

namespace App\Foundation;

require_once 'QueryBuilder.php';

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
 * @method static    where(string $column, mixed $value, string $operator)
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
    private Collection $data;

    public function __construct($attributes = [])
    {
        parent::__construct();

        $this->data = $attributes instanceof Collection ? $attributes : collect($attributes);
        $this->original = $this->data->toArray();

        if (!$this->table) {
            $tableName = $this->getTableName();
            $primary = $this->primary;
            $this->setModelTable($tableName, $primary)->___table($tableName, $primary);
        }

        if($this->timestamps){
            $this->fillable = array_merge($this->fillable, ['created_at','updated_at']);
        }

        $this->pdo = self::getInstance();
    }

    protected function cast(mixed $value, CastType|string $cast): mixed
    {
        if (is_string($cast)) {
            $castType = CastType::parse($cast);
            $param = CastType::extractParam($cast);
            return $castType->apply($value, $param);
        }

        return $cast->apply($value);
    }

    protected function applyCast(object|array|null $stdClass): object|array|null
    {
        if(is_null($stdClass)){
            return null;
        }
        if (is_array($stdClass)) {
            return array_map(fn($v) => $this->applyCast($v), $stdClass);
        }

        $objectVars = get_object_vars($stdClass);
        foreach (array_intersect_key($this->casts, $objectVars) as $col => $cast) {
            $castEnum = CastType::parse($cast);
            $param =  CastType::extractParam($cast);
            // dd($cast, $castEnum, $param);

            $stdClass->{$col} = $castEnum->apply($stdClass->{$col}, $param);
        }

        return $stdClass;
    }

    /**
     * Helper method to process casts array with enum support
     */
    private function processCastDefinition(mixed $cast): CastType
    {
        if ($cast instanceof CastType) {
            return $cast;
        }

        return Cast::parse((string)$cast);
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

    /**
     * Handle static method calls
     */
    public static function __callStatic($method, $args)
    {
        $instance = new static();

        $res = call_user_func_array([$instance, '___' . $method], $args);

        if ($method == 'get' || $method == 'first' || $method == 'find') {

            if ($method == 'get') {
                $newRes = [];
                foreach ($res as $r) {
                    $newRes[] = new static($instance->applyCast($r));
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
            $res = call_user_func_array([clone $this, '___' .$method], $args);

            if ($method == 'get' || $method == 'first' || $method == 'find') {

                if ($method == 'get') {
                    $newRes = [];
                    foreach ($res as $r) {
                        $newRes[] = new static($this->applyCast($r));
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
            'all' => fn($args) => $this->data->all(...$args),
            'toArray' => fn($args) => $this->data->toArray(...$args),
            'toJson' => fn($args) => $this->data->toJson(...$args),
            'count' => fn($args) => $this->data->count(...$args),
            'isEmpty' => fn($args) => $this->data->isEmpty(...$args),
            'isNotEmpty' => fn($args) => $this->data->isNotEmpty(...$args),
            'jsonSerialize' => fn($args) => $this->data->jsonSerialize(...$args),
            'getIterator' => fn($args) => $this->data->getIterator(...$args),

            // Filtering Methods
            'filter' => fn($args) => new static($this->data->filter(...$args)),
            'reject' => fn($args) => $this->data->reject(...$args),
            'first' => fn($args) => $this->data->first(...$args),
            'firstOrFail' => fn($args) => $this->data->firstOrFail(...$args),
            'last' => fn($args) => $this->data->last(...$args),
            'where' => fn($args) => $this->data->where(...$args),
            'whereStrict' => fn($args) => $this->data->whereStrict(...$args),
            'whereIn' => fn($args) => $this->data->whereIn(...$args),
            'whereNotIn' => fn($args) => $this->data->whereNotIn(...$args),
            'whereBetween' => fn($args) => $this->data->whereBetween(...$args),
            'whereNotBetween' => fn($args) => $this->data->whereNotBetween(...$args),
            'whereNull' => fn($args) => $this->data->whereNull(...$args),
            'whereNotNull' => fn($args) => $this->data->whereNotNull(...$args),
            'unique' => fn($args) => $this->data->unique(...$args),

            // Transforming Methods
            'map' => fn($args) => new static($this->data->map(...$args)),
            'mapInto' => fn($args) => $this->data->mapInto(...$args),
            'transform' => fn($args) => $this->data->transform(...$args),
            'reduce' => fn($args) => $this->data->reduce(...$args),
            'pluck' => fn($args) => $this->data->pluck(...$args),
            'implode' => fn($args) => $this->data->implode(...$args),
            'each' => fn($args) => $this->data->each(...$args),
            'tap' => fn($args) => $this->data->tap(...$args),
            'pipe' => fn($args) => $this->data->pipe(...$args),
            'toObj' => fn($args) => $this->data->toObj(...$args),

            // Sorting & Grouping Methods
            'groupBy' => fn($args) => $this->data->groupBy(...$args),
            'sortBy' => fn($args) => $this->data->sortBy(...$args),
            'sortByDesc' => fn($args) => $this->data->sortByDesc(...$args),
            'sortDesc' => fn($args) => $this->data->sortDesc(...$args),
            'sortAsc' => fn($args) => $this->data->sortAsc(...$args),
            'reverse' => fn($args) => $this->data->reverse(...$args),
            'shuffle' => fn($args) => $this->data->shuffle(...$args),

            // Mutation Methods
            'push' => fn($args) => $this->data->push(...$args),
            'prepend' => fn($args) => $this->data->prepend(...$args),
            'pop' => fn($args) => $this->data->pop(...$args),
            'shift' => fn($args) => $this->data->shift(...$args),
            'merge' => fn($args) => $this->data->merge(...$args),
            'mergeRecursive' => fn($args) => $this->data->mergeRecursive(...$args),
            'replace' => fn($args) => $this->data->replace(...$args),
            'chunk' => fn($args) => $this->data->chunk(...$args),
            'flatten' => fn($args) => $this->data->flatten(...$args),
            'flattenDeep' => fn($args) => $this->data->flattenDeep(...$args),
            'collapse' => fn($args) => $this->data->collapse(...$args),
            'zip' => fn($args) => $this->data->zip(...$args),

            'set' => fn($args) => $this->data->set(...$args),
            'has' => fn($args) => $this->data->has(...$args),
            'forget' => fn($args) => $this->data->forget(...$args),
            'only' => fn($args) => $this->data->only(...$args),
            'except' => fn($args) => $this->data->except(...$args),
            'keyBy' => fn($args) => $this->data->keyBy(...$args),
            'keys' => fn($args) => $this->data->keys(...$args),
            'values' => fn($args) => $this->data->values(...$args),

            // Aggregate Methods
            'sum' => fn($args) => $this->data->sum(...$args),
            'avg' => fn($args) => $this->data->avg(...$args),
            'min' => fn($args) => $this->data->min(...$args),
            'max' => fn($args) => $this->data->max(...$args),
            'median' => fn($args) => $this->data->median(...$args),
            'mode' => fn($args) => $this->data->mode(...$args),

            // Conditional Methods
            'contains' => fn($args) => $this->data->contains(...$args),
            'containsStrict' => fn($args) => $this->data->containsStrict(...$args),
            'doesntContain' => fn($args) => $this->data->doesntContain(...$args),
            'every' => fn($args) => $this->data->every(...$args),
        ];

        switch (true) {
            case $this->data->isNotEmpty() && isset($availableCollectionMethods[$method]):
                return $availableCollectionMethods[$method]($args);
            default:
                return self::__callStatic($method, $args);;
        };
    }

    public function __set($name, $value)
    {
        if ($this->data->get($name) !== $value) {
            $this->dirty[$name] = $value;
        }
        return $this->data->set($name, $value);
    }

    public function __get($name)
    {
        return $this->data->get($name, null);
    }

    public function all()
    {
        return $this->data;
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
        return $this->data;
    }

    public function __invoke()
    {
        response()->json($this->data);
    }

    public function save()
    {
        $currentTime = date('Y-m-d H:i:s');
        $array = $this->dirty();
        if ($this->isFetched) {
            if ($this->isDirty()) {
                (clone $this)->___where(
                    $this->primary,
                    $this->data->get($this->primary)
                )->___update(
                    $this->timestamps ? array_merge($array, ['updated_at' => $currentTime]) : $array
                );
            }
        } else {
            (clone $this)->___insert(
                $this->timestamps ? array_merge($array, ['updated_at' => $currentTime, 'created_at' => $currentTime]) : $array
            );
        }
    }


    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function jsonSerialize(): mixed
    {
        return $this->data->toArrayWithout($this->hidden);
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
        $data = $this->data->toArrayWithout($this->hidden);
        return json_encode($data);
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
