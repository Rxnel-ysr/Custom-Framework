<?php

declare(strict_types=1);

namespace App\Foundation\Database;

require_once 'Connection.php';

use App\Debug\Debugger;
use App\Foundation\Database\Connection;
use App\Foundation\Traits\Strings;
use InvalidArgumentException;
use LogicException;

class QueryBuilder___ extends Connection
{
    use Strings;

    # Base var
    public $query = '';
    protected $table = '';
    protected $columns = [];
    protected $fillable = [];
    protected $guarded = [];
    protected $primaryColumn = 'id';
    private $usedSelect = false;
    protected $bindings = [];
    protected $stmt;
    protected $pdo;
    private $isFetched = false;

    # Relations var
    private $related = [];
    public $relations = [];

    # Attributes
    protected $restrain = true;

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

    public function __call($name, $arguments)
    {
        return $this->{'___' . $name}(...$arguments);
    }

    public function ___next(): self
    {
        return new self();
    }

    public function ___fillable(array $fillable = []): self
    {
        $this->fillable = $fillable;
        return $this;
    }

    public function ___guarded(array $guarded = []): self
    {
        $this->guarded = $guarded;
        return $this;
    }

    private function setIsFetched(): void
    {
        $this->isFetched = !$this->isFetched;
    }

    public function ___wrapWith($string)
    {
        $this->wrapper = $string;
    }

    private function wrap($queryResult)
    {
        return new $this->wrapper($queryResult);
    }

