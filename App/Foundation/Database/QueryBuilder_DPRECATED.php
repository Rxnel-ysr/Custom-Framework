<?php

declare(strict_types=1);

namespace DEPRECATED\Experimental\App\Foundation\Database;

require_once 'Connection.php';

use App\Debug\Debugger;
use App\Foundation\Database\Connection;
use App\Foundation\Traits\Strings;
use stdClass;

class QueryBuilder2_ extends Connection
{
    use Strings;

    # Base var
    private $query;
    protected $table;
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

    /**
     * Query constructor, reuses the singleton PDO connection.
     */
    public function __construct()
    {
        $this->pdo = Connection::getInstance();
    }

    public function next()
    {
        return new self();
    }

    public function fillable($fillable = [])
    {
        $this->fillable = $fillable;
    }

    public function guarded($guarded = [])
    {
        $this->guarded = $guarded;
    }

    private function setIsFetched()
    {
        $this->isFetched = !$this->isFetched;
    }

    /**
     * Get ip of person or thing that made the query.
     */
    private static function getRequestIp()
    {
        return '[' . $_SERVER['REMOTE_ADDR'] . ']:';
    }

    /**
     * Determine to use WHERE or AND in query.
     * 
     * @return string WHERE or AND
     */
    private function whereOrAnd()
    {
        return (strpos($this->query ?? '', 'WHERE') === false ? ' WHERE' : ' AND') . ' ';
    }

    /**
     * 
     */
    private function decideSelect(?array $columns = null)
    {
        return empty($columns) ? (!empty($this->columns) ? implode(', ', $this->columns) : '*') : implode(',', $columns);
    }

    protected function filterColumns($columns = [])
    {
        return array_diff_key(array_intersect_key($columns, array_flip($this->fillable)), array_flip($this->guarded));
    }

    /**
     * The very first method you'll use to specify the table, or the query just won't work.
     * 
     */
    public function table($table, $primaryColumn = 'id'): self
    {
        $this->table = $table;
        $this->primaryColumn = $primaryColumn;
        return $this;
    }

    /**
     * To avoid overwhelming our potato server, if don't need all the data fetched from query, use this, I recommended it.
     * 
     * @param array $columns Mention each columns to be selected
     */
    public function select($columns = ['*']): self
    {
        $columns = implode(', ', $columns ?? $this->columns ?? ['*']);
        $this->query = 'SELECT ' . $columns . ' FROM ' . $this->table;
        $this->usedSelect = true;
        return $this;
    }

    /**
     * Switches between "id_<name>" and "<name>_id" formats.
     *
     * This function checks the input pattern and switches it to the opposite format.
     * - If the input matches "id_<name>", it converts to "<name>_id".
     * - If the input matches "<name>_id", it converts to "id_<name>".
     *
     * @param string $column The column name to switch.
     * 
     * @return string The switched column name.
     */
    private function switchColumnPattern(string $column): string
    {
        // Match 'id_<name>'
        if (preg_match('/^id_(\w+)$/', $column, $matches)) {
            return "{$matches[1]}_id";
        }

        // Match '<name>_id'
        if (preg_match('/^(\w+)_id$/', $column, $matches)) {
            return 'id_' . $matches[1];
        }

        // Return the original column if no pattern matches
        return $column;
    }

