<?php

declare(strict_types=1);

namespace App\Foundation\Database;

require_once 'Connection.php';

use App\Debug\Debugger;
use App\Foundation\Database\Connection;
use App\Foundation\Traits\Strings;
use InvalidArgumentException;
use LogicException;

class QueryBuilder extends Connection
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
    private $wrapper;

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

    // In QueryBuilder.php, add a getRelations method:
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

    public function ___with(string|array $relations = [], string ...$moreRelation): self
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

        return $this;
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

    public function resolveModel($modelOrTable)
    {
        if (!is_string($modelOrTable) || !class_exists($modelOrTable)) {
            return null;
        }

        return new $modelOrTable();
    }


    public function belongsTo($modelOrTable, $localKey = null, $foreignKey = 'id', $name = null)
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

    public function hasOne($modelOrTable, $foreignKey = null, $localKey = 'id', $name = null)
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

    public function hasMany($modelOrTable, $foreignKey = null, $localKey = 'id', $name = null)
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
            return $this->relationCache[$cacheKey];
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
            ->___get(null,true);

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
            ->___get(null,true);

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
            return $this->relationCache[$cacheKey];
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
            ->___get(null,true);

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
            ->___get(null,true);

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
            return $this->relationCache[$cacheKey];
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
            : $builder->___get(null,true);

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

        // error_log($this->getRequestIp() . $this->query);
        // error_log($this->getRequestIp() . 'Parameter: ' . json_encode($this->bindings));

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

            // error_log($this->getRequestIp() . $fullQuery);
            // error_log($this->getRequestIp() . 'Parameter: ' . json_encode($this->bindings));

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

            // error_log($this->getRequestIp() . $fullQuery);
            // error_log($this->getRequestIp() . 'Parameter: ' . json_encode($this->bindings));

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

        // error_log($this->getRequestIp() . $fullQuery);
        // error_log($this->getRequestIp() . 'Parameter: ' . json_encode($this->bindings));
        // dd($fullQuery);

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
}

// Working simple relation