    public function getRelations()
    {
        return $this->relations;
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
    public function ___table(string $table, string $primaryColumn = 'id'): self
    {
        $this->table = $table;
        $this->primaryColumn = $primaryColumn;
        return $this;
    }

    /**
     * Select columns to retrieve.
     */
    public function ___select($columns = ['*']): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $columnsStr = implode(', ', $columns);
        $this->query = 'SELECT ' . $columnsStr . ' FROM ' . $this->table;
        $this->usedSelect = true;
        return $this;
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

    public function ___with(array $relations = []): self
    {
        foreach ($relations as $key => $value) {
            if (is_string($value)) {
                // Check if it's a nested relation
                if (strpos($value, '.') !== false) {
                    $this->loadNestedRelation($value);
                } else {
                    $this->loadRelationFromString($value);
                }
                continue;
            }

            if (is_callable($value)) {
                $this->loadRelationWithCallback($key, $value);
                continue;
            }

            throw new InvalidArgumentException("Relation [$key] must be string, callable.");
        }

        return $this;
    }

    protected function loadNestedRelation(string $nestedPath): void
    {
        $parts = explode('.', $nestedPath);
        $currentPath = '';
        $currentDepth = 0;

        foreach ($parts as $index => $part) {
            if ($currentDepth === 0) {
                // First level relation
                if ($index === count($parts) - 1) {
                    // Last part, load as regular relation
                    $this->loadRelationFromString($part);
                } else {
                    // Not last part, prepare for nesting
                    [$name, $cols] = array_pad(explode(':', $part, 2), 2, null);

                    if (!method_exists(static::class, $name)) {
                        throw new LogicException("Relation method [$name] does not exist.");
                    }

                    $rel = $this->{$name}();
                    $key = array_key_first($rel);
                    $def = $rel[$key];

                    $def->columns = $cols ? explode(',', $cols) : ['*'];
                    $def->nested = true; // Mark as nested relation
                    $def->depth = 0;
                    $def->path = $name;

                    // Store remaining path for later processing
                    $remainingPath = implode('.', array_slice($parts, $index + 1));
                    $def->nestedWith = [$remainingPath];

                    $this->relations[$key] = $def;
                    break; // Stop here, nested will be loaded in processRelations
                }
            }
        }
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
        $def->nested = false;
        $def->depth = 0;

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
        $def->nested = false;
        $def->depth = 0;

        $this->relations[$key] = $def;
    }

    public function belongsTo($modelOrTable, $foreignKey = null, $localKey = 'id', $name = null)
    {
        $name ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function']
            ?? throw new LogicException('Relation name cannot be resolved.');

        $foreignKey ??= rtrim($this->table, 's') . '_id';

        $model = $this->resolveTable($modelOrTable);
        
        return [
            $name => (object) [
                'type'          => 'belongsTo',
                'mode'          => 'one',
                'model'         => $model,
                'table'         => $model->getTableName(),
                'local_key'     => $localKey,
                'foreign_key'   => $foreignKey,
                'columns'       => ['*'],
                'nested'        => false,
                'depth'         => 0,
                'parent'        => null,
            ]
        ];
    }

    public function hasOne($modelOrTable, $foreignKey = null, $localKey = 'id', $name = null)
    {
        $name ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function']
            ?? throw new LogicException('Relation name cannot be resolved.');

        $foreignKey ??= rtrim($this->table, 's') . '_id';
        $model = $this->resolveTable($modelOrTable);

        return [
            $name => (object) [
                'type'          => 'hasOne',
                'mode'          => 'one',
                'model'         => $model,
                'table'         => $model->getTableName(),
                'local_key'     => $localKey,
                'foreign_key'   => $foreignKey,
                'columns'       => ['*'],
                'nested'        => false,
                'depth'         => 0,
                'parent'        => null,
            ]
        ];
    }

    public function hasMany($modelOrTable, $foreignKey = null, $localKey = 'id', $name = null)
    {
        $name ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function']
            ?? throw new LogicException('Relation name cannot be resolved.');

        $foreignKey ??= rtrim($this->table, 's') . '_id';
        $model = $this->resolveTable($modelOrTable);

        return [
            $name => (object) [
                'type'          => 'hasMany',
                'mode'          => 'many',
                'model'         => $model,
                'table'         => $model->getTableName(),                'local_key'     => $localKey,
                'foreign_key'   => $foreignKey,
                'columns'       => ['*'],
                'nested'        => false,
                'depth'         => 0,
                'parent'        => null,
            ]
        ];
    }

    public function belongsToMany(
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

        if ($pivotTable === null) {
            $tables = [rtrim($this->table, 's'), rtrim($this->resolveTable($modelOrTable), 's')];
            sort($tables);
            $pivotTable = implode('_', $tables);
        } else {
            $pivotTable = $this->resolveTable($pivotTable);
        }

        $foreignPivotKey ??= rtrim($this->table, 's') . '_id';
        $relatedPivotKey ??= rtrim($this->resolveTable($modelOrTable), 's') . '_id';
        $model = $this->resolveTable($modelOrTable);


        return [
            $name => (object) [
                'type'              => 'belongsToMany',
                'mode'              => 'many',
                'model'             => $model,
                'table'             => $model->getTableName(),                'local_key'         => $parentKey,
                'foreign_key'       => $relatedKey,
                'pivot_table'       => $pivotTable,
                'pivot_local_key'   => $foreignPivotKey,
                'pivot_foreign_key' => $relatedPivotKey,
                'pivot_columns'     => is_array($withPivot) ? $withPivot : [$withPivot],
                'return_type'       => $returnType,
                'pivot_data_key'    => $pivotDataKey,
                'columns'           => ['*'],
                'nested'            => false,
                'depth'             => 0,
                'parent'            => null,
            ]
        ];
    }

    private function loadManyToManyRelation($mainItem, $relation): array
    {
        $cacheKey = $relation->local_key . '_' . $mainItem->id . '_' . $relation->table . '_' .
            ($relation->pivot_table ?? '') . '_' . implode(',', $relation->pivot_columns ?? []);

        if (isset($this->relationCache[$cacheKey])) {
            return $this->relationCache[$cacheKey];
        }

        $includePivotColumns = !empty($relation->pivot_columns) && $relation->pivot_columns !== ['*'];

        $pivotSelect = [$relation->pivot_local_key, $relation->pivot_foreign_key];
        if ($includePivotColumns) {
            $pivotSelect = array_merge($pivotSelect, $relation->pivot_columns);
        }

        $pivotData = $this->___next()
            ->___table($relation->pivot_table)
            ->___select(array_unique($pivotSelect))
            ->___where($relation->pivot_local_key, $mainItem->{$relation->local_key})
            ->___get(true);

        if (empty($pivotData)) {
            $this->relationCache[$cacheKey] = [];
            return [];
        }

        $relatedIds = array_column($pivotData, $relation->pivot_foreign_key);

        $relatedData = $this->___next()
            ->___table($relation->table)
            ->___select($relation->columns)
            ->___where($relation->foreign_key, $relatedIds, 'in')
            ->___get(true);

        $relatedMap = [];
        foreach ($relatedData as $item) {
            $relatedMap[$item->{$relation->foreign_key}] = $item;
        }

        $result = [];

        foreach ($pivotData as $pivotRow) {
            $relatedId = $pivotRow->{$relation->pivot_foreign_key};

            if (!isset($relatedMap[$relatedId])) {
                continue;
            }

            $relatedItem = clone $relatedMap[$relatedId];

            if ($includePivotColumns) {
                $pivotAttributes = [];
                foreach ($relation->pivot_columns as $column) {
                    if (isset($pivotRow->{$column})) {
                        $pivotAttributes[$column] = $pivotRow->{$column};
                    }
                }

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

    private function loadManyToManyRelationStructured($mainItem, $relation): object
    {
        $cacheKey = $relation->local_key . '_' . $mainItem->id . '_' . $relation->table . '_' .
            ($relation->pivot_table ?? '') . '_structured';

        if (isset($this->relationCache[$cacheKey])) {
            return $this->relationCache[$cacheKey];
        }

        $pivotSelect = [$relation->pivot_local_key, $relation->pivot_foreign_key];
        $includePivotColumns = !empty($relation->pivot_columns) && $relation->pivot_columns !== ['*'];

        if ($includePivotColumns) {
            $pivotSelect = array_merge($pivotSelect, $relation->pivot_columns);
        }

        $pivotData = $this->___next()
            ->___table($relation->pivot_table)
            ->___select(array_unique($pivotSelect))
            ->___where($relation->pivot_local_key, $mainItem->{$relation->local_key})
            ->___get(true);

        if (empty($pivotData)) {
            $result = (object) [
                'related' => [],
                'pivot' => []
            ];
            $this->relationCache[$cacheKey] = $result;
            return $result;
        }

        $relatedIds = array_column($pivotData, $relation->pivot_foreign_key);

        $relatedData = $this->___next()
            ->___table($relation->table)
            ->___select($relation->columns)
            ->___where($relation->foreign_key, $relatedIds, 'in')
            ->___get(true);

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
                        $includePivotColumns = !empty($relation->pivot_columns) && $relation->pivot_columns !== ['*'];

                        if ($includePivotColumns) {
                            $item->$relationKey = $this->loadManyToManyRelation($item, $relation);
                        } else {
                            $result = $this->loadManyToManyRelationStructured($item, $relation);
                            $item->$relationKey = $result->related;
                        }
                    } else {
                        $item->$relationKey = $this->loadDirectRelation($item, $relation);
                    }
                }

                // Handle nested relations
                if (isset($relation->nestedWith) && !empty($relation->nestedWith)) {
                    foreach ($relation->nestedWith as $nestedPath) {
                        $this->loadNestedRelationsForItem($item->$relationKey, $nestedPath, $relation);
                    }
                }
            }
        }

        if (!$isArray) {
            return $items[0];
        }

        return $items;
    }

