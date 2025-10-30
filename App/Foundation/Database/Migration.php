<?php

declare(strict_types=1);

namespace App\Foundation\Database;

use App\Debug\Debugger;
use App\Foundation\Database\Connection;
use App\Foundation\Traits\Macroable;
use Exception;

require_once 'Connection.php';

$root = dirname(__DIR__, 4);

abstract class Migration
{

    private static array $setting;

    abstract public function up();

    abstract public function down();

    public static function init(array $setting = [
        'database_record' => '/path/to/database/record.php',
        'migration' => '/path/to/migrations'
    ])
    {
        $dummy = [
            'database_record' => '/path/to/database/record.php',
            'migration' => '/path/to/migrations'
        ];
        if ($setting == $dummy) {
            throw new Exception('Migration: Please provided setting first');
        }

        self::$setting = $setting;
    }

    public static function getRecord()
    {
        return require self::$setting['database_record'];
    }

    public static function getCurrentRecord()
    {
        try {
            $records = require self::$setting['database_record'];
            return $records[$records['current']];
        } catch (\Throwable $e) {
            Debugger::dumpErr($e);
        }
    }

    public static function addMigrationOnRecord($id, $migration, $name)
    {
        $records = self::getRecord();
        if (!isset($records[$id])) {
            $records[$id] = [$name => $migration];
        } else {
            $records[$id][$name] = $migration;
        }

        $content = "<?php\n\nreturn " . var_export($records, true) . ";\n";
        file_put_contents(self::$setting['database_record'], $content);
    }