    /**
     * Define and handle relations in your table.
     *
     * This method allows you to specify relations for your table in a structured format:
     * 
     * `['type.mode.table']`
     * 
     * ### Parameters:
     * - `type`: Defines the relationship type. Available options:
     *   - `'has'`: Indicates that the current table "has" a related table.
     *   - `'belongsTo'`: Indicates that the current table "belongs to" another table.
     * 
     * - `mode`: Defines the cardinality of the relationship. Available options:
     *   - `'one'`: Represents a one-to-one relationship.
     *   - `'many'`: Represents a one-to-many relationship.
     * 
     * - `table`: Specifies the name of the related table.
     * 
     * ### Extended Selection (optional):
     * - You can refine the selection of columns from the related table by appending column names after a colon (`:`).
     * - Example: `'has.one.users:id.name.email'`
     *   - Type: `'has'`
     *   - Mode: `'one'`
     *   - Table: `'users'`
     *   - Selected Columns: `'id', 'name', 'email'`
     * 
     * ### Optional Features:
     * - You can further define pivot tables or foreign key columns for many-to-many relationships.
     * - Example: `['belongsTo.many.tags:id.name:post_tag']`
     *   - Type: `'belongsTo'`
     *   - Mode: `'many'`
     *   - Table: `'tags'`
     *   - Columns to be selected in `Table`: `'id','name'`
     *   - Pivot table `'post_tag'`
     * 
     *  `Note`: Type will be ignored if you setting pivot table.
     * 
     * ### Usage Example:
     * ```php
     * $object->with([
     *     'has.one.users:id.name',
     *     'belongsTo.many.posts',
     * ]);
     * ```
     * test
     * ### Behavior:
     * - The method processes the provided relations and stores them in the `relations` property as an object.
     * - Each relation is represented as an object containing:
     *   - `type` (string): The relationship type (`has` or `belongsTo`).
     *   - `mode` (string): The cardinality (`one` or `many`).
     *   - `columns` (array): The columns to be selected from the related table.
     *   - `foreign_key_column` (string, optional): The foreign key column used for many-to-many relationships.
     *   - `pivot_table` (string, optional): The pivot table for many-to-many relationships.
     * 
     * The person who make this is sick when he do it, let appreciated his Masochism.
     * 
     * Just remember to name foreign key column to like `<name>_id`, or `id_<name>` but not in completely different name, this code is not sentient enough to do that.
     * 
     * @param array $relations List of relations in the defined format.
     * @param bool $allowBruteForceSearching Set this to if you permitted brute force to search column name pattern
     * @return self Fluent interface for method chaining.
     */
    public function with($relations = []): self
    {
        $result = new stdClass();
        foreach ($relations as $relation) {
            $relationSelection = explode(':', $relation);
            $parts = explode('.', $relationSelection[0]);
            $columns =  isset($relationSelection[1])
                ? explode('.', $relationSelection[1])
                : ['*'];

            $relation = new stdClass();
            $relation->type = $parts[0] ?? null;
            $relation->mode = $parts[1] ?? null;

            // if left-side condition true, so the right-side will be executed, otherwise it will skip 
            // I like over-complicated things, but I have goals
            isset($parts[3]) && $relation->foreign_key_column = $parts[3];
            isset($parts[4]) && $relation->primary_key_column = $parts[4];
            isset($relationSelection[2]) && $relation->pivot_table = $relationSelection[2];

            $relation->columns = $columns;

            $result->{$parts[2]} = $relation;
        }

        $this->relations = $result;
        return $this;
    }

    // // SELECT table
    // private function table($table)
    // {
    //     $this->query = "SELECT * FROM $table";
    //     return $this;
    // }

    /**
     * where clause for all the method, you'll need to use this often if you're a backend, or else you'll be cooked
     * 
     * @param string $column
     * @param mixed|array $value Value to match in column
     * @param string $operator Defaulted to '='
     */
    public function where($column, $value, $operator = '='): self
    {
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

            $this->query .= $this->whereOrAnd() . $column . ($operator == '!=' ? ' NOT IN' : ' IN') . ' (' . $placeholders . ')';
            $this->bindings = array_merge($this->bindings, $value);
            return $this;
        }

        if ($value == null || strtolower($value) == 'null') {
            $this->query .= $this->whereOrAnd() . $column . ($operator == '!=' ? ' IS NOT NULL' : ' IS NULL');
            return $this;
        }