    private function loadNestedRelationsForItem($relatedItems, string $nestedPath, $parentRelation)
    {
        if (!$relatedItems) {
            return;
        }

        $isArray = is_array($relatedItems);
        $items = $isArray ? $relatedItems : [$relatedItems];

        // Parse the nested path
        $parts = explode('.', $nestedPath);
        $currentRelationName = $parts[0];
        $remainingPath = count($parts) > 1 ? implode('.', array_slice($parts, 1)) : null;

        foreach ($items as $item) {
            if (!$item) continue;

            // Create a new query builder for the related item's context
            $relatedBuilder = $this->___next()->___table($parentRelation->table);

            // Check if the related item has the relation method
            if (!method_exists($relatedBuilder, $currentRelationName)) {
                throw new LogicException("Nested relation method [$currentRelationName] does not exist on " . $parentRelation->table);
            }

            // Get the relation definition
            $nestedRelation = $relatedBuilder->{$currentRelationName}();
            $nestedKey = array_key_first($nestedRelation);
            $nestedDef = $nestedRelation[$nestedKey];

            // Load the immediate nested relation
            $item->$nestedKey = $this->loadNestedDirectRelation($item, $nestedDef, $parentRelation);

            // If there's more nested path, continue recursively
            if ($remainingPath && $item->$nestedKey) {
                if (is_array($item->$nestedKey)) {
                    foreach ($item->$nestedKey as $nestedItem) {
                        $this->loadNestedRelationsForItem($nestedItem, $remainingPath, $nestedDef);
                    }
                } else {
                    $this->loadNestedRelationsForItem($item->$nestedKey, $remainingPath, $nestedDef);
                }
            }
        }
    }

