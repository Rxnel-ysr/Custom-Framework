<?php

namespace App\Foundation\Manager;

use Exception;
use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionUnionType;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

class ClassManager
{
    private static array $classes = [];
    private static string $root;
    private static array $cache_classes = [];
    private static bool $is_initialized = false;
    private static bool $debug = false;
    private static bool $auto_resolve = false;
    private static array $setting;
    private static array $files;

    private const EMPTY_CLASSMAP_TEMPLATE = "<?php\nreturn [];\n";

    /**
     * Initializes the class manager by loading the class mappings from the configuration file.
     */
    public static function set(
        string $root,
        bool $debug = true,
        bool $auto = false,
        array $setting = [
            'classmap' => 'path/to/class/map.php',
            'cache_classmap' => 'path/to/cache/class/map.php',
            'where_to_look_class' => 'path/to/dir/containing/class',
        ],
        array $files = []
    ): void {
        $dummy_setting = [
            'classmap' => 'path/to/class/map.php',
            'cache_classmap' => 'path/to/cache/class/map.php',
            'where_to_look_class' => 'path/to/dir/containing/class'
        ];

        self::$debug = $debug;
        self::$setting = $setting;
        self::$root = rtrim($root, '/') . '/';

        if (self::$setting === $dummy_setting) {
            throw new Exception('Please define path first');
        }

        $required = ['classmap', 'cache_classmap', 'where_to_look_class'];
        foreach ($required as $key) {
            if (empty($setting[$key])) {
                throw new Exception("Missing required setting: {$key}");
            }
        }
        self::$files = $files;

        if (!self::$is_initialized) {
            self::createClassMapFilesIfNotExists();

            self::$classes = self::loadClassMap(self::$setting['classmap']);
            self::$cache_classes = self::loadClassMap(self::$setting['cache_classmap']);

            if ($auto && empty(self::$classes)) {

                $classes = self::scanForClasses(self::$root);
                $res = [];
                foreach ($classes as $class => $path) {
                    $res[$class] = str_replace(self::$root, '', $path);
                }

                self::updateClassesMapping($res);
                self::updateCacheClassesMapping($res);
            }

            self::$setting['where_to_look_class'] = rtrim(self::$setting['where_to_look_class'], '/\\') . '/';
            self::$is_initialized = true;

            if (self::$debug) {
                error_log('Auto-loader: initialized.');
            }
        } elseif (self::$debug) {
            error_log('Auto-loader: skipped initialization because classes are already loaded.');
        }
    }

