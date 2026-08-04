<?php

declare(strict_types=1);

namespace App\Foundation\Database;

require_once 'Connection.php';

use App\Debug\Debugger;
use App\Foundation\Database\Connection;
use App\Foundation\Exceptions\Framework\Database\ModelNotFoundException;
use App\Foundation\Exceptions\Framework\Primitive\InvalidArgumentException;
use App\Foundation\Exceptions\Framework\Primitive\LogicException;
use App\Foundation\Traits\Strings;

class RawStatement
{
    public static function raw(string $statement)
    {
        return new self($statement);
    }

    public function __construct(protected string $statement) {}

    public function __toString()
    {
        return $this->statement;
    }
}

/**
 * @method static static next()
 *
 * @method static static fillable(array $fillable = [])
 * @method static static guarded(array $guarded = [])
 *
 * @method static static table(string $table, string $primaryColumn = 'id')
 * @method static static select(string|array $columns = ['*'])
 * @method static static with(string|array $relations = [], string ...$moreRelation)
 *
 * @method static static where($column, $value, string $operator = '=')
 * @method static static orWhere($column, $value, string $operator = '=')
 *
 * @method static mixed find($primary)
 * @method static mixed findOrFail($primary)
 *
 * @method static static insert(array $data = [])
 * @method static static create(array $data)
 *
 * @method static array|null get($columns = null, bool $skipRelations = false)
 * @method static object|null first(bool $skipRelations = false)
 *
 * @method static \PDOStatement update(array $data = [], bool $ignoreWhereWarning = false)
 * @method static \PDOStatement delete(bool $ignoreWhereWarning = false)
 *
 * @method static static limit(int $limitNumber)
 * @method static static offset(int $offsetNumber)
 *
 * @method static \App\Foundation\Database\RawStatement rawExpr(string $expression)
 *
 * @method static static join(string $table, string $ownerTableColumn, string $foreignKey, string $operator = '=', string $type = 'INNER')
 * @method static static orderBy(string $column, string $direction = 'ASC')
 * @method static static groupBy(string $column)
 *
 * @method static static restrain(bool $state)
 *
 * @method static static raw(string $sql, array $bindings = [])
 * @method static \PDOStatement execute()
 *
 * @method static object|false fetch()
 * @method static array fetchAll()
 *
 * @method static string|false lastInsertId()
 * @method static bool close()
 *
 * @method static void purge()
 *
 * @method static int rowCount()
 */
class QueryBuilder extends Connection
{
    use Strings;

    # Base var
    private $query = '';
    protected $table = '';
    private $columns = [];
    protected $fillable = [];
    protected $guarded = [];
    protected $primary = 'id';
    private $usedSelect = false;
    private $bindings = [];
    private $stmt;
    private $pdo;
    private $isFetched = false;

    # Relations var
    private $related = [];
    public $relations = [];

    # Attributes
    private $restrain = true;

    # Relation cache
    private $relationCache = [];

    /**
     * Query constructor, reuses the singleton PDO connection.
     */
    public function __construct()
    {
        $this->pdo = Connection::getInstance();
    }

    public static function __callStatic($name, $arguments)
    {
        return (new self())->$name(...$arguments);
    }

    private function countFromEnd(string $s, string $c): int
    {
        $count = 0;
        for ($i = strlen($s) - 1; $i >= 0; --$i) {
            if ($s[$i] === $c) $count++;
        }
        return $count;
    }


    public function __call($name, $arguments)
    {
        return (clone $this)->{'___' . $name}(...$arguments);
    }

    protected function ___next(): self
    {
        return new self();
    }

    protected function ___fillable(array $fillable = []): self
    {
        $this->fillable = $fillable;
        return (clone $this);
    }

    protected function ___guarded(array $guarded = []): self
    {
        $this->guarded = $guarded;
        return (clone $this);
    }

    private function setIsFetched(): void
    {
        $this->isFetched = !$this->isFetched;
    }

    // In QueryBuilder.php, add a getRelations method:
    protected function getRelations()
    {
        return (clone $this)->relations;
    }