    private function loadNestedDirectRelation($mainItem, $relation, $parentRelation)
    {
        $cacheKey = 'nested_' . $parentRelation->table . '_' . $mainItem->id . '_' .
            $relation->table . '_' . $relation->type . '_' . $relation->mode;

        if (isset($this->relationCache[$cacheKey])) {
            return $this->relationCache[$cacheKey];
        }

        $builder = $this->___next()->___table($relation->table)->___select($relation->columns);

        // Handle different relation types
        switch ($relation->type) {
            case 'hasOne':
            case 'hasMany':
                // For nested relations, we need to find the correct foreign key
                $builder->___where($relation->foreign_key, $mainItem->{$parentRelation->foreign_key ?? 'id'});
                break;

            case 'belongsTo':
                $builder->___where($relation->local_key, $mainItem->{$relation->foreign_key});
                break;

            default:
                throw new LogicException("Unknown relation type: {$relation->type}");
        }

        $result = $relation->mode === 'one'
            ? $builder->___first(true)
            : $builder->___get(true);

        $this->relationCache[$cacheKey] = $result;
        return $result;
    }

    private function loadDirectRelation($mainItem, $relation)
    {
        $cacheKey = serialize($mainItem) . '_' . $relation->table . '_' . $relation->type . '_' . $relation->mode;

        if (isset($this->relationCache[$cacheKey])) {
            return $this->relationCache[$cacheKey];
        }

        $builder = $this->___next()->___table($relation->table)->___select($relation->columns);

        if (isset($relation->constraint)) {
            ($relation->constraint)($builder);
        }

        switch ($relation->type) {
            case 'hasOne':
            case 'hasMany':
                $builder->___where($relation->foreign_key, $mainItem->{$relation->local_key});
                break;

            case 'belongsTo':
                $builder->___where($relation->local_key, $mainItem->{$relation->foreign_key});
                break;

            default:
                throw new LogicException("Unknown relation type: {$relation->type}");
        }

        $result = $relation->mode === 'one'
            ? $builder->___first(true)
            : $builder->___get(true);

        $this->relationCache[$cacheKey] = $result;
        return $result;
    }

    protected function resolveTable($modelOrTable)
    {
        if (!is_string($modelOrTable) || !class_exists($modelOrTable)) {
            return $modelOrTable;
        }

        return new $modelOrTable();
    }

