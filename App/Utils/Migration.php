<?php

namespace App\Utils\Database;

use App\Utils\Database\Connection;
use PSpell\Config;

class Migration
{
    public function up() {}

    public function down() {}

    public static function getRecord()
    {
        return require DATABASE . 'record/record.php';
    }

    public static function getCurrentRecord()
    {

        $records = require DATABASE . 'record/record.php';
        return $records[$records['current']];
    }


    public static function addMigrationOnRecord($id, $migration, $name)
    {

        ini_set('short_open_tag', 1);

        $records = self::getRecord();
        if (!isset($records[$id])) {
            $records[$id] = [$name => $migration];
        } else {
            $records[$id][$name] = $migration;
        }

        $content = "<?php\n\nreturn " . convertArraySyntax(var_export($records, true)) . ";\n";
        file_put_contents(DATABASE . 'record/record.php', $content);
    }

    public static function goToNextMigration()
    {
        $records = self::getRecord();
        $records['current'] = (int)$records['current'] + 1;

        $content = "<?php\n\nreturn " . convertArraySyntax(var_export($records, true)) . ";\n";
        file_put_contents(DATABASE . 'record/record.php', $content);
    }

    public static function goToPrevMigrationsAndUnset()
    {
        $records = self::getRecord();
        $current = $records['current'];

        if ($current < 0) {
            echo 'No previous migration to rollback.' . PHP_EOL;
            return;
        }

        $current_record = $records[$current];
        $prev_record = $records[$current - 1] ?? [];

        $prepare = filterArrayToKeep($current_record, arrayNonIntersect(array_keys($current_record), array_keys($prev_record)));

        foreach ($prepare as $key => $code) {
            $instance = eval($code);
            echo 'Rolling back: ' . $key . PHP_EOL;
            $instance->down();
        }

        if ($records['current'] !== -1) {
            unset($records[$current]);
        }

        $records['current']--;
        echo '';

        $content = "<?php\n\nreturn " . convertArraySyntax(var_export($records, true)) . ";\n";
        file_put_contents(DATABASE . 'record/record.php', $content);
    }


    public static function getCurrentConnection()
    {
        return Connection::getInstance();
    }
}


class Schema
{

    public static function create($table, $callback)
    {

        self::dropIfExists($table);
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        $blueprint->build();
    }

    public static function dropIfExists($table)
    {
        $pdo = Connection::getInstance();
        $smt = $pdo->prepare('DROP TABLE IF EXISTS ' . $table);
        $smt->execute();
    }
}


class Blueprint
{

    private $pdo;
    private $stmt;
    private $table_name;
    private $columns = [];
    private $last_column = null;
    protected $engine = 'InnoDB';
    private $comment = null;
    private $collate;
    private $charset;
    private $db_type;
    private $config;


    public function __construct($table_name)
    {
        $this->table_name = $table_name;
        $this->pdo = Connection::getInstance();
        $this->collate = env('DB_COLLATION', 'utf8mb4_general_ci');
        $this->charset = env('DB_CHARSET', 'utf8mb4');
        $this->config = require CONFIG . 'database.php';
        $this->db_type = env('DB_TYPE', $this->config['default']);
    }

    public function build()
    {

        $columns = implode(', ', array_values($this->columns));
        $query = '
        CREATE TABLE IF NOT EXISTS '
            . $this->table_name
            . '(' . $columns . ')'
            . ($this->db_type == 'mysql' ? " ENGINE=$this->engine" : '')
            . ($this->db_type == 'mysql' ? " CHARSET='$this->charset' COLLATE='$this->collate'" : '')
            . ($this->comment != null ? " COMMENT='$this->comment'" : '');

        // echo $query;
        $this->stmt = $this->pdo->prepare($query);
        $this->stmt->execute();
    }


    private function addColumn($column, $definition)
    {
        $this->columns[$column] = $column . ' ' . $definition;
        $this->last_column = $column;
        return $this;
    }

    private function modifyLastColumn($modifier)
    {
        if ($this->last_column != null && $modifier != 'NULL') {
            $this->columns[$this->last_column] .= ' ' . $modifier;
        } else if ($this->last_column != null) {
            $this->columns[$this->last_column] = str_replace('NOT NULL', '', $this->columns[$this->last_column]);
        }
        return $this;
    }

    public function engine($engine)
    {
        $this->engine = $engine;
        return $this;
    }

    public function tComment($comment)
    {
        $this->comment = $comment;
        return $this;
    }

    public function collate($collate)
    {
        $this->collate = $collate;
        return $this;
    }

    public function charset($charset)
    {
        $this->charset = $charset;
        return $this;
    }

    // Column type
    public function id($column = 'id')
    {
        $this->addColumn($column, $this->db_type == 'mysql' ? 'INTEGER' : 'INTEGER PRIMARY KEY')->modifyLastColumn($this->db_type == 'mysql' ? 'PRIMARY KEY AUTO_INCREMENT' : 'AUTOINCREMENT');
        return $this;
    }

    public function string($column, $length = 255)
    {
        $this->addColumn($column, 'VARCHAR(' . $length . ') NOT NULL');
        return $this;
    }

    public function text($column)
    {
        $this->addColumn($column, 'TEXT NULL NULL');
        return $this;
    }

    public function integer($column)
    {
        $this->addColumn($column, 'INTEGER NOT NULL');
        return $this;
    }

    public function decimal($column, $digits, $precision = 2)
    {
        $this->addColumn($column, 'DECIMAL(' . $digits . ',' . $precision .  ') NOT NULL');
        return $this;
    }

    public function foreignKey($fk_name, $type, $parent_table, $parent_table_id)
    {
        $this->addColumn($fk_name, $type)
            ->modifyLastColumn(', FOREIGN KEY (' . $fk_name . ') REFERENCES ' . $parent_table . '(' . $parent_table_id . ')');
        return $this;
    }


    public function foreignId($fk_name, $parent_table, $parent_table_id = 'id')
    {
        $this->foreignKey($fk_name, 'INT', $parent_table, $parent_table_id);
        return $this;
    }

    public function date($column)
    {
        $this->addColumn($column, 'DATE NOT NULL');
        return $this;
    }

    public function time($column)
    {
        $this->addColumn($column, 'TIME NOT NULL');
        return $this;
    }

    public function datetime($column)
    {
        $this->addColumn($column, 'TIME NOT NULL');
        return $this;
    }

    public function timestamps()
    {
        $this->addColumn('created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP')
            ->addColumn('updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' . ($this->db_type == 'mysql' ? ' ON UPDATE CURRENT_TIMESTAMP' : ''));
        return $this;
    }

    // Modification
    public function unique()
    {
        $this->modifyLastColumn('UNIQUE');
        return $this;
    }

    public function nullable()
    {
        $this->modifyLastColumn('NULL');
        return $this;
    }

    public function autoIncrement()
    {
        $this->modifyLastColumn('AUTO INCREMENT');
        return $this;
    }

    public function onDelete($type)
    {
        $this->modifyLastColumn('ON DELETE ' . $type);
        return $this;
    }

    public function onUpdate($type)
    {
        $this->modifyLastColumn('ON UPDATE ' . $type);
        return $this;
    }

    public function comment($comment)
    {
        $this->modifyLastColumn("COMMENT '$comment'");
        return $this;
    }
}
