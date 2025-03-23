<?php
namespace App\Utils;

class Env
{
    /**
     * Loads environment variables from a given file and sets them in the system environment.
     *
     * @param string $path The path to the environment file to load.
     * 
     * @return void
     */
    public static function load($path)
    {
        if (!file_exists($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($key, $value) = explode('=', $line, 2);
            $value = trim($value, '"\'');
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}