    /**
     * where clause
     */
    public function ___where($column, $value, string $operator = '='): self
    {
        $operator = strtoupper($operator);

        if ($operator === 'BETWEEN' || $operator === 'NOT BETWEEN') {
            if (!is_array($value) || count($value) !== 2) {
                throw new \InvalidArgumentException('The BETWEEN operator requires an array with exactly two values.');
            }

            $this->query .= $this->whereOrAnd() . $column . ' ' . $operator . ' ? AND ?';
            $this->bindings = array_merge($this->bindings, $value);
            return $this;
        }

        if (is_array($value)) {
            $placeholders = implode(', ', array_fill(0, count($value), '?'));
            $operatorUpper = strtoupper($operator);

            if ($operatorUpper === 'IN' || $operatorUpper === 'NOT IN') {
                $this->query .= $this->whereOrAnd() . $column . ' ' . $operatorUpper . ' (' . $placeholders . ')';
            } else {
                $this->query .= $this->whereOrAnd() . $column . ' ' . $operator . ' (' . $placeholders . ')';
            }

            $this->bindings = array_merge($this->bindings, $value);
            return $this;
        }

        if ($value === null || strtolower((string)$value) === 'null') {
            $nullOperator = ($operator === '!=' || $operator === '<>') ? ' IS NOT NULL' : ' IS NULL';
            $this->query .= $this->whereOrAnd() . $column . $nullOperator;
            return $this;
        }

        $this->query .= $this->whereOrAnd() . $column . ' ' . $operator . ' ?';
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Or where clause
     */
    public function ___orWhere($column, $value, string $operator = '='): self
    {
        $this->query .= ' OR';

        if (is_array($value)) {
            $placeholders = implode(', ', array_fill(0, count($value), '?'));
            $this->query .= ' ' . $column . ($operator === '!=' ? ' NOT IN' : ' IN') . ' (' . $placeholders . ')';
            $this->bindings = array_merge($this->bindings, $value);
            return $this;
        }

        if ($value === null || strtolower((string)$value) === 'null') {
            $nullOperator = ($operator === '!=' ? ' IS NOT NULL' : ' IS NULL');
            $this->query .= ' ' . $column . $nullOperator;
            return $this;
        }

        $this->query .= ' ' . $column . ' ' . $operator . ' ?';
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Find by primary key
     */
    public function ___find($primaryKey)
    {
        return $this->___where($this->primaryColumn, $primaryKey)->___first();
    }

    /**
     * Insert data
     */
    public function ___insert(array $data = []): self
    {
        $filtered = $this->filterColumns($data);

        if (empty($filtered)) {
            throw new \InvalidArgumentException('No valid columns to insert.');
        }

        $columns = implode(', ', array_keys($filtered));
        $placeholders = implode(', ', array_fill(0, count($filtered), '?'));

        $this->query = 'INSERT INTO ' . $this->table . ' (' . $columns . ') VALUES (' . $placeholders . ')';
        $this->bindings = array_values($filtered);

        $this->stmt = $this->pdo->prepare($this->query);
        $this->stmt->execute($this->bindings);

        $this->resetQuery();
        return $this;
    }

    public function ___create(array $data): self
    {
        return $this->___insert($data);
    }

    /**
     * Get all records
     */
    public function ___get($columns = null, bool $skipRelations = false)
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
            return null;
        }
    }

    /**
     * Get first record
     */
    public function ___first(bool $skipRelations = false)
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
     * Find by primary key and fetch
     */
    public function ___fetchWherePrimary($primaryKey, bool $skipRelations = false)
    {
        $this->___where($this->primaryColumn, $primaryKey);
        return $this->___first($skipRelations);
    }

    /**
     * Update data
     */
    public function ___update(array $data = [], bool $ignoreWhereWarning = false): \PDOStatement
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
        foreach (array_keys($filtered) as $key) {
            $setClause[] = $key . ' = ?';
        }

        $setString = implode(', ', $setClause);
        $fullQuery = 'UPDATE ' . $this->table . ' SET ' . $setString . $this->query;

