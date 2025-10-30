<?php

declare(strict_types=1);

namespace Experimental\App\Foundation;

use Experimental\App\Foundation\Database\QueryBuilder;
use ArrayIterator;
use Countable;
use ErrorException;
use IteratorAggregate;
use JsonSerializable;
use Stringable;
use Traversable;

class Model implements IteratorAggregate, Countable, JsonSerializable, Stringable
{
    protected $table;
    protected $fillable = [];
    protected $guarded = [];
    protected $primary = 'id';
    private bool $isFetched = false;
    private array $data = [];
    private bool $escapedWhenCastingToString = true;
    private static array $queryBuilders = [];
    private array $methodRegistry = [];

    public function __construct(array $attributes = [])
    {
        $this->data = $attributes;
        $this->resolveTableName();
    }

    /**
     * Register a static method to be handled by __callStatic
     */
    public static function registerStaticMethod(string $method, callable $handler): void
    {
        self::$methodRegistry[$method] = $handler;
    }

    /**
     * Intercept all static calls
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        // Check registered methods first
        if (isset(self::$methodRegistry[$method])) {
            return self::$methodRegistry[$method](...$args);
        }

        // Forward to QueryBuilder
        $qb = self::getQueryBuilder();
        if (method_exists($qb, $method)) {
            return $qb->$method(...$args);
        }

        throw new \BadMethodCallException("Static method $method not found");
    }

    /**
     * Get or create QueryBuilder instance for this model
     */
    protected static function getQueryBuilder(): QueryBuilder
    {
        $class = self::class;

        if (!isset(self::$queryBuilders[$class])) {
            self::$queryBuilders[$class] = new QueryBuilder();
            $caller = get_called_class();
            self::$queryBuilders[$class]->table((new $caller)->resolveTableName());
        }

        return self::$queryBuilders[$class];
    }

    /**
     * Resolve table name from class name
     */
    protected function resolveTableName(): string
    {
        if (!isset($this->table)) {
            $className = basename(str_replace('\\', '/', static::class));
            $this->table = strtolower(str_replace('base', '', $className)) . 's';
        }
        return $this->table;
    }

    // ... [keep all your existing instance methods unchanged] ...

    public function save()
    {
        $qb = static::getQueryBuilder();
        return $this->isFetched
            ? $qb->update($this->data)
            : $qb->insert($this->data);
    }

    public function delete()
    {
        $qb = static::getQueryBuilder();
        foreach ($this->data as $key => $value) {
            $qb->where($key, $value);
        }
        return $qb->delete();
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
        return $this->data;
    }
    
    public function __debugInfo(){
        return $this->data;
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
        return json_encode($this);
    }
    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function __get($name)
    {
        return $this->data[$name] ?? throw new ErrorException('Undefined property: ' . self::class . '::$' . $name);
    }
}
