<?php

declare(strict_types=1);

namespace App\Foundation;

require_once 'QueryBuilder_EXPE.php';

use Experimental\App\Foundation\Database\QueryBuilder;
use ArrayIterator;
use Countable;
use ErrorException;
use IteratorAggregate;
use JsonSerializable;
use Stringable;
use Traversable;

class Model extends QueryBuilder implements IteratorAggregate, Countable, JsonSerializable, Stringable
{
    protected $table;
    protected $fillable = [];
    protected $guarded = [];
    protected $primary = 'id';
    private $isFetched;
    private $data = [];
    private $escapedWhenCastingToString = true;

    public function __construct($attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->data[$key] = $value;
        }

        if (!$this->table)
            $this->table(strtolower(str_replace('base', '', basename(str_replace('\\', '/', get_called_class())))) . 's');

        $this->pdo = self::getInstance();
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function __get($name)
    {
        return $this->data[$name] ?? throw new ErrorException('Undefined property: ' . self::class . '::$' . $name);
    }

    public function all()
    {
        return $this->data;
    }

    public function map($callback)
    {
        return array_map($callback, $this->data);
    }

    public function filter($callback)
    {
        return array_filter($this->data, $callback);
    }

    public function walk($callback, ...$args)
    {
        array_walk($this->data, $callback, ...$args);
        return $this;
    }

    public function getProp()
    {
        return $this->data;
    }

    public function __invoke()
    {
        response()->json($this->data);
    }

    public function __debugInfo()
    {
        return array_map(function ($d) {
            if ($d instanceof self) {
                return $d->getProp();
            }
            return $d;
        }, $this->data);
    }

    public function save()
    {
        $this->isFetched ? $this->update($this->data) : $this->insert($this->data);
    }

    public function destroy()
    {
        array_map(fn($a, $b) => $this->where($a, $b), $this->data);
        $this->delete();
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
}