        $this->bindings = array_merge(array_values($filtered), $this->bindings);

        $this->stmt = $this->pdo->prepare($fullQuery);
        $this->stmt->execute($this->bindings);

        $this->resetQuery();
        return $this->stmt;
    }

    /**
     * Delete records
     */
    public function ___delete(bool $ignoreWhereWarning = false): \PDOStatement
    {
        if (strpos($this->query, 'WHERE') === false && !$ignoreWhereWarning) {
            throw new \Exception('Missing WHERE clause for delete operation.');
        }

        if ($ignoreWhereWarning) {
            error_log('WARNING: Ignoring WHERE clause, all records will be deleted!');
        }

        $fullQuery = 'DELETE FROM ' . $this->table . $this->query;

        $this->stmt = $this->pdo->prepare($fullQuery);
        $this->stmt->execute($this->bindings);

        $this->resetQuery();
        return $this->stmt;
    }

    /**
     * Add limit clause
     */
    public function ___limit(int $limitNumber): self
    {
        $this->query .= ' LIMIT ' . $limitNumber;
        return $this;
    }

    /**
     * Add offset clause
     */
    public function ___offset(int $offsetNumber): self
    {
        $this->query .= ' OFFSET ' . $offsetNumber;
        return $this;
    }

    /**
     * Join tables
     */
    public function ___join(string $table, string $ownerTableColumn, string $foreignKey, string $operator = '=', string $type = 'INNER'): self
    {
        $this->query .= ' ' . $type . ' JOIN ' . $table . ' ON ' . $ownerTableColumn . ' ' . $operator . ' ' . $foreignKey;
        return $this;
    }

    /**
     * Order by clause
     */
    public function ___orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'])) {
            throw new \InvalidArgumentException('Direction must be ASC or DESC');
        }

        $this->query .= ' ORDER BY ' . $column . ' ' . $direction;
        return $this;
    }

    /**
     * Group by clause
     */
    public function ___groupBy(string $column): self
    {
        $this->query .= ' GROUP BY ' . $column;
        return $this;
    }

    public function ___restrain(bool $state): self
    {
        $this->restrain = $state;
        return $this;
    }

    /**
     * Raw SQL query
     */
    public function ___raw(string $sql, array $bindings = []): self
    {
        $this->query .= $sql . ' ';
        $this->bindings = array_merge($this->bindings, $bindings);
        return $this;
    }

    /**
     * Execute raw query
     */
    public function ___execute(): \PDOStatement
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

        $this->stmt = $this->pdo->prepare($this->query);
        $this->stmt->execute($this->bindings);

        $this->resetQuery();
        return $this->stmt;
    }

    /**
     * Fetch one result
     */
    public function ___fetch(): object|false
    {
        return $this->stmt->fetch(\PDO::FETCH_OBJ);
    }

    /**
     * Fetch all results
     */
    public function ___fetchAll(): array
    {
        return $this->stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Get last insert ID
     */
    public function ___lastInsertId(): string|false
    {
        $id = $this->pdo->lastInsertId();
        return ($id === '0' || $id === false) ? false : $id;
    }

    /**
     * Close cursor
     */
    public function ___close(): bool
    {
        return $this->stmt ? $this->stmt->closeCursor() : true;
    }

    /**
     * Clear all properties
     */
    public function ___purge(): void
    {
        $this->query = '';
        $this->table = '';
        $this->columns = [];
        $this->fillable = [];
        $this->guarded = [];
        $this->primaryColumn = 'id';
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
        return $this;
    }

    /**
     * Get number of affected rows
     */
    public function ___rowCount(): int
    {
        return $this->stmt ? $this->stmt->rowCount() : 0;
    }

    /**
     * Magic isset for relations
     */
    public function _____isset($name)
    {
        return isset($this->relations->$name);
    }

    /**
     * Get table name for the current model
     */
    public function getTableName(): string
    {
        return $this->table;
    }
}