        $this->query .= $this->whereOrAnd() .  $column . ' ' . $operator . ' ?';
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Or where clause for all the method, addition to where()
     * 
     * @param string $column
     * @param mixed|array $value Value to match in column
     * @param string $operator Defaulted to '='
     */
    public function orWhere($column, $value, $operator = '=')
    {
        $this->query .= ' OR';

        if ($operator === 'BETWEEN' || $operator === 'NOT BETWEEN') {
            if (!is_array($value) || count($value) !== 2) {
                throw new \InvalidArgumentException('The BETWEEN operator requires an array with exactly two values.');
            }

            $this->query .= ' ' . $column . ' ' . $operator . ' ? AND ?';
            $this->bindings = array_merge($this->bindings, $value);
            return $this;
        }

        if (is_array($value)) {
            $placeholders = implode(', ', array_fill(0, count($value), '?'));

            $this->query .= ' ' . $column . ($operator == '!=' ? ' NOT IN' : ' IN') . ' (' . $placeholders . ')';
            $this->bindings = array_merge($this->bindings, $value);
            return $this;
        }

        if ($value == null || strtolower($value) == 'null') {
            $this->query .= ' ' . $column . ($operator == '!=' ? ' IS NOT NULL' : ' IS NULL');
            return $this;
        }

        $this->query .=  ' ' . $column . ' ' . $operator . ' ?';
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Dedicated to only find row matched given primary key
     * 
     * @param int $primaryKey Primary key to search
     * 
     * @return self|false Will return an object or false if there is no match found
     */
    public function find($primaryKey)
    {
        $this->query .= $this->whereOrAnd() . $this->primaryColumn . ' = ?';
        $this->bindings[] = $primaryKey;
        error_log($this->getRequestIp() . $this->query);
        error_log($this->getRequestIp() . 'Parameter: ' . json_encode($this->bindings));
        return $this;
    }

    /**
     * Friendly reminder, you need to add table() before this, so that all these method will work or you can just make new class extending this class
     * @param array $data `['column' => 'value']`
     */
    public function insert($data = []): self
    {
        $filtered = $this->filterColumns($data);

        $columns = implode(', ', array_keys($filtered));
        $placeholders = implode(', ', array_fill(0, count($filtered), '?'));

        $this->query = 'INSERT INTO ' . $this->table . ' (' . $columns . ') VALUES (' . $placeholders . ')';
        $this->bindings = array_values($data);
        error_log($this->query);
        error_log(json_encode($this->bindings));
        $this->stmt = $this->pdo->prepare($this->query);
        $this->stmt->execute($this->bindings);
        return $this;
    }

    /**
     * Get all record within array and inside array there is all of object from query
     * 
     */
    public function get($columns = null, $skipRelations = false)
    {
        try {
            $query = ($this->usedSelect ? '' : 'SELECT ') . $this->decideSelect($columns) . ' FROM ' . $this->table;
            // if (!empty($this->relations) || !$skipRelations) {
            //     foreach ($this->relations as $table => $relation) {
            //     }
            // }

            error_log($this->getRequestIp() . $query . $this->query);
            error_log($this->getRequestIp() . 'Parameter: ' . json_encode($this->bindings));

            $this->stmt = $this->pdo->prepare($query . $this->query);
            $this->stmt->execute($this->bindings);
            $this->resetQuery();

            return new static($this->stmt->fetchAll(\PDO::FETCH_OBJ) ?: []);
        } catch (\PDOException $e) {
            Debugger::dumpErr($e);
            return [];
        }
    }

    /**
     * Get the very first record in order as an object
     */
    public function first($skipRelations = false)
    {
        try {
            $query = $this->usedSelect ? '' : 'SELECT ' . $this->decideSelect() . ' FROM ' . $this->table;
            $this->query .= ' LIMIT 1';
            error_log($this->getRequestIp() . $query . $this->query);
            error_log($this->getRequestIp() . 'Parameter: ' . json_encode($this->bindings));

            $this->stmt = $this->pdo->prepare($query . $this->query);
            $this->stmt->execute($this->bindings);
            $this->resetQuery();

            $main_body = $this->stmt->fetch(\PDO::FETCH_OBJ);
            if (!$main_body) {
                return null;
            }

            if (empty($this->relations) || $skipRelations) {
                $data = new static($main_body);
                $data->setIsFetched();
                return $data;
            }

            return $this->processRelations($main_body);
        } catch (\PDOException $e) {
            Debugger::dumpErr($e);
            return null;
        }
    }

    /**
     * Dedicated to only find and fetch row matched given primaryKey
     * 
     * @param int $primaryKey Primary key primaryKey to search
     * 
     * @return object|false Will return an object or false if there is no match found
     */
    public function fetchWherePrimary($primaryKey, $skipRelations = false)
    {
        $query = $this->usedSelect ? '' : 'SELECT ' . $this->decideSelect() . ' FROM ' . $this->table;
        $this->query .= $this->whereOrAnd() . $this->primaryColumn . ' = ?';
        $this->bindings[] = $primaryKey;
        error_log($this->getRequestIp() . $query . $this->query);
        error_log($this->getRequestIp() . 'Parameter: ' . json_encode($this->bindings));

        $this->stmt = $this->pdo->prepare($query . $this->query);
        $this->stmt->execute($this->bindings);

        $user_data = $this->stmt->fetch(\PDO::FETCH_OBJ);
        if (!$user_data) {
            return false;
        }
        return (!empty($this->relations) && !$skipRelations) ? $this->processRelations($user_data) : new static($user_data);
    }


    /**
     * Same like delete(), don't forget to add where() before this
     * 
     * @param array $data Data that will be updated in database, accept a key value pairs array, example:
     * 
     *  ```php
     * $object->table('user')->find(1)->update(["username" => "new_username"]);
     * ```
     * 
     * That will select id `1` at table `user` and update column `username` with `new_username`
     * 
     * @param array $data `['column' => 'value']`
     * @param bool $ignoreWhereWarning [optional]
     * 
     * Set this to `true` to bypass where warning and if you `EXTREMELY` aware and have `CONSENT` of what you wanna do.
     * 
     */
    public function update($data = [], $ignoreWhereWarning = false)
    {
        if (strpos($this->query, 'WHERE') === false && !$ignoreWhereWarning) {
            error_log('You need to specify what to update in ' . $this->table . ' table, else you\'ll update everything');
            throw new \Exception("Missing where clause");
        }
        if ($ignoreWhereWarning) {
            error_log('WARNING: Ignoring WHERE clause, all records will be updated!');
        }
        $filtered = $this->filterColumns($data);
        $set = [];
        foreach (array_keys($filtered) as $key) {
            $set[] = $key . ' = ?';
        }
        $this->bindings = array_merge(array_values($filtered), $this->bindings);
        $query = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $set);
        error_log($this->getRequestIp() . $query . $this->query);
        error_log(json_encode($this->bindings));
        $this->stmt = $this->pdo->prepare($query . $this->query);
        $this->stmt->execute($this->bindings);
        return $this->stmt;
    }

