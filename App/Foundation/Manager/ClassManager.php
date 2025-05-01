<?php

namespace App\Foundation\Manager;

use Exception;
use Throwable;

class ClassManager
{
    private static array $classes = [];
    private static array $cache_classes = [];
    private static bool $is_initialized = false;
    private static bool $debug = false;
    private static bool $is_web = false;
    private static bool $auto_resolve = false;

    /**
     * Initializes the class manager by loading the class mappings from the configuration file.
     *
     * @return void
     */
    public static function init(
        bool $debug = true,
        bool $is_web = false,
    ) {
        self::$debug = $debug;
        self::$is_web = $is_web;
        if (!self::$is_initialized) {
            self::$classes = (array) json_decode(file_get_contents(CONFIG . 'classes.json'));
            self::$cache_classes = (array) json_decode(file_get_contents(CACHE_PATH . 'classes/classes.json'));
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
        try {
            self::$auto_resolve = $auto_resolve;
            spl_autoload_register([self::class, 'autoload'], true);
        } catch (\Throwable $e) {
            throw new \Exception('Failed to load class', 404, $e);
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
        foreach ($classes as $alias => $class) {
            require_once self::$classes[$class];

            if (!is_numeric($alias)) {
                class_alias($class, $alias);
            }
        }
    }

    public static function loadClass(string $class)
    {
        require_once ROOT . self::$classes[$class];
        return self::$classes[$class];
    }

    // public static function autoload($class)
    // {
    //     try {
    //         if(self::$debug)error_log('Auto-loader: Using method 1');
    //         if (file_exists(ROOT . self::getClassFile($class))) {
    //             require_once ROOT . self::getClassFile($class);
    //             self::messageForResolvedClass($class, 1);
    //         } else {
    //             if (self::$debug) error_log('Auto-loader: Method 1 failed (invalid registered/unregistered class path)');
    //             throw new \Exception($class . '::class has not yet registered.', 404);
    //         }
    //     } catch (\Throwable $e) {
    //         if (self::$auto_resolve) {
    //             if (self::$auto_resolve && getBoolEnv('AUTO_LOAD_USER_PATH_DEFINED_CLASS') && getBoolEnv('AUTO_LOAD_CLASS_ALWAYS_EXISTS')) {
    //                 throw new \Exception('[Auto-loader] Method 6 (Manual input) and 7 (Class creation) cant be used at same time, please choose either.', 403);
    //             }
    //             self::method_2($class)
    //                 ? self::messageForResolvedClass($class, 2)
    //                 : (self::method_3($class)
    //                     ? self::messageForResolvedClass($class, 3)
    //                     : (self::method_4($class)
    //                         ? self::messageForResolvedClass($class, 4)
    //                         : (self::method_5($class)
    //                             ? self::messageForResolvedClass($class, 5)
    //                             : (self::method_6($class, $e)
    //                                 ? self::messageForResolvedClass($class, 6)
    //                                 : (self::method_7($class)
    //                                     ? self::messageForResolvedClass($class, 7)
    //                                     :  throw new \Exception($class . '::class has not yet registered. And cannot be resolved.', 69, $e)
    //                                 )))));
    //         } else {
    //             throw new \Exception($class . '::class has not yet registered.', 404, $e);
    //         }
    //     }
    // }

    public static function autoload($class)
    {
        try {
            if (self::$debug) error_log('Auto-loader: Using method 1');

            if (file_exists(ROOT . self::getClassFile($class))) {
                require_once ROOT . self::getClassFile($class);
                self::messageForResolvedClass($class, 1);
                return;
            } else {
                if (self::$debug) error_log('Auto-loader: Method 1 failed');
                throw new \Exception($class . '::class has not yet registered.', 404);
            }
        } catch (\Throwable $e) {
            if (self::$auto_resolve && getBoolEnv('AUTO_LOAD_USER_PATH_DEFINED_CLASS') && getBoolEnv('AUTO_LOAD_CLASS_ALWAYS_EXISTS')) {
                throw new \Exception('[Auto-loader] Method 6 (Manual input) and 7 (Class creation) cant be used at same time, please choose either.', 403);
            }

            if (self::$auto_resolve) {
                $methods = [2, 3, 4, 5, 6, 7];

                foreach ($methods as $method) {
                    $methodName = 'method_' . $method;
                    if (self::$methodName($class)) {
                        self::messageForResolvedClass($class, $method);
                        return;
                    }
                    if (class_exists($class)) return;
                }
                throw new \Exception($class . '::class has not yet registered. And cannot be resolved.', 400, $e);
            } else {
                throw new \Exception($class . '::class has not yet registered.', 404, $e);
            }
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

    // // if (self::$auto_resolve && self::$debug) error_log('Triggered auto resolve');
    // $class_base_name = basename(str_replace('\\', '/', $class));
    // // error_log('Triggered auto resolve base class name: ' . $class_base_name);
    // $class_path = 'App/Utils/' . $class_base_name . '.php';
    // if (file_exists(ROOT . $class_path)) {
    //     require_once ROOT . $class_path;
    //     if (class_exists($class)) {
    //         self::registerNewClass($class, $class_path);
    //         if (self::$debug) error_log('Auto-loader: successfully resolved unregistered class [' . $class . ']');
    //     } else {
    //         if (self::$debug) error_log('Auto-loader: failed to resolve unregistered class [' . $class . ']');
    //         throw new \Exception($class . '::class has not yet registered. And cant be resolved, manual action needed.', 404, $e);
    //     }
    // } else {
    //     throw new \Exception($class . '::class has not yet registered. And cant be resolved, manual action needed.', 404, $e);
    // }

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
        file_put_contents(CONFIG . 'classes.json', json_encode(self::$classes, JSON_PRETTY_PRINT));
    }

    public static function updateClassesMapping(array $classes)
    {
        self::$classes = $classes;
        file_put_contents(CONFIG . 'classes.json', json_encode(self::$classes, JSON_PRETTY_PRINT));
    }

    public static function cachedResolvedClass($class, $resolved_path)
    {
        self::registerNewClass($class, $resolved_path);
        self::$cache_classes[$class] = $resolved_path;
        file_put_contents(CACHE_PATH . 'classes/classes.json', json_encode(self::$cache_classes, JSON_PRETTY_PRINT));
    }

    public static function loadClassFromCache($class)
    {
        if (file_exists(ROOT . self::$cache_classes[$class])) {
            require_once ROOT . self::$cache_classes[$class];
            return self::$cache_classes[$class];
        } else {
            return false;
        }
    }

    public static function resolve($class)
    {
        $class_base_name = basename(str_replace('\\', '/', $class));
        $guessed_class_path = 'App/Utils/' . $class_base_name . '.php';
        if (file_exists(ROOT . $guessed_class_path)) {
            require_once ROOT . $guessed_class_path;
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
     * Method 2 searching algorithm (A.K.A. Direct look up to most common place, e.g. App/Utils/Classname.php)
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
     * Method 3 searching algorithm (A.K.A. Look up to cached path, looking path at storage/cache/classes.json)
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
        if (class_exists($class)) {
            self::registerNewClass($class, $path);
            return true;
        }
        if (self::$debug) {
            error_log('Auto-loader: Method 3 failed (no cache for [' . $class . '])');
        }
        return false;
    }

    /**
     * Method 4 searching algorithm (A.K.A. Look up based on namespaced directory)
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
        if (file_exists($guessed_class_path_name)) {
            require_once ROOT . $guessed_class_path_name;
            if (class_exists($class)) {
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
     * Method 5 searching algorithm (A.K.A. Scans all php files inside `ROOT`)
     * Excluding Controllers, Middlewares, Libs, and database
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
        if (getBoolEnv('AUTO_LOAD_RECURSIVE_CLASS_SCANNING', self::$auto_resolve)) {
            $classes = scanForClasses(ROOT, ['App/Http/Controllers', 'App/Http/Middlewares', 'App/Core/Libs', 'database'], [], ['App/Http/Controllers/Controller.php']);
            if (isset($classes[$class])) {
                require_once $classes[$class];
                if (class_exists($class)) {
                    self::updateClassesMapping(array_combine(array_keys($classes), array_map(fn($path) => str_replace(ROOT, '', $path), $classes)));
                    self::cachedResolvedClass($class, str_replace(ROOT, '', $classes[$class]));
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
     * Method 6 searching algorithm (A.K.A. On the run user defined path)
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
    public static function method_6($class, null|Throwable $e = null)
    {
        if (self::$debug) {
            error_log('Auto-loader: Using method 6');
        }
        if (self::$is_web && getBoolEnv('AUTO_LOAD_USER_PATH_DEFINED_CLASS', !getBoolEnv('AUTO_LOAD_CLASS_ALWAYS_EXISTS', true))) {
            return showErrorPage(HTTP_SERVER_ERROR, 'Method 6, add new class: ' . $class, 'Register ' . $class . '\'s path', '', !empty($e) ? $e->getTraceAsString() : '', $e, true);
        }
        if (self::$debug) {
            error_log('Auto-loader: Method 6 failed (skipped)');
        }
        return false;
    }

    /**
     * Method 7 searching algorithm (A.K.A. Creating placeholder class)
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