    /**
     * Get ip of person or thing that made the query.
     */
    private static function getRequestIp(): string
    {
        return '[' . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') . ']:';
    }

    /**
     * Determine to use WHERE or AND in query.
     */
    private function whereOrAnd(): string
    {
        return (strpos($this->query, 'WHERE') === false ? ' WHERE' : ' AND') . ' ';
    }

    private function decideSelect(?array $columns = null): string
    {
        return empty($columns) ? (!empty($this->columns) ? implode(', ', $this->columns) : '*') : implode(', ', $columns);
    }

    protected function filterColumns(array $columns = []): array
    {
        if (empty($this->fillable)) {
            return $columns;
        }

        return array_diff_key(
            array_intersect_key($columns, array_flip($this->fillable)),
            array_flip($this->guarded)
        );
    }

    /**
     * The very first method you'll use to specify the table.
     */
    protected function ___table(string $table, string $primaryColumn = 'id'): self
    {
        $this->table = $table;
        $this->primary = $primaryColumn;
        return (clone $this);
    }

    /**
     * Select columns to retrieve.
     */
    protected function ___select(string|array $columns = ['*']): self
    {
        $columns = is_array($columns) ? $columns : [$columns];

        $columnsStr = implode(', ', array_map(
            fn($col) => $col instanceof RawStatement ? $col->__toString() : $col,
            $columns
        ));

        $this->query = 'SELECT ' . $columnsStr . ' FROM ' . $this->table;
        $this->usedSelect = true;
        return (clone $this);
    }

    /**
     * Switches between "id_<name>" and "<name>_id" formats.
     */
    private function switchColumnPattern(string $column): string
    {
        if (preg_match('/^id_(\w+)$/', $column, $matches)) {
            return "{$matches[1]}_id";
        }

        if (preg_match('/^(\w+)_id$/', $column, $matches)) {
            return 'id_' . $matches[1];
        }

        return $column;
    }

    protected function ___with(string|array $relations = [], string ...$moreRelation): self
    {
        $relations = is_string($relations) ? [$relations] : $relations;
        $relations = !empty($moreRelation) ? array_merge($relations, $moreRelation) : $relations;

        foreach ($relations as $key => $value) {

            if (is_string($value)) {
                $this->loadRelationFromString($value);
                continue;
            }

            if (is_callable($value)) {
                $this->loadRelationWithCallback($key, $value);
                continue;
            }

            throw new InvalidArgumentException("Relation [$key] must be string, callable.");
        }

        return (clone $this);
    }

    protected function loadRelationFromString(string $value): void
    {
        [$name, $cols] = array_pad(explode(':', $value, 2), 2, null);

        if (!method_exists(static::class, $name)) {
            throw new LogicException("Relation method [$name] does not exist.");
        }

        $rel = $this->{$name}();
        $key = array_key_first($rel);
        $def = $rel[$key];

        $def->columns = $cols ? explode(',', $cols) : ['*'];

        $this->relations[$key] = $def;
    }

    protected function loadRelationWithCallback(string $name, \Closure $callback): void
    {
        [$name, $cols] = array_pad(explode(':', $name, 2), 2, null);

        if (!method_exists(static::class, $name)) {
            throw new LogicException("Relation method [$name] does not exist.");
        }

        $rel = $this->{$name}();
        $key = array_key_first($rel);
        $def = $rel[$key];

        $def->columns = $cols ? explode(',', $cols) : ['*'];
        $def->constraint = $callback;

        $this->relations[$key] = $def;
    }

    protected function resolveModel($modelOrTable)
    {
        if (!is_string($modelOrTable) || !class_exists($modelOrTable)) {
            return null;
        }

        return new $modelOrTable();
    }


    protected function belongsTo($modelOrTable, $localKey = null, $foreignKey = 'id', $name = null)
    {
        $name ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function']
            ?? throw new LogicException('Relation name cannot be resolved.');

        $related = $this->resolveTable($modelOrTable);
        $localKey ??= self::pluralToSingular($related) . '_id';

        return [
            $name => (object) [
                'type'          => 'belongsTo',
                'mode'          => 'one',
                'model'         => $this->resolveModel($modelOrTable),
                'table'         => $related,
                'foreign_key'     => $foreignKey, // Column in related table
                'local_key'   => $localKey, // Column in current table
                'columns'       => ['*'],
            ]
        ];
    }

    protected function hasOne($modelOrTable, $foreignKey = null, $localKey = 'id', $name = null)
    {
        $name ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function']
            ?? throw new LogicException('Relation name cannot be resolved.');

        // Auto-detect foreign key if not provided
        $foreignKey ??= self::pluralToSingular($this->table) . '_id';

        return [
            $name => (object) [
                'type'          => 'hasOne',
                'mode'          => 'one',
                'table'         => $this->resolveTable($modelOrTable),
                'model'         => $this->resolveModel($modelOrTable),
                'local_key'     => $localKey, // Column in current table
                'foreign_key'   => $foreignKey, // Column in related table
                'columns'       => ['*'],
            ]
        ];
    }

    protected function hasMany($modelOrTable, $foreignKey = null, $localKey = 'id', $name = null)
    {
        $name ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function']
            ?? throw new LogicException('Relation name cannot be resolved.');

        // Auto-detect foreign key if not provided
        $foreignKey ??= self::pluralToSingular($this->table) . '_id';

        return [
            $name => (object) [
                'type'          => 'hasMany',
                'mode'          => 'many',
                'table'         => $this->resolveTable($modelOrTable),
                'model'         => $this->resolveModel($modelOrTable),
                'local_key'     => $localKey, // Column in current table
                'foreign_key'   => $foreignKey, // Column in related table
                'columns'       => ['*'],
            ]
        ];
    }

    protected function belongsToMany(
        $modelOrTable,
        $pivotTable = null,
        $foreignPivotKey = null,
        $relatedPivotKey = null,
        $parentKey = 'id',
        $relatedKey = 'id',
        $name = null,
        $withPivot = [],
        $pivotDataKey = 'pivot',
        $returnType = 'auto'
    ) {
        $name ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function']
            ?? throw new LogicException('Relation name cannot be resolved.');

        // Auto-detect pivot table if not provided
        if ($pivotTable === null) {
            $tables = [self::pluralToSingular($this->table), self::pluralToSingular($this->resolveTable($modelOrTable))];
            sort($tables);
            $pivotTable = implode('_', $tables);
        } else {
            $pivotTable = $this->resolveTable($pivotTable);
        }

        // Auto-detect foreign keys if not provided
        $foreignPivotKey ??= self::pluralToSingular($this->table) . '_id';
        $relatedPivotKey ??= self::pluralToSingular($this->resolveTable($modelOrTable)) . '_id';

        return [
            $name => (object) [
                'type'              => 'belongsToMany',
                'mode'              => 'many',
                'table'             => $this->resolveTable($modelOrTable),

                'local_key'         => $parentKey,
                'foreign_key'       => $relatedKey,

                'pivot_table'       => $pivotTable,

                'pivot_local_key'   => $foreignPivotKey,
                'pivot_foreign_key' => $relatedPivotKey,

                'pivot_columns'     => is_array($withPivot) ? $withPivot : [$withPivot],
                'return_type'       => $returnType, // 'auto', 'with_pivot', 'separate',
                'pivot_data_key'    => $pivotDataKey,
                'columns'           => ['*'],
            ]
        ];
    }

    /**
     * Load many-to-many relation with smart mapping
     */
    private function loadManyToManyRelation($mainItem, $relation): array
    {
        $cacheKey = $relation->local_key . '_' . $mainItem->id . '_' . $relation->table . '_' .
            ($relation->pivot_table ?? '') . '_' . implode(',', $relation->pivot_columns ?? []);

        if (isset($this->relationCache[$cacheKey])) {
            return (clone $this)->relationCache[$cacheKey];
        }

        // Check if we should include pivot columns
        $includePivotColumns = !empty($relation->pivot_columns) && $relation->pivot_columns !== ['*'];

        // Build pivot select columns
        $pivotSelect = [$relation->pivot_local_key, $relation->pivot_foreign_key];
        if ($includePivotColumns) {
            $pivotSelect = array_merge($pivotSelect, $relation->pivot_columns);
        }

        // Get pivot table data
        $pivotData = $this->___next()
            ->___table($relation->pivot_table)
            ->___select(array_unique($pivotSelect))
            ->___where($relation->pivot_local_key, $mainItem->{$relation->local_key})
            ->___get(null, true);

        if (empty($pivotData)) {
            $this->relationCache[$cacheKey] = [];
            return [];
        }

        $relatedIds = array_column($pivotData, $relation->pivot_foreign_key);

        // Get related data
        $relatedData = $this->___next()
            ->___table($relation->table)
            ->___select($relation->columns)
            ->___where($relation->foreign_key, $relatedIds, 'in')
            ->___get(null, true);

        // Create a map of related_id => related_item
        $relatedMap = [];
        foreach ($relatedData as $item) {
            $relatedMap[$item->{$relation->foreign_key}] = $item;
        }

        // Build the result based on pivot columns
        $result = [];

        foreach ($pivotData as $pivotRow) {
            // dd($pivotRow);
            // print_rpre($pivotRow);
            // exit;
            $relatedId = $pivotRow->{$relation->pivot_foreign_key};

            if (!isset($relatedMap[$relatedId])) {
                continue;
            }

            $relatedItem = clone $relatedMap[$relatedId];

            if ($includePivotColumns) {
                // If we have pivot columns, create a pivot object
                $pivotAttributes = [];
                foreach ($relation->pivot_columns as $column) {
                    if (isset($pivotRow->{$column})) {
                        $pivotAttributes[$column] = $pivotRow->{$column};
                    }
                }

                // Remove foreign keys from pivot attributes
                unset(
                    $pivotAttributes[$relation->pivot_local_key],
                    $pivotAttributes[$relation->pivot_foreign_key]
                );

                if (!empty($pivotAttributes)) {
                    $relatedItem->{$relation->pivot_data_key} = (object) $pivotAttributes;
                }
            }

            $result[] = $relatedItem;
        }

        $this->relationCache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Alternative: Return structured object with both paths
     */
    private function loadManyToManyRelationStructured($mainItem, $relation): object
    {
        $cacheKey = $relation->local_key . '_' . $mainItem->id . '_' . $relation->table . '_' .
            ($relation->pivot_table ?? '') . '_structured';

        if (isset($this->relationCache[$cacheKey])) {
            return (clone $this)->relationCache[$cacheKey];
        }

        // Build pivot select columns
        $pivotSelect = [$relation->pivot_local_key, $relation->pivot_foreign_key];
        $includePivotColumns = !empty($relation->pivot_columns) && $relation->pivot_columns !== ['*'];

        if ($includePivotColumns) {
            $pivotSelect = array_merge($pivotSelect, $relation->pivot_columns);
        }

        // Get pivot table data
        $pivotData = $this->___next()
            ->___table($relation->pivot_table)
            ->___select(array_unique($pivotSelect))
            ->___where($relation->pivot_local_key, $mainItem->{$relation->local_key})
            ->___get(null, true);

        if (empty($pivotData)) {
            $result = (object) [
                'related' => [],
                'pivot' => []
            ];
            $this->relationCache[$cacheKey] = $result;
            return $result;
        }

        $relatedIds = array_column($pivotData, $relation->pivot_foreign_key);

        // Get related data
        $relatedData = $this->___next()
            ->___table($relation->table)
            ->___select($relation->columns)
            ->___where($relation->foreign_key, $relatedIds, 'in')
            ->___get(null, true);

        $result = (object) [
            'related' => $relatedData,
            'pivot' => $pivotData
        ];

        $this->relationCache[$cacheKey] = $result;
        return $result;
    }

    private function processRelations($data)
    {
        if (empty($this->relations)) {
            return $data;
        }

        $isArray = is_array($data);
        $items = $isArray ? $data : [$data];

        foreach ($items as $item) {
            foreach ($this->relations as $relationTableName => $relation) {
                $relationKey = $relationTableName;

                if (!isset($item->$relationKey)) {
                    if (isset($relation->pivot_table)) {
                        // For belongsToMany relations
                        $includePivotColumns = !empty($relation->pivot_columns) && $relation->pivot_columns !== ['*'];

                        if ($includePivotColumns) {
                            // Option A: Return related data with pivot attached
                            $result = $this->loadManyToManyRelation($item, $relation);
                            // print_rpred($item,  $relation, $result);

                            // print_rpred($item, $relation);
                            $item->$relationKey = isset($relation->model) ? new ($relation->model)($result) : $result;
                        } else {
                            // Option B: Return structured object with both related and pivot
                            $result = $this->loadManyToManyRelationStructured($item, $relation);

                            // Option B1: Store both
                            $item->$relationKey = $result->related;

                            // Option B2: Or just store related
                            // $item->$relationKey = $result->related;
                        }
                    } else {
                        $result = $this->loadDirectRelation($item, $relation);

                        $item->$relationKey = $relation->model ? new ($relation->model)($result) : $result;
                    }
                }
            }
        }

        if (!$isArray) {
            return $items[0];
        }

        return $items;
    }

    // Helper method for loadDirectRelation (without parentTable parameter)
    private function loadDirectRelation($mainItem, $relation)
    {
        $cacheKey = $mainItem->id . '_' . $relation->table . '_' . $relation->type . '_' . $relation->mode;

        if (isset($this->relationCache[$cacheKey])) {
            return (clone $this)->relationCache[$cacheKey];
        }

        $builder = $this->___next()->___table($relation->table)->___select($relation->columns);

        if (isset($relation->constraint)) {
            ($relation->constraint)($builder);
        }

        // Handle different relation types
        switch ($relation->type) {
            case 'hasOne':
            case 'hasMany':
                $builder->___where($relation->foreign_key, $mainItem->{$relation->local_key});
                break;

            case 'belongsTo':
                $builder->___where($relation->foreign_key, $mainItem->{$relation->local_key});
                break;

            default:
                throw new LogicException("Unknown relation type: {$relation->type}");
        }

        $result = $relation->mode === 'one'
            ? $builder->___first(true)
            : $builder->___get(null, true);

        $this->relationCache[$cacheKey] = $result;
        return $result;
    }

    protected function resolveTable($modelOrTable): string
    {
        if (!is_string($modelOrTable) || !class_exists($modelOrTable)) {
            return $modelOrTable;
        }

        return (new $modelOrTable())->getTableName();
    }


    /**
     * where clause
     */
    protected function ___where($column, $value, string $operator = '='): self
    {
        $operator = strtoupper($operator);

        // Check if the column itself is a RawStatement
        $columnStr = $column instanceof RawStatement ? $column->__toString() : $column;

        if ($operator === 'BETWEEN' || $operator === 'NOT BETWEEN') {
            if (!is_array($value) || count($value) !== 2) {
                throw new \InvalidArgumentException('The BETWEEN operator requires an array with exactly two values.');
            }

            $first = $value[0] instanceof RawStatement ? $value[0]->__toString() : '?';
            $second = $value[1] instanceof RawStatement ? $value[1]->__toString() : '?';

            $this->query .= $this->whereOrAnd() . "{$columnStr} {$operator} {$first} AND {$second}";

            // Only add to bindings if not RawStatement
            foreach ($value as $val) {
                if (!($val instanceof RawStatement)) {
                    $this->bindings[] = $val;
                }
            }

            return (clone $this);
        }

        if (is_array($value)) {
            $placeholders = implode(', ', array_map(fn($item) => $item instanceof RawStatement ? $item->__toString() : '?', $value));
            $operatorUpper = strtoupper($operator);

            if ($operatorUpper === 'IN' || $operatorUpper === 'NOT IN') {
                $this->query .= $this->whereOrAnd() . "{$columnStr} {$operatorUpper} ({$placeholders})";
            } else {
                $this->query .= $this->whereOrAnd() . "{$columnStr} {$operator} ({$placeholders})";
            }

            // Only add non-RawStatement values to bindings
            $this->bindings = array_merge($this->bindings, array_filter($value, fn($val) => !($val instanceof RawStatement)));
            return (clone $this);
        }

        if ($value === null || strtolower((string)$value) === 'null') {
            $nullOperator = ($operator === '!=' || $operator === '<>') ? ' IS NOT NULL' : ' IS NULL';
            $this->query .= $this->whereOrAnd() . $columnStr . $nullOperator;
            return (clone $this);
        }

        if ($value instanceof RawStatement) {
            $this->query .= $this->whereOrAnd() . "{$columnStr} {$operator} " . $value->__toString();
            return (clone $this);
        }

        $this->query .= $this->whereOrAnd() . "{$columnStr} {$operator} ?";
        $this->bindings[] = $value;
        return (clone $this);
    }

    /**
     * Or where clause
     */
    protected function ___orWhere($column, $value, string $operator = '='): self
    {
        $this->query .= ' OR';

        $columnStr = $column instanceof RawStatement ? $column->__toString() : $column;

        if (is_array($value)) {
            $placeholders = implode(', ', array_map(fn($item) => $item instanceof RawStatement ? $item->__toString() : '?', $value));
            $this->query .= ' ' . $columnStr . ($operator === '!=' ? ' NOT IN' : ' IN') . " ({$placeholders})";

            $this->bindings = array_merge($this->bindings, array_filter($value, fn($val) => !($val instanceof RawStatement)));
            return (clone $this);
        }

        if ($value === null || strtolower((string)$value) === 'null') {
            $nullOperator = ($operator === '!=' ? ' IS NOT NULL' : ' IS NULL');
            $this->query .= " {$columnStr} {$nullOperator}";
            return (clone $this);
        }

        if ($value instanceof RawStatement) {
            $this->query .= " {$columnStr} {$operator} " . $value->__toString();
            return (clone $this);
        }

        $this->query .= " {$columnStr} {$operator} ?";
        $this->bindings[] = $value;
        return (clone $this);
    }

    /**
     * Find by primary key
     */
    protected function ___find($primaryKey)
    {
        return (clone $this)->___where($this->primary, $primaryKey)->___first();
    }

    /**
     * Find by primary key
     */
    protected function ___findOrFail($primaryKey)
    {
        if (! $model = $this->___where($this->primary, $primaryKey)->___first()) {
            $cls = static::class;
            throw new ModelNotFoundException("No query result from [{$cls}]: {$primaryKey}");
        }

        return $model;
    }

    /**
     * Insert data
     */
    protected function ___insert(array $data = []): self
    {
        $this->query = 'INSERT INTO ' . $this->table;
        $hasSetColumns = false;
        $placeholdersValues  = [];

        foreach ($data as $value) {
            $filtered = $this->filterColumns($value);

            if (empty($filtered)) {
                throw new \InvalidArgumentException('No valid columns to insert.');
            }

            if (!$hasSetColumns) {
                $columns = implode(', ', array_keys($value));
                $this->query .= " ({$columns}) VALUES";
                $hasSetColumns = true;
            }

            $placeholders = [];
            $values = [];

            foreach ($filtered as $value) {
                if ($value instanceof RawStatement) {
                    $placeholders[] = $value->__toString();
                } else {
                    $placeholders[] = '?';
                    $values[] = $value;
                }
            }

            $placeholdersValues[] = '(' . implode(', ', $placeholders) . ')';
            array_push($this->bindings, ...$values);
        }

        $this->query .= ' ' . implode(',', $placeholdersValues);
        $this->stmt = $this->pdo->prepare($this->query);
        $this->stmt->execute($this->bindings);

        $this->resetQuery();
        return $this;
    }

    protected function ___create(array $data): self
    {
        return (clone $this)->___insert([$data]);
    }

    /**
     * Get all records
     */
    protected function ___get($columns = null, bool $skipRelations = false)
    {
        try {
            $selectClause = $this->usedSelect ? '' : 'SELECT ' . $this->decideSelect($columns) . ' FROM ' . $this->table;
            $fullQuery = $selectClause . $this->query;

            $this->stmt = $this->pdo->prepare($fullQuery);
            $this->stmt->execute($this->bindings);
            $results = $this->stmt->fetchAll(\PDO::FETCH_OBJ) ?: [];

            $this->resetQuery();

            if (empty($results) || empty($this->relations) || $skipRelations) {
                return $results;
            }

            return $this->processRelations($results);
        } catch (\PDOException $e) {
            Debugger::dumpErr($e);
            return [];
        }
    }

    public function ___paginate(int $limit, int $page, bool $skipRelations = false)
    {
        try {

            $selectClause = $this->usedSelect ? '' : 'SELECT * FROM ' . $this->table;
            $offset = ($page - 1) * $limit;
            $fullQuery = $selectClause . $this->query . " LIMIT $limit OFFSET $offset";


            $this->stmt = $this->pdo->prepare($fullQuery);
            $this->stmt->execute($this->bindings);
            $results = $this->stmt->fetchAll(\PDO::FETCH_OBJ) ?: [];

            $this->resetQuery();

            if (empty($results) || empty($this->relations) || $skipRelations) {
                return $results;
            }

            return $this->processRelations($results);
        } catch (\PDOException $e) {
            Debugger::dumpErr($e);
            return [];
        }
    }

    /**
     * Get first record
     */
    protected function ___first(bool $skipRelations = false)
    {
        try {
            $selectClause = $this->usedSelect ? '' : 'SELECT ' . $this->decideSelect() . ' FROM ' . $this->table;
            $fullQuery = $selectClause . $this->query . ' LIMIT 1';

            $this->stmt = $this->pdo->prepare($fullQuery);
            $this->stmt->execute($this->bindings);
            $result = $this->stmt->fetch(\PDO::FETCH_OBJ);

            $this->resetQuery();

            if (!$result) {
                return null;
            }

            if (empty($this->relations) || $skipRelations) {
                return $result;
            }

            return $this->processRelations($result);
        } catch (\PDOException $e) {
            Debugger::dumpErr($e);
            return null;
        }
    }

    /**
     * Update data
     */
    protected function ___update(array $data = [], bool $ignoreWhereWarning = false): \PDOStatement
    {
        if (strpos($this->query, 'WHERE') === false && !$ignoreWhereWarning) {
            throw new \Exception('Missing WHERE clause for update operation.');
        }

        if ($ignoreWhereWarning) {
            error_log('WARNING: Ignoring WHERE clause, all records will be updated!');
        }

        $filtered = $this->filterColumns($data);

        if (empty($filtered)) {
            throw new \InvalidArgumentException('No valid columns to update.');
        }

        $setClause = [];
        $newBindings = [];

        foreach ($filtered as $key => $value) {
            if ($value instanceof RawStatement) {
                $setClause[] = $key . ' = ' . $value->__toString();
            } else {
                $setClause[] = $key . ' = ?';
                $newBindings[] = $value;
            }
        }

        $setString = implode(', ', $setClause);
        $fullQuery = 'UPDATE ' . $this->table . ' SET ' . $setString . $this->query;

        $this->bindings = array_merge($newBindings, $this->bindings);

        $this->stmt = $this->pdo->prepare($fullQuery);
        $this->stmt->execute($this->bindings);

        $this->resetQuery();
        return $this->stmt;
    }

    /**
     * Delete records
     */
    protected function ___delete(bool $ignoreWhereWarning = false): \PDOStatement
    {
        if (strpos($this->query, 'WHERE') === false && !$ignoreWhereWarning) {
            throw new \Exception('Missing WHERE clause for delete operation.');
        }

        if ($ignoreWhereWarning) {
            error_log('WARNING: Ignoring WHERE clause, all records will be deleted!');
        }

        $fullQuery = 'DELETE FROM ' . $this->table . $this->query;

        // error_log($this->getRequestIp() . $fullQuery);
        // error_log($this->getRequestIp() . 'Parameter: ' . json_encode($this->bindings));

        $this->stmt = $this->pdo->prepare($fullQuery);
        $this->stmt->execute($this->bindings);

        $this->resetQuery();
        return $this->stmt;
    }

    /**
     * Add limit clause
     */
    protected function ___limit(int $limitNumber): self
    {
        $this->query .= ' LIMIT ' . $limitNumber;
        return (clone $this);
    }

    /**
     * Add offset clause
     */
    protected function ___offset(int $offsetNumber): self
    {
        $this->query .= ' OFFSET ' . $offsetNumber;
        return (clone $this);
    }

    protected function ___rawExpr(string $expression): RawStatement
    {
        return RawStatement::raw($expression);
    }

    /**
     * Join tables
     */
    protected function ___join(string $table, string $ownerTableColumn, string $foreignKey, string $operator = '=', string $type = 'INNER'): self
    {
        $ownerTableColumnStr = $ownerTableColumn instanceof RawStatement ? $ownerTableColumn->__toString() : $ownerTableColumn;
        $foreignKeyStr = $foreignKey instanceof RawStatement ? $foreignKey->__toString() : $foreignKey;

        $this->query .= " {$type} JOIN {$table} ON {$ownerTableColumnStr} {$operator} {$foreignKeyStr}";
        return (clone $this);
    }

    /**
     * Order by clause
     */
    protected function ___orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'])) {
            throw new \InvalidArgumentException('Direction must be ASC or DESC');
        }

        // Handle RawStatement in order by
        $columnStr = $column instanceof RawStatement ? $column->__toString() : $column;

        $this->query .= " ORDER BY {$columnStr} {$direction}";
        return (clone $this);
    }

    /**
     * Group by clause
     */
    protected function ___groupBy(string $column): self
    {
        $columnStr = $column instanceof RawStatement ? $column->__toString() : $column;

        $this->query .= " GROUP BY {$columnStr}";
        return (clone $this);
    }

    protected function ___restrain(bool $state): self
    {
        $this->restrain = $state;
        return (clone $this);
    }

    /**
     * Raw SQL query
     */
    protected function ___raw(string $sql, array $bindings = []): self
    {
        $this->query .= $sql . ' ';
        $this->bindings = array_merge($this->bindings, $bindings);
        return (clone $this);
    }

    /**
     * Execute raw query
     */
    protected function ___execute(): \PDOStatement
    {
        $upperQuery = strtoupper($this->query);

        if ($this->restrain && str_contains($upperQuery, 'DROP')) {
            throw new \Exception('DROP action detected, aborted unless explicitly restrain set to false.');
        }

        if (
            $this->restrain && (str_contains($upperQuery, 'DELETE') || str_contains($upperQuery, 'UPDATE'))
            && !str_contains($upperQuery, 'WHERE')
        ) {
            throw new \Exception('DELETE/UPDATE action without WHERE clause detected, aborted unless explicitly restrain set to false.');
        }

        // error_log($this->getRequestIp() . $this->query);
        // error_log($this->getRequestIp() . 'Parameter: ' . json_encode($this->bindings));

        $this->stmt = $this->pdo->prepare($this->query);
        $this->stmt->execute($this->bindings);

        $this->resetQuery();
        return $this->stmt;
    }

    /**
     * Fetch one result
     */
    protected function ___fetch(): object|false
    {
        return (clone $this)->stmt->fetch(\PDO::FETCH_OBJ);
    }

    /**
     * Fetch all results
     */
    protected function ___fetchAll(): array
    {
        return (clone $this)->stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Get last insert ID
     */
    protected function ___lastInsertId(): string|false
    {
        $id = $this->pdo->lastInsertId();
        return ($id === '0' || $id === false) ? false : $id;
    }

    /**
     * Close cursor
     */
    protected function ___close(): bool
    {
        return (clone $this)->stmt ? $this->stmt->closeCursor() : true;
    }

    /**
     * Clear all properties
     */
    protected function ___purge(): void
    {
        $this->query = '';
        $this->table = '';
        $this->columns = [];
        $this->fillable = [];
        $this->guarded = [];
        $this->primary = 'id';
        $this->usedSelect = false;
        $this->bindings = [];
        $this->stmt = null;
        $this->relations = [];
        $this->related = [];
        $this->isFetched = false;
        $this->restrain = true;
        $this->relationCache = [];
    }

    /**
     * Reset query builder state
     */
    private function resetQuery(): self
    {
        $this->query = '';
        $this->bindings = [];
        $this->usedSelect = false;
        $this->relationCache = [];
        return (clone $this);
    }

    /**
     * Get number of affected rows
     */
    protected function ___rowCount(): int
    {
        return (clone $this)->stmt ? $this->stmt->rowCount() : 0;
    }

    /**
     * Magic isset for relations
     */
    protected function _____isset($name)
    {
        return isset($this->relations->$name);
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        return $this->pdo->rollback();
    }
}

// Working simple relation