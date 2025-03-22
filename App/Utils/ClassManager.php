<?php
namespace App\Utils;

require_once './App/Core/definitions.php';

class ClassManager
{
    private static array $classes = [];

    public static function getClassFile(string $class)
    {
        self::$classes = require CONFIG . 'classes.php';
        return self::$classes[$class];
    }

    public static function registerNewClass(string $class, string $location)
    {
        self::$classes = require CONFIG . 'classes.php';
        self::$classes[$class] = ROOT . $location;
        $content = "require_once '../App/Core/definitions.php';\n" . var_export(self::$classes, true);
        file_put_contents(CONFIG . 'classes.php', $content);
    }
}
