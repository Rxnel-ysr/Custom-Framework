<?php

namespace App\EXPE\Foundation\Manager;

use Exception;
use ReflectionUnionType;
use Throwable;

class ClassManager
{
    private static array $classes = [];
    private static string $root;
    private static array $cache_classes = [];
    private static bool $is_initialized = false;
    private static bool $debug = false;
    private static bool $is_web = false;
    private static bool $auto_resolve = false;
    private static array $setting;

    /**
     * Initializes the class manager by loading the class mappings from the configuration file.
     *
     * @return void
     */
    public static function init(
        string $root,
        bool $debug = true,
        bool $is_web = false,
        array $setting = [
            'classmap' => 'path/to/class/map.php',
            'cache_classmap' => 'path/to/cache/class/map.php',
        ]
    ) {
        $dummy_setting = [
            'classmap' => 'path/to/class/map.php',
            'cache_classmap' => 'path/to/cache/class/map.php',
        ];
        self::$debug = $debug;
        self::$is_web = $is_web;
        self::$setting = $setting;
        self::$root = $root;
        if (self::$setting == $dummy_setting) {
            throw new Exception('Please define path first');
        }
        if (!self::$is_initialized) {
            self::$classes = require self::$setting['classmap'];
            self::$cache_classes = require self::$setting['cache_classmap'];
            self::$is_initialized = true;
            if (self::$debug)
                error_log('Auto-loader: initialized.');
        } else {
            if (self::$debug)
                error_log('Auto-loader: skipped initialization because classes are already loaded.');
        }
    }

    public static function getAttr()
    {
        return [
            'is_initialized' => self::$is_initialized,
            'debug' => self::$debug,
            'auto_resolve' => self::$auto_resolve
        ];
    }

    public static function initAutoloader(bool $auto_resolve = false)
    {

        self::$auto_resolve = $auto_resolve;
        spl_autoload_register([self::class, 'autoload'], true);
    }

    /**
     * Retrieves the file path associated with a given class.
     *
     * @param string $class The name of the class whose file path is to be retrieved.
     * @return string The file path of the class.
     */
    public static function getClassFile(string $class)
    {
        return self::$classes[$class] ?? false;
    }

    /**
     * Loads the classes specified in the provided array, including their aliases if necessary.
     *
     * @param array $classes An associative array where the keys represent class aliases and the values represent the actual class names.
     * @return void
     */
    public static function loadClasses(array $classes)
    {
        foreach ($classes as $alias => $class) {
            require_once self::$classes[$class];

            if (!is_numeric($alias)) {
                class_alias($class, $alias);
            }
        }
    }

    public static function getMethodDetails($class)
    {
        if ($class == 'self::class' || $class == 'self') {
            $class = self::class;
        }
        $reflection = new \ReflectionClass($class);
        $methods = $reflection->getMethods();

        $details = [];

        foreach ($methods as $method) {
            $params = array_map(function ($param) {
                return [
                    'name' => $param->getName() ?? 'No name',
                    'type' => $param->hasType()
                        ? ($param->getType() instanceof ReflectionUnionType
                            ? implode('|', array_map(fn($t) => $t->getName(), $param->getType()->getTypes()))
                            : $param->getType()->getName())
                        : 'mixed',
                    'optional' => $param->isOptional()
                ];
            }, $method->getParameters());

            $details[$method->name] = [
                'visibility' => \Reflection::getModifierNames($method->getModifiers()),
                'is_static' => $method->isStatic(),
                'return_type' => $method->hasReturnType()
                    ? ($method->getReturnType() instanceof ReflectionUnionType
                        ? implode('|', array_map(fn($t) => $t->getName(), $method->getReturnType()->getTypes()))
                        : $method->getReturnType()->getName())
                    : 'mixed',
                'phpdoc' => $method->getDocComment() ?: 'No DocBlock',
                'parameters' => $params
            ];
        }

        return $details;
    }
    public static function autoload($class)
    {
        return self::method_x($class);
    }

    public static function method_x($class)
    {
        return self::method_1($class) ?: self::method_2($class) ?: self::method_3($class) ?: self::method_4($class) ?: self::method_5($class) ?: self::method_6($class) ?: self::method_7($class);
    }