    private static function createClassMapFilesIfNotExists(): void
    {
        foreach (['classmap', 'cache_classmap'] as $mapType) {
            $dir = dirname(self::$setting[$mapType]);
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                    throw new \RuntimeException("Failed to create directory: $dir");
                }
            }
            if (!file_exists(self::$setting[$mapType])) {
                file_put_contents(self::$setting[$mapType], self::EMPTY_CLASSMAP_TEMPLATE, LOCK_EX);
            }
        }
    }

    private static function loadClassMap(string $filePath): array
    {
        $loaded = require $filePath;
        return is_array($loaded) ? $loaded : [];
    }

    public static function getAttr(): array
    {
        return [
            'is_initialized' => self::$is_initialized,
            'debug' => self::$debug,
            'auto_resolve' => self::$auto_resolve
        ];
    }

    public static function initAutoloader(bool $auto_resolve = false): void
    {
        self::$auto_resolve = $auto_resolve;
        foreach (self::$files as $file) {
            require_once self::$root . $file;
        }
        spl_autoload_register([self::class, 'autoload'], true);
    }

    public static function getClassFile(string $class): string|false
    {
        return self::$classes[$class] ?? false;
    }

    public static function loadAllClass(array $excepts = []): int
    {
        $loadedCount = 0;

        foreach (self::$classes as $class => $path) {
            if (in_array($class, $excepts, true)) {
                continue;
            }

            if (!class_exists($class, false)) {
                require_once self::$root . DIRECTORY_SEPARATOR . $path;
                $loadedCount++;
            }
        }

        return $loadedCount;
    }

    public static function loadClasses(array $classes): void
    {
        foreach ($classes as $alias => $class) {
            if (isset(self::$classes[$class])) {
                require_once self::$classes[$class];

                if (!is_numeric($alias)) {
                    class_alias($class, $alias);
                }
            }
        }
    }

    public static function scanForClasses(
        string $directory,
        array $ignore_dirs = [],
        array $ignore_files = [],
        array $except_files = []
    ): array {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);
        $classes = [];
        $visited = [];

        // Preprocess ignore dirs once
        $ignoreDirsNormalized = [];
        foreach ($ignore_dirs as $dir) {
            $ignoreDirsNormalized[] = trim($dir, '/\\') . DIRECTORY_SEPARATOR;
        }

        // Flip arrays to hash maps (O(1) lookups)
        $ignoreFilesSet = $ignore_files ? array_flip($ignore_files) : [];
        $exceptFilesSet = $except_files ? array_flip($except_files) : [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator(
                    $directory,
                    FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
                ),
                static function ($file, $key, $iterator) use (
                    $directory,
                    $ignoreDirsNormalized,
                    $exceptFilesSet,
                    &$visited
                ): bool {
                    if (!$file->isDir()) {
                        return true; // keep file
                    }

                    $realPath = $file->getRealPath();
                    if (isset($visited[$realPath])) {
                        return false;
                    }
                    $visited[$realPath] = true;

                    $relativePath = substr($realPath, strlen($directory) + 1);

                    // Explicit exceptions win
                    if (isset($exceptFilesSet[$relativePath])) {
                        return true;
                    }

                    // Fast prefix check
                    foreach ($ignoreDirsNormalized as $ignoreDirPath) {
                        if (strncmp($relativePath, $ignoreDirPath, strlen($ignoreDirPath)) === 0) {
                            return false;
                        }
                    }

                    return true;
                }
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = substr($file->getPathname(), strlen($directory) + 1);

                if (isset($ignoreFilesSet[$relativePath])) {
                    continue;
                }

                // No array_merge in loop, direct append is cheaper
                foreach (self::extractClassesFromFile($file->getPathname()) as $class => $path) {
                    $classes[$class] = $path;
                }
            }
        }

        return $classes;
    }

    private static function extractClassesFromFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if (
            strpos($content, 'class') === false &&
            strpos($content, 'interface') === false &&
            strpos($content, 'trait') === false
        ) {
            return [];
        }

        $cleanedContent = self::cleanPhpContent($content);

        preg_match('/namespace\s+([\w\\\\]+);/i', $cleanedContent, $namespace);
        preg_match_all(
            '/(?<!new\s)\b(?:abstract\s+|final\s+|readonly\s+)?(?:class|interface|trait)\s+([A-Za-z_][A-Za-z0-9_]*)\b/i',
            $cleanedContent,
            $matches
        );

        $classes = [];
        $namespace = $namespace[1] ?? '';

        foreach ($matches[1] ?? [] as $classname) {
            $fullClass = $namespace ? $namespace . "\\" . $classname : $classname;
            $classes[$fullClass] = $filePath;
        }

        return $classes;
    }

    private static function cleanPhpContent(string $content): string
    {
        // Remove comments
        $cleanedContent = preg_replace([
            '/\/\/.*$/m',          // single-line //
            '/#.*$/m',             // single-line #
            '/\/\*[\s\S]*?\*\//'   // multi-line /* */
        ], '', $content);

        // Remove all string literals (single or double quotes)
        $cleanedContent = preg_replace('/(["\'])(?:\\\\.|(?!\1).)*\1/s', '""', $cleanedContent);

        return $cleanedContent ?? '';
    }


    public static function getMethodDetails(string $class): array
    {
        if ($class === 'self::class' || $class === 'self') {
            $class = self::class;
        }

        $reflection = new ReflectionClass($class);
        $methods = $reflection->getMethods();
        $details = [];

        foreach ($methods as $method) {
            $details[$method->name] = [
                'visibility' => \Reflection::getModifierNames($method->getModifiers()),
                'is_static' => $method->isStatic(),
                'return_type' => self::getReturnType($method),
                'phpdoc' => $method->getDocComment() ?: 'No DocBlock',
                'parameters' => self::getMethodParameters($method)
            ];
        }

        return $details;
    }

    private static function getReturnType(ReflectionMethod $method): string
    {
        if (!$method->hasReturnType()) {
            return 'mixed';
        }

        $returnType = $method->getReturnType();
        return $returnType instanceof ReflectionUnionType
            ? implode('|', array_map(fn($t) => $t->getName(), $returnType->getTypes()))
            : $returnType->getName();
    }

    private static function getMethodParameters(ReflectionMethod $method): array
    {
        return array_map(function (ReflectionParameter $param) {
            return [
                'name' => $param->getName(),
                'type' => self::getParameterType($param),
                'optional' => $param->isOptional()
            ];
        }, $method->getParameters());
    }

    private static function getParameterType(ReflectionParameter $param): string
    {
        if (!$param->hasType()) {
            return 'mixed';
        }

        $type = $param->getType();
        return $type instanceof ReflectionUnionType
            ? implode('|', array_map(fn($t) => $t->getName(), $type->getTypes()))
            : $type->getName();
    }

    public static function autoload(string $class): bool
    {
        return self::method_x($class);
    }

    public static function method_x(string $class): bool
    {
        $methods = [
            'method_1',
            'method_2',
            'method_3',
            'method_4',
            'method_5',
            'method_6',
            'method_7'
        ];

        foreach ($methods as $method) {
            if (self::$method($class)) {
                return true;
            }
        }

        return false;
    }

    public static function dumpAutoload(bool $with_cache = false): void
    {

        $classes = self::scanForClasses(self::$root);
        $results = [];

        foreach ($classes as $class => $path) {
            $results[$class] = str_replace(self::$root, '', $path);
        }

        echo 'Updating main mapping...' . PHP_EOL;
        self::updateClassesMapping($results);

        if ($with_cache) {
            echo 'Updating cache mapping...' . PHP_EOL;
            self::updateCacheClassesMapping($results);
        }
    }

    public static function messageForResolvedClass(string $class, int $level): void
    {
        if (!self::$debug) {
            return;
        }

        $messages = [
            1 => 'Auto-loader: Loaded class [%s]',
            2 => 'Auto-loader: Resolved class [%s] (changed path)',
            3 => 'Auto-loader: Resolved class [%s] (from cache)',
            4 => 'Auto-loader: Resolved class [%s] (changed path, renamed)',
            5 => 'Auto-loader: Resolved class [%s] (system scan)',
            6 => 'Auto-loader: Resolved class [%s] (manual action)',
            7 => 'Auto-loader: Resolved class [%s] (temporary placeholder)'
        ];

        error_log(sprintf($messages[$level] ?? 'Auto-loader: Unrecognized level (%d), ignoring... have a nice day!', $class, $level));
    }

    public static function getLoadedClass(array|string $custom_filter = []): array
    {
        $filter = empty($custom_filter) ? array_keys(self::$classes) : (is_array($custom_filter) ? $custom_filter : [$custom_filter]);

        return array_intersect(get_declared_classes(), $filter);
    }

    public static function registerNewClass(string $class, string $location): void
    {
        if (self::$debug) {
            error_log('Auto-loader: Registered class [' . $class . ']');
        }

        self::$classes[$class] = $location;
        self::saveClassMap(self::$setting['classmap'], self::$classes);
    }

    public static function updateClassesMapping(array $classes): void
    {
        self::$classes = $classes;
        self::saveClassMap(self::$setting['classmap'], self::$classes);
    }

    public static function updateCacheClassesMapping(array $classes): void
    {
        self::$cache_classes = $classes;
        self::saveClassMap(self::$setting['cache_classmap'], self::$cache_classes);
    }

    private static function saveClassMap(string $filePath, array $data): void
    {
        file_put_contents($filePath, '<?php' . PHP_EOL . 'return ' . var_export($data, true) . ';');
    }

    public static function cachedResolvedClass(string $class, string $resolved_path): void
    {
        self::registerNewClass($class, $resolved_path);
        self::$cache_classes[$class] = $resolved_path;
        self::saveClassMap(self::$setting['cache_classmap'], self::$cache_classes);
    }

    public static function loadClassFromCache(string $class): string|false
    {
        if (isset(self::$cache_classes[$class]) && file_exists(self::$root . self::$cache_classes[$class])) {
            require_once self::$root . self::$cache_classes[$class];
            return self::$cache_classes[$class];
        }

        return false;
    }

    public static function resolve(string $class): string|false
    {
        $c = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $class);
        $class_base_name = basename($c);
        $guessed_class_path = self::$setting['where_to_look_class'] . $class_base_name . '.php';
        $full_path = self::$root . $guessed_class_path;

        if (file_exists($full_path)) {
            require_once $full_path;

            if (class_exists($class)) {
                self::registerNewClass($class, $guessed_class_path);
                return $guessed_class_path;
            }
        }

        return false;
    }

    public static function searchClass(string $class, string $dir, array $ignore_dirs = [], array $ignore_files = [], array $except_files = []): string|false
    {
        $classes = self::scanForClasses($dir, $ignore_dirs, $ignore_files, $except_files);
        return $classes[$class] ?? false;
    }

    public static function method_1(string $class): bool
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
        }

        if (self::$debug) {
            error_log('Auto-loader: Method 1 failed');
        }

        return false;
    }

    public static function method_2(string $class): bool
    {
        if (self::$debug) {
            error_log('Auto-loader: Using method 2');
        }

        if (self::resolve($class)) {
            return true;
        }

        if (self::$debug) {
            error_log('Auto-loader: Method 2 failed (not found in common path)');
        }

        return false;
    }

    public static function method_3(string $class): bool
    {
        if (self::$debug) {
            error_log('Auto-loader: Using method 3');
        }

        $path = self::loadClassFromCache($class);

        if ($path && class_exists($class, false)) {
            self::registerNewClass($class, $path);
            return true;
        }

        if (self::$debug) {
            error_log('Auto-loader: Method 3 failed (no cache for [' . $class . '])');
        }

        return false;
    }

    public static function method_4(string $class): bool
    {
        if (self::$debug) {
            error_log('Auto-loader: Using method 4');
        }

        $guessed_class_path_name = str_replace('\\', '/', $class) . '.php';
        $full_path = self::$root . $guessed_class_path_name;

        if (file_exists($full_path)) {
            require_once $full_path;

            if (class_exists($class, false)) {
                self::registerNewClass($class, $guessed_class_path_name);
                return true;
            }

            if (self::$debug) {
                error_log('Auto-loader: Method 4 failed (class not found)');
            }
        } elseif (self::$debug) {
            error_log('Auto-loader: Method 4 failed (file not exist)');
        }

        return false;
    }

    public static function method_5(string $class): bool
    {
        if (self::$debug) {
            error_log('Auto-loader: Using method 5');
        }

        if (!self::$auto_resolve) {
            if (self::$debug) {
                error_log('Auto-loader: Method 5 failed (skipped)');
            }
            return false;
        }

        $classes = self::scanForClasses(self::$root);
        $normalizedClasses = [];

        foreach ($classes as $cls => $path) {
            $normalizedClasses[$cls] = str_replace(self::$root, '', $path);
        }

        self::updateClassesMapping($normalizedClasses);

        if (isset($classes[$class])) {
            require_once $classes[$class];

            if (class_exists($class)) {
                self::cachedResolvedClass($class, $normalizedClasses[$class]);
                return true;
            }

            if (self::$debug) {
                error_log('Auto-loader: Method 5 failed (class, invalid?)');
            }
        } elseif (self::$debug) {
            error_log('Auto-loader: Method 5 failed (class cant be found)');
        }

        return false;
    }

    public static function method_6(string $class): bool
    {
        if (self::$debug) {
            error_log('Auto-loader: Using method 6');
            error_log('Auto-loader: Method 6 failed (skipped)');
        }

        return false;
    }

    public static function method_7(string $class): bool
    {
        if (self::$debug) {
            error_log('Auto-loader: Using method 7');
        }

        return true;
        error_log('Auto-loader: Method 7 failed (skipped)');
        return false;
    }
}