    /**
     * This will delete everything if you are no specify which row to delete with where().
     * Anyways, be careful with this one, but I am not that careless, I'll put exception here just in case there is no where()
     * 
     * @param bool $ignoreWhereWarning Set this to `true` to bypass where warning and if you `EXTREMELY` aware of what you wanna do
     */
    public function delete($ignoreWhereWarning = false)
    {
        if (strpos($this->query, 'WHERE') === false && !$ignoreWhereWarning) {
            error_log('You need to specify what to delete in ' . $this->table . ' table, else you\'ll delete everything');
            throw new \Exception('Missing where clause');
        }
        if ($ignoreWhereWarning) {
            error_log('WARNING: Ignoring WHERE clause, all records will be deleted!');
        }
        $query = 'DELETE FROM ' . $this->table;
        error_log($this->getRequestIp() . $query . $this->query);
        error_log(json_encode($this->bindings));
        $this->stmt = $this->pdo->prepare($query . $this->query);
        $this->stmt->execute($this->bindings);
        return $this->stmt;
    }

    private function processRelations($data)
    {
        $parent_table = rtrim($this->table, 's');

        foreach ($this->relations as $relation_table_name => $relation) {
            if (isset($relation->pivot_table)) {
                $main_body = $data;
                // printAsJson($main_body);
                $main_body_ids = array_map(fn($body) => $body->id, $main_body);
                // echo "you are here";
                $related_foreign_key_column = $relation->foreign_key_column ?? rtrim($relation_table_name, 's') . '_id';

                $pivot_table_data = $this->table($relation->pivot_table)->where($relation->primary_key_column ?? $parent_table . '_id', $main_body_ids)->get(true);

                $related_table_ids = array_column($pivot_table_data, $related_foreign_key_column);;
                $related_data = $this->table($relation_table_name)->select($relation->columns)->where('id', $related_table_ids)->get(true);

                return $this->linkManyToManyRelation($main_body, $pivot_table_data, $related_data, $relation->primary_key_column ?? $parent_table . '_id', $related_foreign_key_column, $relation_table_name);
            } else {
                $parent_id_table = $this->primaryColumn ?? 'id';
                foreach ($data as $body) {
                    $array_or_object = is_array($data) ? $body : $data;

                    $query = $this->table($relation_table_name)
                        ->select($relation->columns);

                    if ($relation->type === 'has') {
                        $relationQuery = $query->where(
                            $relation->foreign_key_column ?? $parent_table . '_id',
                            $array_or_object->$parent_id_table
                        );
                    } else {
                        $parent_id_table = $relation->foreign_key_column ?? rtrim($relation_table_name, 's') . '_id';

                        $relationQuery = $query->where(
                            'id',
                            $array_or_object->$parent_id_table
                        );
                    }

                    $relation_data = ($relation->mode === 'one')
                        ? $relationQuery->first(true)
                        : $relationQuery->get(true);

                    if (is_array($data)) {
                        $body->{$relation_table_name} = $relation_data;
                    } else {
                        $this->related[$relation_table_name] = $relation_data;
                    }
                }
                if (is_array($data)) {
                    return array_map(function ($item) {
                        $item = new static($item);
                        $item->setIsFetched();
                        return $item;
                    }, $data);
                } else {
                    $data = new static((object)array_merge((array)$data, $this->related));
                    $data->setIsFetched();
                    return $data;
                }
            }
        }
    }

