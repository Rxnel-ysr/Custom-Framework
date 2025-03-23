<?php

namespace App\Utils\Manager;

use App\Debug\Debugger;

class ClassManager
{
    private static array $classes = [];
    private static bool $isInitialized = false;

    /**
     * Initializes the class manager by loading the class mappings from the configuration file.
     *
     * @return void
     */
    public static function init()
    {
        if (!self::$isInitialized) {
            try {
                self::$classes = require CONFIG . 'classes.php';
                self::$isInitialized = true;
                error_log('Auto-loader: initialized.');
            } catch (\Throwable $e) {
                Debugger::dumpErr($e);
            }
        } else {
            error_log('Auto-loader: skipped initialization because classes are already loaded.');
        }
    }

    /**
     * Retrieves the file path associated with a given class.
     *
     * @param string $class The name of the class whose file path is to be retrieved.
     * @return string The file path of the class.
     */
    public static function getClassFile(string $class)
    {
        return self::$classes[$class] ?? throw new \Exception($class . '::class has not yet registered.', 404);
    }

    /**
     * Loads the classes specified in the provided array, including their aliases if necessary.
     *
     * @param array $classes An associative array where the keys represent class aliases and the values represent the actual class names.
     * @return void
     */
    public static function loadClasses(array $classes)
    {
        try {


            foreach ($classes as $alias => $class) {
                require_once self::$classes[$class];

                if (!is_numeric($alias)) {
                    class_alias($class, $alias);
                }
            }
        } catch (\Throwable $e) {
            Debugger::dumpErr($e);
        }
    }

    public static function autoload($class)
    {
        try {
            require_once self::getClassFile($class);
            error_log('Auto-loader: loaded class [' . $class . ']');
        } catch (\Throwable $e) {
            Debugger::dumpErr($e);
        }
    }

    public static function getLoadedClass()
    {
        return array_intersect(get_declared_classes(), array_keys(self::$classes));
    }

    /**
     * Registers a new class with its associated file path and updates the class configuration file.
     *
     * @param string $class The name of the class to register.
     * @param string $location The relative file path of the class to register.
     * @return void
     */
    public static function registerNewClass(string $class, string $location)
    {
        self::$classes[$class] = realpath(ROOT . $location);

        $content = "<?php\n";
        $content .= "require_once './App/Core/definitions.php';\n\n";
        $content .= "return [\n";

        foreach (self::$classes as $key => $value) {
            $value = str_replace(ROOT, "ROOT . '", $value) . "'";
            $content .= "    {$key}::class => {$value},\n";
        }

        $content .= "];\n";

        file_put_contents(CONFIG . 'classes.php', $content);
    }
}