    public static function migrate()
    {
        $files = glob(self::$setting['migration'] . DIRECTORY_SEPARATOR . '*.php');
        $records = Migration::getRecord();

        if ($records['current'] > -1) {
            $filenames = array_map(fn($file) => basename($file), $files);
            $current_migrations = array_keys($records[$records['current']]);

            $new_migrations = arrayNonIntersect($current_migrations, $filenames);

            if (empty($new_migrations)) {
                echo 'Nothing to migrate.' . PHP_EOL;
                return 0;
            }

            $old_migrations = arrayIntersectOnly($current_migrations, $filenames);

            $new_migrations = array_fill_keys($new_migrations, true);
            $old_migrations = array_fill_keys($old_migrations, true);

            $files = array_map(function ($file) use ($new_migrations, $old_migrations) {
                $basename = basename($file);

                if ($new_migrations[$basename] ?? false) {
                    return ['file' => $file, 'status' => 'pending'];
                };

                if ($old_migrations[$basename] ?? false) {
                    return ['file' => $file, 'status' => 'done'];
                }
                return null;
            }, $files);

            foreach ($files as $f) {
                if (!$f)
                    continue;
                $basename = basename($f['file']);

                Migration::addMigrationOnRecord($records['current'] + 1, getContent($f['file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES, [1]), $basename);

                if ($f['status'] != 'done') {
                    echo 'Running migration: ' . $basename . PHP_EOL;
                    $class = require $f['file'];
                    $class->up();
                }
            }
        } else {
            foreach ($files as $f) {
                $basename = basename($f);
                Migration::addMigrationOnRecord($records['current'] + 1, getContent($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES, [1]), $basename);
                echo 'Running migration: ' . $basename . PHP_EOL;
                $class = require $f;
                $class->up();
            }
        }

        Migration::goToNextMigration();
    }

    public static function dropAll()
    {
        $files = glob(self::$setting['migration'] . DIRECTORY_SEPARATOR . '*.php');

        if (empty($files)) {
            echo 'Nothing to drop.' . PHP_EOL;
            return;
        }

        foreach ($files as $f) {
            echo 'Dropping migration: ' . basename($f) . PHP_EOL;
            $class = require $f;
            $class->down();
        }
    }

    public static function dropAndReapplyAll()
    {
        $files = glob(self::$setting['migration'] . DIRECTORY_SEPARATOR . '*.php');

        if (empty($files)) {
            echo 'Nothing to migrate.' . PHP_EOL;
            return;
        }

        foreach ($files as $f) {
            echo 'Dropping migration: ' . basename($f) . PHP_EOL;
            $class = require $f;
            $class->down();
        }
        foreach ($files as $f) {
            echo 'Running migration: ' . basename($f) . PHP_EOL;
            $class = require $f;
            $class->up();
        }
    }

    public static function goToNextMigration()
    {
        $records = self::getRecord();
        $records['current'] = (int) $records['current'] + 1;

        $content = "<?php\n\nreturn " . var_export($records, true) . ";\n";
        file_put_contents(self::$setting['database_record'], $content);
    }

    public static function goToPrevMigrationsAndUnset()
    {
        try {
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

            $content = "<?php\n\nreturn " . var_export($records, true) . ";\n";
            file_put_contents(self::$setting['database_record'], $content);
        } catch (\ParseError $e) {
            Debugger::dumpErr($e);
        } catch (\Throwable $e) {
            Debugger::dumpErr($e);
        } finally {
            echo 'Done.' . PHP_EOL;
        }
    }

    public static function getCurrentConnection()
    {
        return Connection::getInstance();
    }
}

class Schema
{
    public static function create(string $table, callable $callback)
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

class Blueprint extends Connection
{
    use Macroable;

    private $pdo;
    private $stmt;
    private $table_name;
    private $columns = [];
    private $last_column = null;
    protected $engine = 'InnoDB';
    private $comment = null;
    private $collate;
    private $charset;
    protected $db_type;

    public function __construct(string $table_name)
    {
        $this->table_name = $table_name;
        $this->pdo = Connection::getInstance();
        $this->collate = env('DB_COLLATION', self::$config['collation'] ?? null);
        $this->charset = env('DB_CHARSET', self::$config['charset'] ?? null);
        $this->db_type = env('DB_TYPE', self::$config['db_type'] ?? null);
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

    private function addColumn(string $column, string $definition)
    {
        $this->columns[$column] = $column . ' ' . $definition;
        $this->last_column = $column;
        return $this;
    }

    private function modifyLastColumn(string $modifier)
    {
        if ($this->last_column != null && $modifier != 'NULL') {
            $this->columns[$this->last_column] .= ' ' . $modifier;
        } else if ($this->last_column != null) {
            $this->columns[$this->last_column] = str_replace('NOT NULL', '', $this->columns[$this->last_column]);
        }
        return $this;
    }

    public function engine(string $engine)
    {
        $this->engine = $engine;
        return $this;
    }

    public function tComment(string $comment)
    {
        $this->comment = $comment;
        return $this;
    }

    public function collate(string $collate)
    {
        $this->collate = $collate;
        return $this;
    }

    public function charset(string $charset)
    {
        $this->charset = $charset;
        return $this;
    }

    // Column type
    public function id(string $column = 'id')
    {
        $this->addColumn($column, $this->db_type == 'mysql' ? 'INTEGER' : 'INTEGER PRIMARY KEY')->modifyLastColumn($this->db_type == 'mysql' ? 'PRIMARY KEY AUTO_INCREMENT' : 'AUTOINCREMENT');
        return $this;
    }

    public function string(string $column, int $length = 255)
    {
        $this->addColumn($column, 'VARCHAR(' . $length . ') NOT NULL');
        return $this;
    }

    public function text(string $column)
    {
        $this->addColumn($column, 'TEXT NOT NULL');
        return $this;
    }

    public function integer(string $column)
    {
        $this->addColumn($column, 'INTEGER NOT NULL');
        return $this;
    }

    public function decimal(string $column, float $digits, int $precision = 2)
    {
        $this->addColumn($column, 'DECIMAL(' . $digits . ',' . $precision . ') NOT NULL');
        return $this;
    }

    public function foreignKey(string $fk_name, string $type, string $parent_table, string $parent_table_id)
    {
        $this
            ->addColumn($fk_name, $type)
            ->modifyLastColumn(', FOREIGN KEY (' . $fk_name . ') REFERENCES ' . $parent_table . '(' . $parent_table_id . ')');
        return $this;
    }

    public function foreignId(string $fk_name, string $parent_table, string $parent_table_id = 'id')
    {
        $this->foreignKey($fk_name, 'INT', $parent_table, $parent_table_id);
        return $this;
    }

    public function date(string $column)
    {
        $this->addColumn($column, 'DATE NOT NULL');
        return $this;
    }

    public function time(string $column)
    {
        $this->addColumn($column, 'TIME NOT NULL');
        return $this;
    }

    public function datetime(string $column)
    {
        $this->addColumn($column, 'TIME NOT NULL');
        return $this;
    }

    public function enum(string $column, array $allowed)
    {
        $this->addColumn($column, 'ENUM(' . implode(', ', $allowed) . ') NOT NULL');
        return $this;
    }

    public function timestamps()
    {
        $this
            ->addColumn('created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP')
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

    public function after($column)
    {
        $this->modifyLastColumn("AFTER '$column'");
        return $this;
    }
}