    public static function dumpAutoload($with_cache = false)
    {
        $classes = scanForClasses(self::$root);
        $results = [];

        foreach ($classes as $class => $path) {
            $path = str_replace(self::$root, '', $path);
            $results[$class] = $path;
        }

        echo 'Updating main mapping...' . PHP_EOL;
        self::updateClassesMapping($results);
        if ($with_cache) {
            echo 'Updating cache mapping...' . PHP_EOL;
            self::updateCacheClassesMapping($results);
        }
    }


    public static function messageForResolvedClass($class, $level)
    {
        if (self::$debug) {
            switch ($level) {
                case 1:
                    error_log('Auto-loader: Loaded class [' . $class . ']');
                    break;
                case 2:
                    error_log('Auto-loader: Resolved class [' . $class . '] (changed path)');
                    break;
                case 3:
                    error_log('Auto-loader: Resolved class [' . $class . '] (from cache)');
                    break;
                case 4:
                    error_log('Auto-loader: Resolved class [' . $class . '] (changed path, renamed)');
                    break;
                case 5:
                    error_log('Auto-loader: Resolved class [' . $class . '] (system scan)');
                    break;
                case 6:
                    error_log('Auto-loader: Resolved class [' . $class . '] (manual action)');
                    break;
                case 7:
                    error_log('Auto-loader: Resolved class [' . $class . '] (temporary placeholder)');
                    break;
                default:
                    error_log('Auto-loader: Unrecognized level (' . $level . '), ignoring... have a nice day!');
                    break;
            }
        }
    }

    public static function getLoadedClass(array|string $custom_filter = [])
    {
        return array_intersect(get_declared_classes(), !empty($custom_filter) ? (is_array($custom_filter) ? $custom_filter : [$custom_filter]) : array_keys((array) self::$classes));
    }

    /**
     * Registers a new class with its associated file path and updates the class configuration file.
     *
     * @param string $class The name of the class to register.
     * @param string $location The relative file path of the class to register, it started from project root.
     * @return void
     */
    public static function registerNewClass(string $class, string $location)
    {
        if (self::$debug) error_log('Auto-loader: Registered class [' . $class . ']');
        self::$classes[$class] = $location;
        file_put_contents(self::$setting['classmap'], '<?php' . PHP_EOL . 'return ' . var_export(self::$classes, true) . ';');
    }

    public static function updateClassesMapping(array $classes)
    {
        self::$classes = $classes;
        file_put_contents(self::$setting['classmap'], '<?php' . PHP_EOL . 'return ' . var_export(self::$classes, true) . ';');
    }
    public static function updateCacheClassesMapping(array $classes)
    {
        self::$cache_classes = $classes;
        file_put_contents(self::$setting['cache_classmap'], '<?php' . PHP_EOL . 'return ' . var_export(self::$cache_classes, true) . ';');
    }


    public static function cachedResolvedClass($class, $resolved_path)
    {
        self::registerNewClass($class, $resolved_path);
        self::$cache_classes[$class] = $resolved_path;
        file_put_contents(self::$setting['cache_classmap'], '<?php' . PHP_EOL . 'return ' . var_export(self::$cache_classes, true) . ';');
    }

    public static function loadClassFromCache($class)
    {
        if (isset(self::$cache_classes[$class])) {
            if (file_exists(self::$root . self::$cache_classes[$class])) {
                require_once self::$root . self::$cache_classes[$class];
                return self::$cache_classes[$class];
            }
            return false;
        }
        return false;
    }