    private function linkManyToManyRelation(
        array $mainItems,      // Main dataset (e.g., posts)
        array $pivotTable, // Relation mappings (e.g., post_tag table)
        array $relatedItems,   // Related dataset (e.g., tags)
        string $mainKey,       // Key in pivotTable to link with mainItems (e.g., post_id)
        string $relationKey, // Key in pivotTable to link with relatedItems (e.g., tag_id)
        string $tableName = 'related'
    ): array {
        $linkedResult = []; // Final result to hold enriched main items

        foreach ($mainItems as $mainItem) {
            // Filter mappings for the current main item
            $filteredMappings = array_filter(
                $pivotTable,
                fn($mapping) => $mapping->$mainKey == $mainItem->id
            );

            // Map filtered relations to the actual related items
            $linkedRelations = array_map(function ($mapping) use ($relatedItems, $relationKey) {
                foreach ($relatedItems as $relatedItem) {
                    if ($relatedItem->id == $mapping->$relationKey) {
                        return $relatedItem; // Found related item
                    }
                }
            }, $filteredMappings);

            // Attach related items to the main item if they exist
            $enrichedItem = clone $mainItem;
            if (!empty($linkedRelations)) {
                $enrichedItem->$tableName = array_values(array_filter($linkedRelations));
            } else {
                $enrichedItem->$tableName = [];
            }

            $linkedResult[] = $enrichedItem; // Add enriched item to result
        }

        return $linkedResult;
    }

    private function objectLinkRelation(
        object $main,
        array $related,
        string $primaryKey,
        string $foreignKey,
        string $tableName = 'related'
    ) {
        // Filter related items that match the relationship
        $relatedItems = array_filter($related, function ($relation) use ($main, $primaryKey, $foreignKey) {
            return $relation->$foreignKey == $main->$primaryKey;
        });

        // Convert related items to an indexed array
        $relatedItems = array_values($relatedItems);

        // Clone the main object to prevent mutation
        $mainWithRelation = clone $main;

        // Add the related items under the specified relation name
        $mainWithRelation->$tableName = $relatedItems;

        return $mainWithRelation;
    }

    private function arrayLinkRelation(
        array $main,
        array $related,
        string $primaryKey,
        string $foreignKey,
        string $tableName = 'related'
    ) {
        $result = array_map(function ($mainItem) use ($related, $primaryKey, $foreignKey, $tableName) {
            $relatedItems = array_filter($related, function ($relatedItem) use ($mainItem, $primaryKey, $foreignKey) {
                return $relatedItem->$foreignKey == $mainItem->$primaryKey;
            });

            $mainItem->{$tableName} = array_values($relatedItems); // Add related items as 'posts'
            return (object)$mainItem; // Convert main item to \stdClass
        }, $main);

        return $result;
    }


