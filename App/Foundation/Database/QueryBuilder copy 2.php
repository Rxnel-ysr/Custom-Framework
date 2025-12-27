<?php

declare(strict_types=1);

namespace App\Foundation\Database;

require_once 'Connection.php';

use App\Debug\Debugger;
use App\Foundation\Database\Connection;
use App\Foundation\Traits\Strings;
use InvalidArgumentException;
use LogicException;

class QueryBuilder2 extends Connection
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

    public function ___with(array $relations = []): self
    {
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

    protected function resolveModelOrTable($modelOrTable)
    {
        if (!is_string($modelOrTable) || !class_exists($modelOrTable)) {
            return ['isModel' => false, 'value' => $modelOrTable];
        }

        return ['isModel' => true, 'value' => new $modelOrTable()];
    }


    protected function loadRelationFromString(string $value): void
    {
        // Check if it's a nested relation (has dots)
        if (strpos($value, '.') !== false) {
            $this->loadNestedRelationFromString($value);
            return;
        }

        // Original code for single relation
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

    protected function loadNestedRelationFromString(string $value): void
    {
        [$relationsString, $cols] = array_pad(explode(':', $value, 2), 2, null);
        $relationChain = explode('.', $relationsString);

        if (count($relationChain) < 2) {
            throw new InvalidArgumentException("Nested relation must contain at least one dot (e.g., 'classroom.school')");
        }

        $rootRelationName = array_shift($relationChain);

        if (!method_exists(static::class, $rootRelationName)) {
            throw new LogicException("Relation method [$rootRelationName] does not exist.");
        }

        $rel = $this->{$rootRelationName}();
        $key = array_key_first($rel);
        $def = $rel[$key];

        // Store nested relation information
        $def->nested = $relationChain;
        $def->columns = $cols ? explode(',', $cols) : ['*'];

        $this->relations[$key] = $def;
    }

    protected function loadMultiRelationFromString(string $value)
    {
        $relations = explode('.', $value);
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

    public function plural(string $value)
    {
        return self::isPlural($value) ? $value : self::pluralize($value);
    }

    public function belongsTo($modelOrTable, $localKey = null, $foreignKey = 'id', $name = null)
    {
        $name ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function']
            ?? throw new LogicException('Relation name cannot be resolved.');

        $res = $this->resolveModelOrTable($modelOrTable);

        $localKey ??= ($res['isModel'] ? self::pluralToSingular($res['value']->getTableName()) : $res['value']) . '_id';

        return [
            $name => (object) [
                'type'          => 'belongsTo',
                'mode'          => 'one', // this belongs to that
                'table'         => $res,
                'local_key'     => $localKey,
                'foreign_key'   => $foreignKey,
                'name'          => $name,
                'columns'       => ['*'],
            ]
        ];
    }


    public function hasOne($modelOrTable, $foreignKey = null, $localKey = 'id', $name = null)
    {
        $name ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function']
            ?? throw new LogicException('Relation name cannot be resolved.');

        $res = $this->resolveModelOrTable($modelOrTable);

        $foreignKey ??= ($res['isModel'] ? self::pluralToSingular($res['value']->getTableName()) : $res['value']) . '_id';

        $res = $this->resolveModelOrTable($modelOrTable); // this has that
        return [
            $name => (object) [
                'type'          => 'hasOne',
                'mode'          => 'one',
                'table'         => $res,
                'local_key'     => $localKey,
                'foreign_key'   => $foreignKey ??= self::pluralToSingular($this->table) . '_id',
                'name'          => $name,
                'columns'       => ['*'],
            ]
        ];
    }

    public function hasMany($modelOrTable, $foreignKey = null, $localKey = 'id', $name = null)
    {
        $name ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function']
            ?? throw new LogicException('Relation name cannot be resolved.');

        $res = $this->resolveModelOrTable($modelOrTable);

        $res = $this->resolveModelOrTable($modelOrTable); // this has that
        return [
            $name => (object) [
                'type'          => 'hasOne',
                'mode'          => 'one',
                'table'         => $res,
                'local_key'     => $localKey,
                'foreign_key'   => $foreignKey ??= self::pluralToSingular($this->table) . '_id',
                'name'          => $name,
                'columns'       => ['*'],
            ]
        ];
    }

    public function belongsToMany(
        $modelOrTable,
        $pivotTable = null,
        $foreignPivotKey = null,
        $localPivotKey = null,
        $parentKey = 'id',
        $relatedKey = 'id',
        $name = null,
        $withPivot = [],
        $pivotDataKey = 'pivot'
    ) {
        $name ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function']
            ?? throw new LogicException('Relation name cannot be resolved.');

        $related = $this->resolveModelOrTable($modelOrTable);

        $pivot = $this->resolveModelOrTable($pivotTable);

        $foreignPivotKey ??= $related['isModel'] ? self::pluralToSingular($related['value']->getTableName()) : $related['value'];
        $localPivotKey  ??=  self::pluralToSingular($this->table);


        return [
            $name => (object) [
                'type'              => 'belongsToMany',
                'mode'              => 'many',

                'table'             => $related,
                'pivot_table'       => $pivot,

                'local_key'         => $parentKey,
                'foreign_key'       => $relatedKey,

                'pivot_local_key'   => $localPivotKey . '_id',
                'pivot_foreign_key' => $foreignPivotKey . '_id',

                'pivot_columns'     => is_array($withPivot) ? $withPivot : [$withPivot],
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
            ->___get(true);

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
            ->___get(true);

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

        // Get related data
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
                        // For belongsToMany relations
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

                // Handle nested relations if present
                if (isset($relation->nested) && !empty($relation->nested)) {
                    $this->loadNestedRelations($item, $relation);
                }
            }
        }

        if (!$isArray) {
            return $items[0];
        }

        return $items;
    }

    private function loadNestedRelations($parentItem, $parentRelation)
    {
        $relationKey = array_key_first([$parentRelation->name => $parentRelation]);

        // Ensure the parent relation is loaded
        if (!isset($parentItem->$relationKey)) {
            return;
        }

        $currentItems = is_array($parentItem->$relationKey)
            ? $parentItem->$relationKey
            : [$parentItem->$relationKey];

        $nestedRelations = $parentRelation->nested;

        foreach ($currentItems as $currentItem) {
            $this->processNestedRelationChain($currentItem, $nestedRelations);
        }
    }

    private function processNestedRelationChain($item, array $relationChain)
    {
        if (empty($relationChain)) {
            return;
        }

        $relationName = array_shift($relationChain);
        print_rpre($item, $relationChain);
        exit;

        // Check if the item has the relation method
        if (!method_exists($item, $relationName)) {
            throw new LogicException("Relation method [$relationName] does not exist on item.");
        }

        // Get the relation definition from the item
        $rel = $item->{$relationName}();
        $key = array_key_first($rel);
        $def = $rel[$key];

        // Load the relation
        $def->columns = ['*']; // You might want to handle columns differently for nested
        $loadedRelation = $this->loadDirectRelation($item, $def);

        // Set the loaded relation
        $item->$key = $loadedRelation;

        // Continue with remaining nested relations
        if (!empty($relationChain) && $loadedRelation) {
            $nextItems = is_array($loadedRelation) ? $loadedRelation : [$loadedRelation];

            foreach ($nextItems as $nextItem) {
                $this->processNestedRelationChain($nextItem, $relationChain);
            }
        }
    }


    private function loadDirectRelation($mainItem, $relation)
    {
        // $cacheKey =  $relation->table . '_' . $relation->type . '_' . $relation->mode;

        // if (isset($this->relationCache[$cacheKey])) {
        //     return $this->relationCache[$cacheKey];
        // }

        if ($relation->table['isModel']) {
            $builder = $relation->table['value']->___select($relation->columns);
        } else {
            $builder = $this->___next()->___table($relation->table['value'])->___select($relation->columns);
        }

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
            : $builder->___get(true);

        // $this->relationCache[$cacheKey] = $result;
        return $result;
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

            // if(strpos($fullQuery,'comment_id') !== null){
            //     print_rpre($fullQuery,debug_backtrace());
            //     exit;
            // }
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
