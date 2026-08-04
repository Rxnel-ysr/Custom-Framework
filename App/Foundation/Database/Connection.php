<?php

declare(strict_types=1);

namespace App\Foundation\Database;

use App\Foundation\Manager\InstanceManager;
use Exception;
use PDO;
use PDOException;

class Connection
{
    private static ?PDO $PDO = null;
    private static array $config;

    public static function set(array $config)
    {
        self::$config = $config;
    }

    /**
     * Returns a singleton instance of the PDO connection.
     *
     * @throws PDOException If the connection to the database fails.
     */
    public static function getInstance()
    {
        // try {
        if (self::$PDO === null) {
            $config = self::$config;
            $dbType = env('DB_TYPE', $config['default']);
            $config = $config[$dbType];
            self::$config = $config;

            if ($dbType === 'sqlite') {
                $dsn = "sqlite:" . $config['database'];
                self::$PDO = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT => true
                ]);
            } else {
                $dsn = "mysql:host={$config['host']};dbname={$config['name']};charset={$config['charset']};collation={$config['collation']}";
                self::$PDO = new PDO($dsn, $config['user'], $config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT => true
                ]);
            }
        }
        return self::$PDO;
        // } catch (\Throwable $e) {
        //     Debugger::dumpErr($e);
        // }
    }
}
