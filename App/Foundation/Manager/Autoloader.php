<?php

declare(strict_types=1);

namespace App\Foundation\Manager;

use Attribute;
use Exception;
use FilesystemIterator;
use Generator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Dep
{
    public function __construct(public string $name) {}
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Boot
{
    public function __construct(public array|string $name) {}
}

class AutoloaderException extends Exception {}
class AutoloaderRuntimeException extends RuntimeException {}

class Autoloader
{
    /** 
     * @var string $root Where Autoloader expect project's root is
     */
    private static string $root;

    /** 
     * @var int $flags Flags for Autoloader
     */
    private static int $flags;

    /**
     * @var array<string, array{filepath: string, depends: string[], boot: string[]}> $classes Autoloader main classmap
     */
    private static array $classes;

    /**
     * @var array<string, array{filepath: string, depends: string[], boot: string[]}> $classes Autoloader cache classmap
     */
    private static array $cache_classes;

    /**
     * @var array{
     *  classmap: string,
     *  cache_classmap: string,
     *  where_to_look_class: string,
     *  system_scan:array{
     *      root-scan: bool,
     *      prioritize: string[]
     *  },
     *  psr-4: array<string, string>
     * } $setting Autoloader settings
     */
    private static array $setting;

    /**
     * Attribute classes aliases
     *
     * @var array<string, string>
     */
    private static array $depAliases = [];

    /**
     * @var array $files Files to be required, relative to root project
     */
    private static array $files;

    /**
     * @var bool $is_initialized Autoloader state, true for mapping has been loaded otherwise false when not ready
     */
    private static bool $is_initialized = false;

    /**
     * @var bool $cold Autoloader state, true when autoloader has not yet build deps graph
     */
    private static bool $cold = false;

    /**
     * On off switch to deps and boot resolution
     *
     * @var boolean $resolution
     */
    private static bool $resolution = true;

    /**
     *  Enable debug mode and print autolaoder actions
     */
    public const DEBUG = 1;

    /**
     * Include to make autolaoder resolve classes, exclude to make autoloader rely only on classmap
     */
    public const AUTO_RESOLVE = 2;

    /**
     * Include to handle cold boot, and regenerate classmap if deleted
     */
    public const AUTO_INIT = 4;

    /**
     * Check filemtime then automatically rescan if file has been changed
     */
    public const CHECK_FILEMTIME = 8;

    /**
     * Force autoloader to only can read. No file operation, nothing. Just read
     */
    public const READ_ONLY = 16;

    /**
     * Enable Depedency resolution
     */
    public const DEP_RESOLUTION = 32;

    /**
     * Enable Boot resolution
     */
    public const BOOT_RESOLUTION = 64;

    private const EMPTY_CLASSMAP_TEMPLATE = "<?php\nreturn [];\n";

    /**
     * Initializes the class manager by loading the class mappings from the configuration file.
     * 
     * @param string $root
     * @param int $flags
     * @param array{
     *     classmap: string,
     *     cache_classmap: string,
     *     where_to_look_class: string,
     *     except: string[],
     *     psr-4: string[],
     *     system-scan: array{
     *         ignore: string[],
     *         prioritize: string[],
     *         root-scan: bool
     *     }
     * } $setting
     * @param string[] $files
     */
    public static function setup(
        string $root,
        array $setting = [
            'classmap' => 'path/to/class/map.php',
            'cache_classmap' => 'path/to/cache/class/map.php',
            'where_to_look_class' => 'path/to/dir/containing/class.php',
            'except' => [],
            'psr-4' => [],
            'system_scan' => [
                'prioritize' => [],
                'ignore' => [],
                'root-scan' => false
            ],
        ],
        array $files = [],
        int $flags = 0,
    ): void {
        $dummy_setting = [
            'classmap' => 'path/to/class/map.php',
            'cache_classmap' => 'path/to/cache/class/map.php',
            'where_to_look_class' => 'path/to/dir/containing/class.php',
            'except' => [],
            'psr-4' => [],
            'system_scan' => [],
        ];


        if ($setting === $dummy_setting) {
            throw new Exception('Please define path first');
        }
        self::$flags = $flags;
        self::$root = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
        $setting['except'] = array_fill_keys($setting['except'], true);
        self::$setting = $setting;

        $required = ['classmap', 'cache_classmap', 'where_to_look_class'];
        foreach ($required as $key) {
            if (empty($setting[$key])) {
                throw new AutoloaderRuntimeException("Missing required setting: {$key}");
            }
        }
        self::$files = $files;

        if (!self::$is_initialized) {
            self::createClassMapFilesIfNotExists();

            self::$classes = self::require(self::$setting['classmap']);
            self::$cache_classes = self::require(self::$setting['cache_classmap']);

            if ((self::AUTO_INIT & $flags) && empty(self::$classes)) {
                self::$cold = true;
                $res = [];

                foreach (self::systemScan(true, true) as $class => $spec) {
                    $spec['filepath'] = self::normalizePathToRelative($spec['filepath']);
                    $res[$class] = $spec;
                }

                // var_dump($res);

                // var_dump($res);
                // die;

                self::updateClassesMapping($res);
                self::updateCacheClassesMapping($res);
            }

            self::$setting['where_to_look_class'] = rtrim(self::$setting['where_to_look_class'], '/\\') . DIRECTORY_SEPARATOR;
            self::$is_initialized = true;

            self::log('Auto-loader: initialized.');
        } else {
            self::log('Auto-loader: skipped initialization because classes are already loaded.');
        }
    }

    private static function systemScan(
        bool $check_filemtime = true,
        bool $skip_dep_check = false
    ): Generator {
        $ignoredDirs = array_map(fn($d) => rtrim($d, '/') . '/', self::$setting['system_scan']['ignore'] ?? []);
        $prioritized = self::$setting['system_scan']['prioritize'] ?? [];
        $rootScan    = self::$setting['system_scan']['root-scan'] ?? false;
        $ignoredDirsRoot = $ignoredDirs;

        foreach ($prioritized as $dir) {
            $dir = trim($dir, '/');

            $tobeIgnored = [];
            foreach ($ignoredDirs as $key => $ignore) {
                if (strncmp($ignore, $dir, strlen($dir)) === 0) {
                    $tobeIgnored[] = substr($ignore, strlen($dir) + 1);
                    unset($ignoredDirs[$key]);
                }
            }
            // var_dump($tobeIgnored);

            yield from self::scanForClasses(
                self::$root . "/{$dir}",
                $check_filemtime,
                $skip_dep_check,
                $tobeIgnored
            );

            $ignoredDirsRoot[] = "{$dir}/";
        }

        if ($rootScan) {
            yield from self::scanForClasses(self::$root, $check_filemtime, $skip_dep_check, $ignoredDirsRoot);
        }
    }

    public static function registerAttributeAliases(array $aliases)
    {
        self::$depAliases = $aliases;
        foreach (self::$depAliases as $alias => $designated) {
            if ($designated !== Dep::class && $designated !== Boot::class) {
                throw new AutoloaderRuntimeException(sprintf(
                    'The designated alias [%s] was not compatible with supported attributes',
                    $designated
                ));
            }
            class_alias($designated, $alias);
            self::$depAliases[$alias] = $designated;
            self::$depAliases[$designated] = $alias;
        }
    }

    public static function registerAutoloader(): bool
    {
        if (self::$is_initialized) {
            foreach (self::$files as $file) {
                require_once self::$root . $file;
            }
            $res = spl_autoload_register([self::class, 'method_x'], true);
            if (self::$cold) {
                self::$cold = false;
                foreach (self::$classes as $class => $spec) {
                    $deps = self::getSetup($class);
                    $spec['boot'] = $deps['boot'];
                    $spec['depends'] = $deps['depends'];
                    self::$classes[$class] = $spec;
                }
                self::updateClassesMapping(self::$classes);
                self::updateCacheClassesMapping(self::$classes);
            }
            return $res;
        }

        throw new AutoloaderException("Autoloader not yet initialized");
    }

    private static function createClassMapFilesIfNotExists(): void
    {
        foreach (['classmap', 'cache_classmap'] as $mapType) {
            $dir = dirname(self::$setting[$mapType]);
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                    throw new AutoloaderRuntimeException("Failed to create directory: $dir");
                }
            }
            if (!file_exists(self::$setting[$mapType])) {
                file_put_contents(self::$setting[$mapType], self::EMPTY_CLASSMAP_TEMPLATE, LOCK_EX);
            }
        }
    }

    private static function require(string $filePath): array
    {
        $loaded = require $filePath;
        return is_array($loaded) ? $loaded : [];
    }

    public static function getClassFile(string $class): array
    {
        return self::$classes[$class] ?? false;
    }

    public static function scanForClasses(
        string $directory,
        bool $check_filemtime = false,
        bool $skipDepCheck = false,
        array $ignore_dirs = [],
        array $ignore_files = [],
        array $except_files = []
    ): array {
        $directory = rtrim(realpath($directory), DIRECTORY_SEPARATOR);
        $classes = [];
        $visited = [];

        // Normalize ignore dirs (absolute + trailing slash)
        $ignoreDirsNormalized = [];
        foreach ($ignore_dirs as $dir) {
            $fullPath = realpath($directory . DIRECTORY_SEPARATOR . trim($dir, '/\\'));
            if ($fullPath !== false) {
                $ignoreDirsNormalized[] = str_replace('\\', '/', $fullPath) . '/';
            }
        }

        // Fast lookup maps
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
                    &$visited
                ): bool {
                    $realPath = str_replace('\\', '/', $file->getRealPath());

                    // Prevent recursion loops
                    if (isset($visited[$realPath])) {
                        return false;
                    }
                    $visited[$realPath] = true;

                    // Ignore matching directories
                    if ($file->isDir()) {
                        foreach ($ignoreDirsNormalized as $ignorePath) {
                            if (str_starts_with($realPath . '/', $ignorePath)) {
                                return false;
                            }
                        }
                    }

                    return true;
                }
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = substr(str_replace('\\', '/', $file->getPathname()), strlen($directory) + 1);

                // Skip ignored files unless excepted
                if (isset($ignoreFilesSet[$relativePath]) && !isset($exceptFilesSet[$relativePath])) {
                    continue;
                }

                foreach (self::extractClassesFromFile($file->getPathname(), $check_filemtime, $skipDepCheck) as $class => $specs) {
                    $classes[$class] = $specs;
                }
            }
        }

        return $classes;
    }


    /**
     *  Get a class's @depends and @boot
     *
     * @param string $classname
     * @return array{depends: array, boot: array}
     */
    private static function getSetup(string $classname): array
    {
        $result = [
            'depends' => [],
            'boot' => [],
        ];

        $reflectClass = new ReflectionClass($classname);

        foreach ($reflectClass->getAttributes() as $dep) {
            if (is_a($dep->getName(), Dep::class, true)) {
                $dep = $dep->newInstance();
                $result['depends'][] = $dep->name;
            }
        }

        foreach ($reflectClass->getAttributes() as $boot) {
            if(is_a($boot->getName(), Boot::class, true)){
                $boot = $boot->newInstance();
                $result['boot'][] = $boot->name;
            }
        }

        return $result;
    }


    /**
     * Extract classes from a file with their @depends and @boot
     *
     * @param string $filePath
     * @return array<string, array{filepath: string, depends: string[], boot: string[]}>
     */
    private static function extractClassesFromFile(string $filePath, bool $check_filemtime = false, bool $skipDepCheck = false): array
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
        $namespace = $namespace[1] ?? '';

        // Find all class-like declarations
        preg_match_all(
            '/(?<!new\s)\b(?:abstract\s+|final\s+|readonly\s+)?(?:class|interface|trait)\s+([A-Za-z_][A-Za-z0-9_]*)\b/i',
            $cleanedContent,
            $matches
        );

        $classes = [];

        foreach ($matches[1] ?? [] as $classname) {
            // echo "hi: {$filePath}" .PHP_EOL;
            // require_once $filePath;

            $fullClass = $namespace ? $namespace . "\\" . $classname : $classname;
            $directives = $skipDepCheck ? ['depends' => [], 'boot' => []] : self::getSetup($fullClass);

            $classes[$fullClass] = [
                'filepath' => $filePath,
                'depends'  => $directives['depends'],
                'boot'     => $directives['boot'],
                'filemtime' => $check_filemtime ? filemtime($filePath) : 0
            ];
        }

        return $classes;
    }


    /**
     * Clean php content from single-line comment and multi-line comment
     *
     * @param string $content
     * @return string
     */
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


    private static function log($msg)
    {
        if (self::DEBUG & self::$flags) error_log($msg);
    }

    public static function loadAll(): void
    {
        foreach (self::$classes as $class => $spec) {
            self::loadClass($class, false, $spec);
        }
    }

    public static function enableResolution(bool $toggle = true)
    {
        self::$resolution = $toggle;
    }

    /**
     * Load class and resolve init methods and dependencies
     *
     * @param array{filepath: string, depends: string[], boot: string[], filemtime: int} $class
     * @param string $classname The fully qualified class name being loaded
     * @return bool
     */
    private static function loadClass(string $classname, bool $check_filemtime, array $spec)
    {
        $classPath = self::$root . $spec['filepath'];
        if ($check_filemtime) {
            if (filemtime($classPath) > $spec['filemtime']) {
                return false;
            }
        }

        $classDir = dirname($classPath) . DIRECTORY_SEPARATOR;

        if (self::$resolution && (self::$flags & self::DEP_RESOLUTION)) {
            foreach ($spec['depends'] ?? [] as $dependency) {
                self::log("Auto-loader: Begin attempt to resolve dependency [{$dependency}] from class [{$classname}]");
                if (strpos($dependency, '.php') !== false) {
                    $depPath = realpath($classDir . $dependency);
                    if ($depPath && strpos($depPath, self::$root) === 0) {
                        self::log("Auto-loader: Resolved file dependency [{$dependency}] from class [{$classname}]");
                        require_once $depPath;
                    } else {
                        throw new AutoloaderRuntimeException("Invalid dependency path: [{$dependency}]");
                    }
                    continue;
                } else if (!$exist =  self::exists($dependency)) {
                    self::method_x($dependency);
                    self::log("Auto-loader: Resolved class dependency [{$dependency}] from class [{$classname}]");
                    continue;
                }

                if ($exist) {
                    self::log("Auto-loader: Skipping class dependency [{$dependency}] from class [{$classname}] because its already loaded");
                    continue;
                }

                self::log("Auto-loader: Cannot resolve dependency [{$dependency}] from class [{$classname}]");
            }
        }

        require_once $classPath;

        if (self::$resolution && (self::$flags & self::BOOT_RESOLUTION)) {
            foreach ($spec['boot'] ?? [] as $setup) {
                $isArr = is_array($setup);
                $setupName = $isArr ? $setup[1] : $setup;
                self::log("Auto-loader: Begin attempt to boot [$setupName] from class [{$classname}]");
                if ($isArr) {
                    [$initClass, $method] = $setup;
                    if (method_exists($initClass, $method)) {
                        call_user_func([$initClass, $method]);
                        self::log("Auto-loader: Called [{$setupName}] boot method successfully");
                    } elseif (self::DEBUG & self::$flags) {
                        self::log("Auto-loader: Boot method not found: [{$setupName}]");
                    }
                    continue;
                } elseif (function_exists($setup)) {
                    $setup();
                    self::log("Auto-loader: Called [{$setupName}] boot function successfully");
                    continue;
                } elseif (method_exists($classname, $setup)) {
                    call_user_func([$classname, $setup]);
                    self::log("Auto-loader: Called [{$setupName}] boot method successfully");
                    continue;
                }

                self::log("Auto-loader: Invalid boot callable: [{$setupName}] from class [{$classname}]");
            }
        }

        return true;
    }

    /**
     * Normalize a path to be relative to Autoloader defined root
     *
     * @param string $path
     * @return string
     */
    private static function normalizePathToRelative(string $path): string
    {
        return str_replace(self::$root, '', $path);
    }


    /**
     * Method_# entry point to resolve class
     *
     * @param string $class
     * @return boolean True on success, false otherwise
     */
    public static function method_x(string $class): bool
    {
        if (isset(self::$setting['except'][$class])) {
            return false;
        }
        $methods = [
            'method_1',
            'method_2',
            'method_3',
            'method_4',
            'method_5',
        ];

        foreach ($methods as $method) {
            if (self::{$method}($class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dump classmap
     *
     * @param boolean $with_cache Will also replace cache map if true
     * @return void
     */
    public static function dumpAutoload(bool $with_cache = false): void
    {
        self::enableResolution(false);

        $res = [];
        foreach (self::systemScan(true, false) as $cls => $spec) {
            $spec['filepath'] = self::normalizePathToRelative($spec['filepath']);
            $res[$cls] = $spec;
        }
        unset($classes);

        echo 'Updating main mapping...' . PHP_EOL;
        self::updateClassesMapping($res);

        if ($with_cache) {
            echo 'Updating cache mapping...' . PHP_EOL;
            self::updateCacheClassesMapping($res);
        }
    }

    /**
     * Message for resolved class
     *
     * @param string $class
     * @param integer $level
     * @return void
     */
    private static function messageForResolvedClass(string $class, int $level): void
    {
        if (!(self::DEBUG & self::$flags)) {
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

    /**
     * Get loaded class
     *
     * @param array $custom_filter
     * @return array
     */
    public static function getLoadedClass(array|string $custom_filter = []): array
    {
        $filter = empty($custom_filter) ? array_keys(self::$classes) : (is_array($custom_filter) ? $custom_filter : [$custom_filter]);

        return array_intersect(get_declared_classes(), $filter);
    }

    /**
     * Register a class or classes to main classmap
     *
     * @param string|array $class
     * @param array $specs
     * @return void
     */
    private static function registerNewClass(string|array $class, array $specs): void
    {
        if (is_array($class)) {
            $tmp = array_combine($class, $specs);
            foreach ($tmp as $cls => $spec) {
                self::log("Auto-loader: Registered class [{$cls}]");
                self::$classes[$cls] = $spec;
            }
        } else {
            self::log('Auto-loader: Registered class [' . $class . ']');
            self::$classes[$class] = $specs;
        }
        self::saveClassMap(self::$setting['classmap'], self::$classes);
    }

    /**
     * Update main classmap
     *
     * @param array $classes
     * @return void
     */
    private static function updateClassesMapping(array $classes): void
    {
        self::$classes = $classes;
        self::saveClassMap(self::$setting['classmap'], self::$classes);
    }

    /**
     * update cache classmap
     *
     * @param array $classes
     * @return void
     */
    private static function updateCacheClassesMapping(array $classes): void
    {
        self::$cache_classes = $classes;
        self::saveClassMap(self::$setting['cache_classmap'], self::$cache_classes);
    }

    /**
     * Update or overwrite a file to store mapping
     *
     * @param string $filePath
     * @param array $data
     * @return void
     */
    private static function saveClassMap(string $filePath, array $data): void
    {

        if (self::$flags & self::READ_ONLY) {
            self::log("Auto-loader: READ_ONLY active — skipping save for [$filePath]");
            return;
        }

        foreach ($data as &$class) {
            $class['depends'] = (array)($class['depends'] ?? []);
            $class['boot']    = (array)($class['boot'] ?? []);
        }

        $dir = dirname($filePath);
        $tmp = $dir . '/.' . basename($filePath) . '.' . bin2hex(random_bytes(4)) . '.tmp';

        $content = '<?php' . PHP_EOL . 'return ' . var_export($data, true) . ';' . PHP_EOL;

        $fp = fopen($tmp, 'wb');
        if (!$fp) {
            throw new RuntimeException("Failed to open temp file for writing: $tmp");
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new RuntimeException("Failed to acquire lock on temp file: $tmp");
            }

            if (fwrite($fp, $content) === false) {
                throw new RuntimeException("Failed to write to temp file: $tmp");
            }

            fflush($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }

        // Preserve permissions if file exists
        if (file_exists($filePath)) {
            $perms = fileperms($filePath) & 0777;
            @chmod($tmp, $perms);
        }

        // Atomic replace
        if (!@rename($tmp, $filePath)) {
            @unlink($tmp);
            throw new RuntimeException("Failed to replace $filePath with $tmp");
        }
    }



    /**
     * Cached a class or classes to cache class map
     *
     * @param string|array $class
     * @param array $specs
     * @return void
     */
    private static function cachedResolvedClass(string|array $class, array $specs): void
    {
        self::registerNewClass($class, $specs);
        if (is_array($class)) {
            $tmp = array_combine($class, $specs);
            foreach ($tmp as $cls => $spec) {
                self::$cache_classes[$cls] = $spec;
            }
        } else {
            self::$cache_classes[$class] = $specs;
        }
        self::saveClassMap(self::$setting['cache_classmap'], self::$cache_classes);
    }

    /**
     * Load class from cache
     *
     * @param string $class
     * @return array|false
     */
    private static function loadClassFromCache(string $class): array|false
    {
        if (isset(self::$cache_classes[$class]) && file_exists(self::$root . self::$cache_classes[$class]['filepath'])) {
            self::loadClass($class, false, self::$cache_classes[$class]);
            return self::$cache_classes[$class];
        }

        return false;
    }

    /**
     * Resolve class based on PSR-4
     *
     * @param string $class
     * @return string|false
     */
    private static function resolve(string $class): string|false
    {
        $c = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $class);
        $class_base_name = basename($c);
        $guessed_class_path = self::$setting['where_to_look_class'] . $class_base_name . '.php';
        $full_path = self::$root . $guessed_class_path;

        if (file_exists($full_path)) {
            require_once $full_path;

            if (self::exists($class) && !(self::READ_ONLY & self::$flags)) {
                $specs = self::extractClassesFromFile($full_path);
                self::registerNewClass(array_keys($specs), array_map(function ($arr) {
                    $arr['filepath'] = self::normalizePathToRelative($arr['filepath']);
                    return $arr;
                }, array_values($specs)));
                return $guessed_class_path;
            }
        }

        return false;
    }

    /**
     * Check a class or trait or interface is exist or not
     *
     * @param string $classOrTraitOrInterface
     * @return boolean
     */
    public static function exists(string $classOrTraitOrInterface): bool
    {
        return class_exists($classOrTraitOrInterface, false) || trait_exists($classOrTraitOrInterface, false) || interface_exists($classOrTraitOrInterface, false);
    }

    public static function method_1(string $class): bool
    {
        self::log('Auto-loader: Using method 1');

        if (isset(self::$classes[$class]) && file_exists(self::$root . self::$classes[$class]['filepath'])) {
            $res = self::loadClass($class, (bool)(self::CHECK_FILEMTIME & self::$flags), self::$classes[$class]);
            if (!$res && !(self::READ_ONLY & self::$flags)) {
                // var_dump(['must refresh' => !$bool]);
                self::log("Auto-loader: Re-scanning class [{$class}] because its content has been edited");
                require_once self::$root . self::$classes[$class]['filepath'];
                $extracted = self::extractClassesFromFile(self::$classes[$class]['filepath'], (bool)(self::CHECK_FILEMTIME & self::$flags));
                self::registerNewClass(array_keys($extracted), array_map(function ($arr) {
                    $arr['filepath'] = self::normalizePathToRelative($arr['filepath']);
                    return $arr;
                }, array_values($extracted)));
                if (isset($extracted[$class])) {
                    self::loadClass($class, false, $extracted[$class]);
                }
            }

            if (self::exists($class)) {
                self::messageForResolvedClass($class, 1);
                return true;
            }
        }

        self::log('Auto-loader: Method 1 failed');

        return false;
    }

    public static function method_2(string $class): bool
    {
        self::log('Auto-loader: Using method 2');

        if (self::resolve($class)) {
            return true;
        }

        self::log('Auto-loader: Method 2 failed (not found in common path)');

        return false;
    }

    public static function method_3(string $class): bool
    {

        self::log('Auto-loader: Using method 3');


        $spec = self::loadClassFromCache($class);

        if (self::exists($class)) {
            if (!(self::READ_ONLY & self::$flags)) self::registerNewClass($class, $spec);
            return true;
        }

        self::log('Auto-loader: Method 3 failed (no cache for [' . $class . '])');

        return false;
    }

    public static function method_4(string $class): bool
    {
        self::log('Auto-loader: Using method 4');

        // Lazy path generator
        $paths = (function () use ($class) {
            foreach (self::$setting['psr-4'] ?? [] as $prefix => $baseDir) {
                $prefix = rtrim($prefix, '\\') . '\\';
                $baseDir = rtrim(self::$root . $baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

                if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
                    self::log("Auto-loader: Namespace mismatch -> [$prefix] skipped for $class");
                    continue;
                }

                $relativeClass = substr($class, strlen($prefix));
                yield $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
            }

            yield self::$root . str_replace('\\', '/', $class) . '.php';
        })();

        // Consume generator lazily
        foreach ($paths as $path) {
            if (file_exists($path)) {
                require_once $path;

                if (self::exists($class)) {
                    $specs = self::extractClassesFromFile($path);
                    self::registerNewClass(
                        array_keys($specs),
                        array_map(fn($arr) => [
                            ...$arr,
                            'filepath' => self::normalizePathToRelative($arr['filepath']),
                        ], array_values($specs))
                    );
                    return true;
                }
            }
        }

        self::log('Auto-loader: Method 4 failed');
        return false;
    }


    public static function method_5(string $class): bool
    {
        self::log('Auto-loader: Using method 5');

        if (!(self::AUTO_RESOLVE & self::$flags)) {
            self::log('Auto-loader: Method 5 failed (skipped)');
            return false;
        }

        $classes = self::systemScan(true, true);

        $normalizedClasses = [];

        foreach ($classes as $cls => $spec) {
            $spec['filepath'] = self::normalizePathToRelative($spec['filepath']);
            $normalizedClasses[$cls] = $spec;

            if ($class === $cls) {
                require_once $spec['filepath'];

                if (self::exists($class)) {
                    self::cachedResolvedClass($class, $spec);
                    return true;
                }

                self::log('Auto-loader: Method 5 failed (class, invalid?)');
            } else {
                self::log('Auto-loader: Method 5 failed (class cant be found)');
            }
        }

        self::updateClassesMapping($normalizedClasses);

        return false;
    }
}