    public static function resolve($class)
    {
        $class_base_name = basename(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $class));
        $guessed_class_path = 'App/Foundation/' . $class_base_name . '.php';
        if (file_exists(self::$root . $guessed_class_path)) {
            require_once self::$root . $guessed_class_path;
            if (class_exists($class)) {
                self::registerNewClass($class, $guessed_class_path);
                return $guessed_class_path;
            }
            return false;
        }
        return false;
    }

    public static function searchClass($class, $dir, $ignore_dirs = [], $ignore_files = [], $except_files = [])
    {
        $classes = scanForClasses($dir, $ignore_dirs, $ignore_files, $except_files);
        if (isset($classes[$class])) {
            return $classes[$class];
        }
        return false;
    }
    /**
     * Method 1 searching algorithm (Map Look Up)
     * ___
     * 
     * Hierarchy of class searching Algorithm and Big O notation speed
     *
     * Method 1: O(1)
     *
     * Method 2: O(1)
     *
     * Method 3: O(1)
     *
     * Method 4: O(1)
     *
     * Method 5: O(N²)
     *
     * Method 6: O(1)
     *
     * Method 7: O(1)
     *
     * ---
     */
    public static function method_1(string $class)
    {
        if (self::$debug) {
            error_log('Auto-loader: Using method 1');
        }
        if (isset(self::$classes[$class]) && file_exists(self::$root . self::$classes[$class])) {
            require_once self::$root . self::$classes[$class];
            if (class_exists($class, false)) {
                self::messageForResolvedClass($class, 1);
                return true;
            }
            return false;
        }
        if (self::$debug) {
            error_log('Auto-loader: Method 1 failed');
        }
        return false;
    }

    /**
     * Method 2 searching algorithm (Common Path Look Up)
     * ___
     * 
     * Hierarchy of class searching Algorithm and Big O notation speed
     *
     * Method 1: O(1)
     *
     * Method 2: O(1)
     *
     * Method 3: O(1)
     *
     * Method 4: O(1)
     *
     * Method 5: O(N²)
     *
     * Method 6: O(1)
     *
     * Method 7: O(1)
     *
     * ---
     */
    public static function method_2($class)
    {
        if (self::$debug)
            error_log('Auto-loader: Using method 2');
        if (!empty(self::resolve($class))) {
            return true;
        }
        if (self::$debug) error_log('Auto-loader: Method 2 failed (not found in common path)');
        return false;
    }

    /**
     * Method 3 searching algorithm (Cache mapping Look Up)
     * ___
     * 
     * Hierarchy of class searching Algorithm and Big O notation speed
     *
     * Method 1: O(1)
     *
     * Method 2: O(1)
     *
     * Method 3: O(1)
     *
     * Method 4: O(1)
     *
     * Method 5: O(N²)
     *
     * Method 6: O(1)
     *
     * Method 7: O(1)
     *
     * ---
     */
    public static function method_3($class)
    {
        if (self::$debug) {
            error_log('Auto-loader: Using method 3');
        }
        $path = self::loadClassFromCache($class);
        if (class_exists($class, false)) {
            self::registerNewClass($class, $path);
            return true;
        }
        if (self::$debug) {
            error_log('Auto-loader: Method 3 failed (no cache for [' . $class . '])');
        }
        return false;
    }

    /**
     * Method 4 searching algorithm (Namespace Based Look Up (psr-4) )
     * ___
     * 
     * Hierarchy of class searching Algorithm and Big O notation speed 
     *
     * Method 1: O(1)
     *
     * Method 2: O(1)
     *
     * Method 3: O(1)
     *
     * Method 4: O(1)
     *
     * Method 5: O(N²)
     *
     * Method 6: O(1)
     *
     * Method 7: O(1)
     *
     * ---
     */
    public static function method_4($class)
    {
        if (self::$debug) {
            error_log('Auto-loader: Using method 4');
        }
        $guessed_class_path_name = str_replace('\\', '/', $class) . '.php';
        if (file_exists(self::$root . $guessed_class_path_name)) {
            require_once self::$root . $guessed_class_path_name;
            if (class_exists($class, false)) {
                self::registerNewClass($class, $guessed_class_path_name);
                return true;
            }
            if (self::$debug) error_log('Auto-loader: Method 4 failed (class not found)');
            return false;
        }
        if (self::$debug) {
            error_log('Auto-loader: Method 4 failed (file not exist)');
        }
        return false;
    }

    /**
     * Method 5 searching algorithm (System Scan (psr-0) )
     * ___
     * 
     * Hierarchy of class searching Algorithm and Big O notation speed
     *
     * Method 1: O(1)
     *
     * Method 2: O(1)
     *
     * Method 3: O(1)
     *
     * Method 4: O(1)
     *
     * Method 5: O(N²)
     *
     * Method 6: O(1)
     *
     * Method 7: O(1)
     *
     * ---
     */
    public static function method_5($class)
    {
        if (self::$debug) {
            error_log('Auto-loader: Using method 5');
        }
        if (self::$auto_resolve) {
            $classes = scanForClasses(self::$root);
            self::updateClassesMapping(array_combine(array_keys($classes), array_map(fn($path) => str_replace(self::$root, '', $path), $classes)));
            if (isset($classes[$class])) {
                require_once $classes[$class];
                if (class_exists($class)) {
                    self::cachedResolvedClass($class, str_replace(self::$root, '', $classes[$class]));
                    return true;
                }
                if (self::$debug) error_log('Auto-loader: Method 5 failed (class, invalid?)');
                return false;
            }
            if (self::$debug) error_log('Auto-loader: Method 5 failed (class cant be found)');
            return false;
        }
        if (self::$debug) {
            error_log('Auto-loader: Method 5 failed (skipped)');
        }
        return false;
    }

    /**
     * Method 6 searching algorithm (User Defined Path)
     * ___
     * 
     * Hierarchy of class searching Algorithm and Big O notation speed
     *
     * Method 1: O(1)
     *
     * Method 2: O(1)
     *
     * Method 3: O(1)
     *
     * Method 4: O(1)
     *
     * Method 5: O(N²)
     *
     * Method 6: O(1)
     *
     * Method 7: O(1)
     *
     * ---
     */
    public static function method_6($class)
    {
        if (self::$debug) {
            error_log('Auto-loader: Using method 6');
        }
        // if (self::$is_web && getBoolEnv('AUTO_LOAD_USER_PATH_DEFINED_CLASS', !getBoolEnv('AUTO_LOAD_CLASS_ALWAYS_EXISTS', true))) {
        //     return showErrorPage(HTTP_SERVER_ERROR, 'Method 6, add new class: ' . $class, 'Register ' . $class . '::class path', '', !empty($e) ? $e->getTraceAsString() : '', true);
        // }
        if (self::$debug) {
            error_log('Auto-loader: Method 6 failed (skipped)');
        }
        return false;
    }

    /**
     * Method 7 searching algorithm (Class Creation (Fallback) )
     * ___
     * 
     * Hierarchy of class searching Algorithm and Big O notation speed
     *
     * Method 1: O(1)
     *
     * Method 2: O(1)
     *
     * Method 3: O(1)
     *
     * Method 4: O(1)
     *
     * Method 5: O(N²)
     *
     * Method 6: O(1)
     *
     * Method 7: O(1)
     *
     * ---
     */
    public static function method_7($class)
    {
        if (getBoolEnv('AUTO_LOAD_CLASS_ALWAYS_EXISTS', self::$auto_resolve)) {
            if (self::$debug) {
                error_log('Auto-loader: Using method 7');
            }
            eval("class $class{
                private static \$static_data = [];
                private \$instance_data = [];
                private static \$static_methods = [];
                private \$instance_methods = [];

                public static function addStaticMethod(\$name,\$callback)
                {
                    self::\$static_methods[\$name] = \$callback;
                }
                public static function addInstanceMethod(\$name,\$callback)
                {
                    \$this->instance_methods[\$name] = \$callback;
                }
                public static function __callStatic(\$name, \$arguments)
                {
                    if(isset(self::\$static_methods[\$name])){
                        return call_user_func(self::\$static_methods[\$name],...\$arguments);

                    } else if (strpos(\$name, 'get_') === 0) {

                        \$prop = substr(\$name, 4);
                        return self::\$static_data[\$prop] ?? 'Static property \$prop not found. Even the class it self.';

                    } elseif (strpos(\$name, 'set_') === 0) {

                        \$prop = substr(\$name, 4);
                        self::\$static_data[\$prop] = \$arguments[0];

                    } else {
                        echo 'Function of \$name doesn\'t exist. Even the class it self.';
                    }

                }
                public function __call(\$name, \$arguments)
                {
                    if(isset(\$this->instance_methods[\$name])){
                        return call_user_func(\$this->instance_methods[\$name],...\$arguments);
                    }

                    echo 'Function of ' . \$name . ' doesn\'t exist.';
                }

                public function __get(\$name) {
                    return \$this->instance_data[\$name] ?? 'Property \$name doesn\'t exist. Even the class it self.';
                }

                public function __set(\$name, \$value) {
                    \$this->instance_data[\$name] = \$value;
                }

                public function __toString(){
                    return 'This class is just temporary class created by method 7';
                }
            }");
            return true;
        }
        if (self::$debug) {
            error_log('Auto-loader: Method 7 failed (skipped)');
        }
        return false;
    }
}