    public function limit($limitNumber)
    {
        $this->query .= ' LIMIT ' . $limitNumber;
        return $this;
    }

    /**
     * I almost forgot how query works, I take quick asking to ChatGPt and now I remember, haha.
     * Available option for type is INNER, LEFT, and RIGHT. Defaulted to INNER
     * 
     * @param string $table
     * @param string $ownerTableColumn
     * @param string $foreignKey
     * @param string $operator defaulted to '='
     * @param string $type defaulted to INNER
     * 
     */
    public function join($table, $ownerTableColumn, $foreignKey, $operator = '=', $type = 'INNER'): self
    {
        $this->query .= ' ' . $type . ' JOIN ' . $table . ' ON ' . $ownerTableColumn . ' ' . $operator . ' ' . $foreignKey;
        return $this;
    }

    /**
     * This method is means to order result by ascending or descending order.
     * In case I forget I'll just put option here ASC/DESC ~Ronel
     * 
     * @param string $column
     * @param string $direction ['ASC','DESC']
     * 
     */
    public function orderBy($column, $direction = 'ASC'): self
    {
        $this->query .= ' ORDER BY ' . $column . ' ' . $direction;
        return $this;
    }

    public function restrain($state)
    {
        $this->restrain = $state;
        return $this;
    }

    /**
     * This method can be used with execute() to perform a raw sql query (Prepared for old but gold)
     * 
     * @param string $sql Sql query to be perform
     * @param array $bindings Use this if you prefer placeholder
     */
    public function raw($sql, $bindings = []): self
    {
        $this->query .= $sql . ' ';
        $this->bindings = array_merge($this->bindings, $bindings);
        return $this;
    }

    /**
     * This method was used there is no direct execution by insert() and update() now its deprecated.
     * You can just use others for regular CRUD operations, or you still can use it for raw() query to perform complex query
     *  @return void
     * 
     */
    public function execute(): void
    {
        $upperQuery = strtoupper($this->query);

        if ($this->restrain && str_contains($upperQuery, 'DROP')) {
            throw new \Exception('Drop action detected, aborted unless explicitly restrain set to false.');
        }

        if (
            $this->restrain &&
            (str_contains($upperQuery, 'DELETE') || str_contains($upperQuery, 'UPDATE')) &&
            !str_contains($upperQuery, 'WHERE')
        ) {
            throw new \Exception('Delete/Update action without where clause detected, aborted unless explicitly restrain set to false.');
        }
        error_log($this->getRequestIp() . $this->query);
        error_log(json_encode($this->bindings));
        $this->stmt = $this->pdo->prepare($this->query);
        $this->stmt->execute($this->bindings);
    }

    /**
     * Fetch one result from raw query.
     * Will return object if success or else null if there is none
     * 
     * @return object|null
     */
    public function fetch(): object|false
    {
        return $this->stmt->fetch(\PDO::FETCH_OBJ);
    }

    /**
     * Fetch all result from raw query.
     * Will always return Array, whatever, if there is no match, will return empty array
     */
    public function fetchAll(): array
    {
        return $this->stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * This thing first spawned to me when I leaning python sqlite query, hoo so all are same?
     * 
     * @return string|false Returned id of last successful query or false if no insert query were made 
     * 
     */
    public function lastInsertId(): string|false
    {
        return ($this->pdo->lastInsertId() == '0') ? false : $this->pdo->lastInsertId();
    }

    public function lastRowId(): string
    {
        return $this->stmt->lastRowId();
    }

    /**
     * I am not sure if this a correct way to closing a connection, this just set the pdo to null.
     * Update! now I know the correct way!
     * 
     * @return bool
     */
    public function close(): self
    {
        $this->pdo = null;
        return $this->stmt->closeCursor();
    }

    public function purge(): void
    {
        foreach ($this as $key => $value) {
            unset($this->$key);
        }
    }


    /**
     * Reset the query builder
     * 
     */
    private function resetQuery()
    {
        $this->query = '';
        $this->bindings = [];
        $this->usedSelect = false;
        return $this;
    }

    /**
     * Get numbers of affected row from previous query
     * 
     */
    public function rowCount()
    {
        return $this->stmt->rowCount();
    }
}
