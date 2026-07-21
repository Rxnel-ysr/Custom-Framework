<?php

use App\App;
use App\Debug\Debugger;
use App\Foundation\Compiler\Compile;
use App\Foundation\Exceptions\Http\NotFoundException;
use App\Foundation\Exceptions\Http\UnauthorizedException;
use App\Foundation\Manager\InstanceManager;
use App\Foundation\Model;
use App\Support\Facades\DI;
use App\Support\Facades\Route;

// $views = ROOT . '/resources/views';

/**
 * Will include given views when the URL is matched or execute a callback.
 * @deprecated
 */
function route_($uri, $viewOrCallback): void
{
    global $views;
    global $requestUri;

    if ($requestUri === $uri) {
        if (is_callable($viewOrCallback)) {
            $viewOrCallback();  // Execute the callback
        } else {
            $viewPath = $views . '/' . $viewOrCallback . '.php';
            if (file_exists($viewPath)) {
                include_once $viewPath;
            } else {
                Debugger::showErrorPage(404);
            }
        }
        exit();
    }
}

/**
 * Same like route, but for backend
 * @deprecated
 */
function backRoute($uri, $part): void
{
    global $resources;
    global $requestUri;
    if ($requestUri === $uri) {
        $backPart = $resources . '/' . $part . '.php';

        if (file_exists($backPart)) {
            include_once $backPart;
            exit();
        } else {
            Debugger::showErrorPage(404);
        }
    }
}

function view($view, array $data = [], $return = true)
{
    return Compile::compile(str_replace('.', DIRECTORY_SEPARATOR, $view), $data, $return);
}

function asset(string $path): string
{
    $protocol =
        !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
        ? 'https'
        : 'http';

    $host = $_SERVER['HTTP_HOST'];

    $path = ltrim($path, '/');
    return "{$protocol}://{$host}/public/{$path}";
}

function media(string $path): string
{
    $protocol =
        !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
        ? 'https'
        : 'http';

    $host = $_SERVER['HTTP_HOST'];

    $path = ltrim($path, '/');
    return "{$protocol}://{$host}/storage/{$path}";
}

function restrictedUri(array $restrictedUris): void
{
    global $requestUri;
    if (in_array($requestUri, $restrictedUris)) {
        Utils::log(
            "WARN - User tried to access prohibited uri: '{$requestUri}'"
        );
        Debugger::showErrorPage(403);
    }
}

function redirectHome(array $redirect): void
{
    global $requestUri;
    if (in_array($requestUri, $redirect)) {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Location: /home', true, 301);
        Utils::log("OK - Redirecting user from '{$requestUri}' to '/home'");
        exit();
    }
}

function redirectBack($code = 302): void
{
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    if (!isset($_SERVER['HTTP_REFERER'])) {
        error_log('redirectBack: No HTTP_REFERER found!');
        exit;
    }

    header('Location: ' . $_SERVER['HTTP_REFERER'], true, $code);
    error_log("redirectBack: Redirecting to " . $_SERVER['HTTP_REFERER']);
    // exit;
}


function getReferer()
{
    return $_SERVER['HTTP_REFERER'];
}

function getRequestMethod()
{
    return $_SERVER['REQUEST_METHOD'];
}

function redirect($uri, $code = 200): void
{
    if (headers_sent()) {
        error_log("Headers already sent!");
    }
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Location: ' . $uri, true, $code);
    exit;
}

// Arrays

/**
 * Check if two arrays are identical in terms of values and order.
 *
 * @param array $a First array.
 * @param array $b Second array.
 * @return bool True if arrays are identical, otherwise false.
 */
function checkDiffArr($a, $b)
{
    return (count($a) === count($b) && empty(array_diff($a, $b)) && empty(array_diff($b, $a)));
}

/**
 * Get non-intersecting elements from two arrays.
 *
 * @param array $a First array.
 * @param array $b Second array.
 * @return array Array containing elements that are in either $a or $b but not in both.
 */
function arrayNonIntersect(array $a, array $b): array
{
    return array_values(array_merge(array_diff($a, $b), array_diff($b, $a)));
}

/**
 * Filter an array to keep only the specified keys.
 *
 * @param array $arr The original array.
 * @param array $keysToKeep The keys to retain in the array.
 * @return array Filtered array containing only the specified keys.
 */
function filterArrayToKeep(array $arr, array $keysToKeep): array
{
    return array_intersect_key($arr, array_flip($keysToKeep));
}

/**
 * Get only the intersecting elements from two arrays.
 *
 * @param array $a First array.
 * @param array $b Second array.
 * @return array Array containing elements present in both $a and $b.
 */
function arrayIntersectOnly(array $a, array $b): array
{
    return array_values(array_intersect($a, $b));
}

// function compacts(...$keys)
// {
//     // $vars = get_defined_vars();
//     $result = [];

//     foreach ($keys as $key) {
//         if (isset($GLOBALS[$key])) {
//             $result[$key] = $GLOBALS[$key];
//         }
//     }

//     return $result;
// }

function compacts(...$keys)
{
    $result = [];

    $callerScope = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['args'] ?? [];

    foreach ($keys as $key) {
        if (isset($GLOBALS[$key])) {
            $result[$key] = $GLOBALS[$key];
        } elseif (array_key_exists($key, $callerScope)) {
            $result[$key] = $callerScope[$key];
        }
    }

    return $result;
}

function get_precise_type(mixed $var): string
{
    if (is_array($var)) {
        $keyTypes = [];
        $valTypes = [];

        foreach ($var as $k => $v) {
            $keyTypes[] = get_precise_type($k);
            $valTypes[] = get_precise_type($v);
        }

        $keyType = count(array_unique($keyTypes)) === 1 ? $keyTypes[0] : 'mixed';
        $valType = count(array_unique($valTypes)) === 1 ? $valTypes[0] : 'mixed';

        return "array<$keyType, $valType>";
    }

    if (is_object($var)) return 'object<' . get_class($var) . '>';
    if (is_string($var)) return 'string';
    if (is_int($var)) return 'int';
    if (is_float($var)) return 'float';
    if (is_bool($var)) return 'bool';
    if (is_null($var)) return 'null';
    if (is_resource($var)) return 'resource';

    return 'unknown';
}



/**
 * Calls a function with the given parameters, supporting automatic depency resolution.
 *
 * @param callable|string|array $func The function, method, or callable array to invoke.
 * @param bool $auto_resolve Whether to automatically resolve class dependencies.
 * @param mixed $params Parameters to pass to the function.
 *
 * @throws InvalidArgumentException If the callable is invalid or required parameters are missing.
 *
 * @return mixed The result of the function execution.
 */
// function callFuncWithParams(callable|string|array $func, bool $strict = false, bool $auto_resolve = false, array $params = [])
// {
//     $args = [];
//     // var_dump($func);

//     if (is_callable($func)) {
//         if (is_array($func)) {
//             $ref = new ReflectionMethod($func[0], $func[1]);
//         } elseif (is_string($func) && function_exists($func)) {
//             $ref = new ReflectionFunction($func);
//         } else {
//             $ref = new ReflectionFunction($func);
//         }
//     } else {
//         throw new InvalidArgumentException('Invalid callable provided.');
//     }

//     $isAssoc = !empty($params) && !is_numeric(array_keys($params)[0]);
//     // error_log('Is assoc: '.var_export($isAssoc,true));

//     foreach ($ref->getParameters() as $i => $param) {
//         // error_log("I = $i");
//         if ($auto_resolve) $i--;
//         // error_log("I = $i");
//         // error_log('Param: '.$param);
//         // error_log("Length of param" . count($params));
//         // error_log(isset($params[$i]) ? "isset for $i" : "Not set for $i");
//         $name = $param->getName();
//         $type = $param->getType();

//         if ($auto_resolve && $type && !$type->isBuiltin()) {
//             $className = $type->getName();
//             // error_log('Called auto resolve:'.var_export( $className,true));
//             $args[] = InstanceManager::getInstance($className);
//         } elseif ($isAssoc && array_key_exists($name, $params)) {
//             $args[] = $params[$name];
//         } elseif (!$isAssoc && isset($params[$i])) {
//             // error_log('This enter here: '.$params[$i]);
//             if ($strict && $type && $type->isBuiltin()) {
//                 if (get_debug_type($params[$i]) === $type->getName()) {
//                     $args[] = $params[$i];
//                 } else {
//                     throw new InvalidArgumentException("Type mismatch for parameter: $name");
//                 }
//             } else {
//                 // error_log('CIhut: '.$params[$i]);
//                 $args[] = $params[$i];
//             }
//         } elseif ($param->isOptional()) {
//             $args[] = $param->getDefaultValue();
//         } else {
//             throw new InvalidArgumentException("Missing required parameter: $name");
//         }
//     }
//     // error_log('This is args: '.var_export($args,true));
//     // // die;

//     return $ref instanceof ReflectionFunction
//         ? $ref->invokeArgs($args)
//         : $ref->invokeArgs(is_array($func) ? $func[0] : null, $args);
// }

/**
 * Executes a callable with intelligent parameter resolution.
 *
 * Supports:
 * - Dependency injection (auto-resolution of class instances)
 * - Strict type checking
 * - Both associative and indexed parameters
 * - Method calls on objects
 *
 * @param callable|array|string $callable Function/method to execute
 * @param array $params Parameters (associative or indexed)
 * @param bool $strict Enable strict type checking
 * @param bool $autoResolve Enable DI auto-resolution
 * @return mixed Execution result
 * @throws InvalidArgumentException|ReflectionException
 */
function callFuncWithParams(
    callable|array|string $callable,
    array $params = [],
    bool $strict = false,
    bool $autoResolve = false
) {
    // Normalize callable format
    if (is_string($callable) && str_contains($callable, '::')) {
        $callable = explode('::', $callable);
    }

    // Create reflection object
    $reflection = is_array($callable)
        ? new ReflectionMethod($callable[0], $callable[1])
        : new ReflectionFunction($callable);

    $args = [];
    $isAssoc = array_is_list($params) === false;

    foreach ($reflection->getParameters() as $index => $param) {
        $paramName = $param->getName();
        $paramType = $param->getType();

        // Dependency injection
        if ($autoResolve && $paramType instanceof ReflectionNamedType && !$paramType->isBuiltin()) {
            $name = $paramType->getName();
            $instance = (isset($params[$index]) && $params[$index] instanceof  $name) ? $params[$index] : InstanceManager::getInstance('container')->make($name);
            $res = $instance instanceof Model && $isAssoc && arr_key_exists($paramName, $params) ? $instance->findOrFail($params[$paramName]) : $instance;
            $args[] = Route::setParameter($paramName, $res);
            continue;
        }

        // Parameter value resolution
        if ($isAssoc && arr_key_exists($paramName, $params)) {
            $value = $params[$paramName];
        } elseif (!$isAssoc && array_key_exists($index, $params)) {
            $value = $params[$index];
        } elseif ($param->isDefaultValueAvailable()) {
            $args[] = $param->getDefaultValue();
            continue;
        } else {
            throw new InvalidArgumentException("Missing required parameter: $paramName");
        }

        // Type validation
        if ($strict && $paramType) {
            validateParameterType($value, $paramType, $paramName);
        }

        Route::setParameter($paramName, $args[] = $value);
    }

    return $reflection instanceof ReflectionFunction
        ? $reflection->invokeArgs($args)
        : $reflection->invokeArgs(
            is_object($callable[0]) ? $callable[0] : null,
            $args
        );
}

function arr_key_exists(string $key, array &$array): bool
{
    return isset($array[$key]) || array_key_exists($key, $array);
}

function requireTrack(string $file): array
{
    static $declared = [];

    $before = $declared ?: get_declared_classes();
    require_once $file;
    $after = get_declared_classes();
    $declared = $after;

    return array_slice($after, count($before));
}

/**
 * Get application container or resolve a service.
 *
 * @template T
 * @param class-string<T>|null $class
 * @return App|T
 */
function app(?string $class = null)
{
    $app = InstanceManager::getInstance(App::class);

    if (!$app instanceof App) {
        throw new RuntimeException('Application container not initialized.');
    }

    return $class === null
        ? $app
        : $app->make($class);
}

function req(string $path): mixed
{
    $a = require $path;
    return $a;
}


function config(string $config)
{
    $path = explode('.', $config);
    $file = array_shift($path);

    $configFile = base_path("/config/{$file}.php");
    if (!file_exists($configFile)) {
        throw new Exception("Config file '{$file}.php' not found.");
    }

    $cfg = req($configFile);

    foreach ($path as $part) {
        if (is_array($cfg) && arr_key_exists($part, $cfg)) {
            $cfg = $cfg[$part];
        } else {
            throw new Exception("Config key '{$part}' not found in '{$file}.php'.");
        }
    }

    return $cfg;
}



/**
 * Strict type validation helper.
 */
function validateParameterType(mixed $value, ReflectionType $type, string $paramName): void
{
    $valueType = get_debug_type($value);

    if ($type instanceof ReflectionUnionType) {
        if (!in_array($valueType, array_map(fn($t) => $t->getName(), $type->getTypes()))) {
            throw new InvalidArgumentException(sprintf(
                'Parameter "%s" requires one of: %s, got %s',
                $paramName,
                implode('|', array_map(fn($t) => $t->getName(), $type->getTypes())),
                $valueType
            ));
        }
    } elseif ($type instanceof ReflectionNamedType && $valueType !== $type->getName()) {
        throw new InvalidArgumentException(sprintf(
            'Parameter "%s" requires %s, got %s',
            $paramName,
            $type->getName(),
            $valueType
        ));
    }
}

/**
 * Safely executes a callable, handling errors and allowing a fallback function.
 *
 * @param callable|string|array $closure The function to be executed.
 * @param mixed $parameter Parameters for the function, either an array or a single value.
 * @param mixed &$result Reference to store the function's return value.
 * @param bool $ignoreError Whether to ignore errors after logging.
 * @param callable|null $if_code_fails Optional fallback function if execution fails.
 * @param bool $autoResolve Whether to automatically resolve class dependencies.
 * @param bool $full_debug Whether to fully debug what call back does
 * @param bool $dump_cli Whether to dump error message in cli
 *
 * @return void
 */
function safe(
    callable|string|array $closure,
    mixed $parameter = [],
    mixed &$result = null,
    bool $ignoreError = false,
    bool $dump_cli = false,
    callable|null $if_code_fails = null,
    bool $strict = false,
    bool $autoResolve = false,
    bool $full_debug = false
) {
    !Debugger::getState() && Debugger::init(false, E_ALL, '', false);

    $debug = [];
    $start_time = hrtime(true); // Always record start time

    try {
        if ($full_debug) {
            $debug['start_time'] = $start_time;
            $debug['memory_usage_before'] = memory_get_usage();
        }

        $params = is_array($parameter) ? $parameter : [$parameter];
        $result = callFuncWithParams($closure, $params, $strict, $autoResolve);

        if ($full_debug) {
            $debug['end_time'] = hrtime(true);
            $debug['execution_time_ms'] = ($debug['end_time'] - $start_time) / 1.0e6;
            $debug['memory_usage_after'] = memory_get_usage();
            $debug['peak_memory'] = memory_get_peak_usage();
        }
    } catch (\Throwable $e) {
        $end_time = hrtime(true); // Capture failure time inside catch

        if ($full_debug) {
            $debug['end_time'] = $end_time;
            $debug['execution_time_ms'] = ($end_time - $start_time) / 1.0e6; // Now execution time is always recorded
            $debug['memory_usage_after'] = memory_get_usage();
            $debug['peak_memory'] = memory_get_peak_usage();
            $debug['exception'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
        }

        if (is_callable($if_code_fails)) {
            $ref = new ReflectionFunction($if_code_fails);
            $params = array_map(fn($p) => match ($p->getName()) {
                'debug' => $debug,
                'e', 'exception', 'error' => $e,
                default => null
            }, $ref->getParameters());

            $if_code_fails(...$params);
        }

        Debugger::dumpErr($e, $ignoreError, $dump_cli);
    }
}




// function convertArraySyntax(string $exported): string {
//     return preg_replace('/array\s*\((.*?)\)/s', '[$1]', $exported);
// }

// function scanFsorClasses($directory, $ignore_dirs = [], $ignore_files = [])
// {
//     $files = new RecursiveIteratorIterator(
//         new RecursiveCallbackFilterIterator(
//             new RecursiveDirectoryIterator($directory),
//             function ($file, $key, $iterator) use ($ignore_dirs) {
//                 if ($file->isDir()) {
//                     return !in_array($file->getBasename(), $ignore_dirs);
//                 }
//                 return true;
//             }
//         )
//     );
//     $classes = [];

//     foreach ($files as $file) {
//         if ($file->isFile() && $file->getExtension() === 'php') {
//             $relativePath = str_replace($directory . DIRECTORY_SEPARATOR, '', $file->getPathname());

//             // Skip files explicitly listed in $ignore_files
//             if (in_array($relativePath, $ignore_files)) {
//                 continue;
//             }
//             $content = file_get_contents($file->getPathname());

//             // Remove single-line and multi-line comments
//             $cleanedContent = preg_replace([
//                 '/\/\/.*/',                    // Remove // comments
//                 '/\/\*[\s\S]*?\*\//'           // Remove /* */ comments
//             ], '', $content);

//             // Remove all string literals (single & double quotes)
//             $cleanedContent = preg_replace('/(["\'])(?:\\\1|.)*?\1/s', '', $cleanedContent);

//             // Capture namespace (if exists)
//             preg_match('/namespace\s+([\w\\\\]+);/i', $cleanedContent, $namespace);

//             // Capture all class-like structures (class, interface, abstract class)
//             preg_match_all('/\b(?:class|interface|abstract\s+class)\s+([\w]+)\b/i', $cleanedContent, $matches);

//             if (!empty($matches[1])) {
//                 foreach ($matches[1] as $classname) {
//                     $fullClass = (!empty($namespace[1]) ? $namespace[1] . "\\" : "") . $classname;
//                     $classes[$fullClass] = $file->getPathname();
//                 }
//             }
//         }
//     }

//     return $classes;
// }

function scanForClasses($directory, $ignore_dirs = [], $ignore_files = [], $except_files = [])
{
    $directory = rtrim($directory, DIRECTORY_SEPARATOR); // Ensure no trailing slash

    $files = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            function ($file, $key, $iterator) use ($ignore_dirs, $directory, $except_files) {
                $relativePath = str_replace($directory . DIRECTORY_SEPARATOR, '', $file->getPathname());

                // echo "Now at {$relativePath}\n";

                // If this file is in the 'except_files' list, allow it no matter what
                if (in_array($relativePath, $except_files)) {
                    // echo "EXCEPTED: {$relativePath}\n";
                    return true;
                }

                // If it's a directory, check full relative path (not just basename)
                foreach ($ignore_dirs as $ignoreDir) {
                    if (str_starts_with($relativePath, trim($ignoreDir, '/') . '/')) {
                        // echo "IGNORING DIRECTORY: {$relativePath}\n";
                        return false; // Skip entire dir unless excepted
                    }
                }

                return true;
            }
        )
    );
    $classes = [];

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $relativePath = str_replace($directory . DIRECTORY_SEPARATOR, '', $file->getPathname());

            // Skip explicitly ignored files
            if (in_array($relativePath, $ignore_files)) {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            // Remove single-line and multi-line comments
            $cleanedContent = preg_replace([
                '/\/\/.*/',                    // Remove // comments
                '/\/\*[\s\S]*?\*\//'           // Remove /* */ comments
            ], '', $content);

            // Remove all string literals (single & double quotes)
            $cleanedContent = preg_replace(['/new class/', '/(["\'])(?:\\\1|.)*?\1/s'], ['', ''], $cleanedContent);
            // Capture namespace (if exists)
            preg_match('/namespace\s+([\w\\\\]+);/i', $cleanedContent, $namespace);

            // Capture all class-like structures (class, interface, abstract class)
            preg_match_all('/\b(?:class|interface|abstract\s+class|trait)\s+([\w]+)\b/i', $cleanedContent, $matches);

            if (!empty($matches[1])) {
                foreach ($matches[1] as $classname) {
                    $fullClass = (!empty($namespace[1]) ? $namespace[1] . "\\" : "") . $classname;
                    $classes[$fullClass] = $file->getPathname();
                }
            }
        }
    }

    return $classes;
}

function translateRegex(string $regex): string
{
    // Normalize the regex string first
    $regex = trim($regex);

    // Handle delimiters and modifiers more robustly
    if (preg_match('/^([#~\/])(.*?)\1([imsxADSUXJu]*)$/', $regex, $matches)) {
        $regex = $matches[2];
        if (!empty($matches[3])) {
            $regex .= ' [with modifiers: ' . str_split($matches[3]) . ']';
        }
    }

    $patterns = [
        // ---------------------------------------------------------------------
        // Most Complex Patterns (Highest Priority)
        // ---------------------------------------------------------------------
        // Recursion and Subroutines
        '/\\\\R/'            => ' [recursion to entire pattern] ',
        '/\\\\g\<([^>]+)\>/' => ' [subroutine call to named group "$1"] ',
        '/\\\\g\'([^\']+)\'/' => ' [subroutine call to named group "$1"] ',
        '/\\\\g\{([^}]+)\}/' => ' [subroutine call to named group "$1"] ',

        // Conditionals
        '/\(\?\(([^)]+)\)\)/' => ' [conditional: if $1] ',
        '/\(\?\(\'([^\']+)\'\)\)/' => ' [conditional on named group "$1"] ',
        '/\(\?\(\<([^>]+)\>\)\)/' => ' [conditional on named group "$1"] ',
        '/\(\?\((\d+)\)\)/' => ' [conditional on group $1] ',

        // Named Backreferences (complex before simple)
        '/\\\\k\{([^}]+)\}/' => ' [named backreference to group "$1"] ',
        '/\\\\k\'([^\']+)\'/' => ' [named backreference to group "$1"] ',
        '/\\\\k\<([^>]+)\>/' => ' [named backreference to group "$1"] ',
        '/\\\\g\{-(\d+)\}/' => ' [relative backreference ($1 groups prior)] ',
        '/\\\\g\{(\d+)\}/' => ' [backreference to group $1] ',
        '/\\\\(\d+)/'    => ' [backreference to group $1] ',

        // Unicode Properties
        '/\\\\p\{([^}]+)\}/' => ' [unicode character property "$1"] ',
        '/\\\\P\{([^}]+)\}/' => ' [negated unicode character property "$1"] ',

        // Mode Modifiers & Comments
        '/\(\?#([^)]+)\)/' => ' [comment: $1] ',
        '/\(\?([idmsuxUX]+)-:\)/' => ' [inline modifier group (disable $1)] ',
        '/\(\?([idmsuxUX]+):\)/' => ' [inline modifier group ($1)] ',
        '/\(\?-([idmsuxUX]+)\)/' => ' [mode modifier (disable $1)] ',
        '/\(\?([idmsuxUX]+)\)/' => ' [mode modifier: $1] ',

        // Lookarounds (complex groups)
        '/\(\?<=/'       => ' [positive lookbehind] ',
        '/\(\?<!/'       => ' [negative lookbehind] ',
        '/\(\?=/'        => ' [positive lookahead] ',
        '/\(\?!/'        => ' [negative lookahead] ',

        // Named Groups (complex before simple)
        '/\(\?\'\'([a-zA-Z0-9_]+)\'\'\)?/' => ' [named capture group "$1"] ',
        '/\(\?<([a-zA-Z0-9_]+)>\)?/'   => ' [named capture group "$1"] ',
        '/\(\?P<([a-zA-Z0-9_]+)>\)?/' => ' [named capture group "$1"] ',
        '/\(\?>/'        => ' [atomic group] ',
        '/\(\?:/'        => ' [non-capturing group] ',
        '/\((?!\?)/'     => ' [capturing group] ',

        // Quantifiers (possessive/lazy before greedy)
        '/\{(\d+),(\d+)\}/' => ' [between $1 and $2 times] ',
        '/\{(\d+),\}/'      => ' [at least $1 times] ',
        '/\{,(\d+)\}/'      => ' [at most $1 times] ',
        '/\{(\d+)\}/'       => ' [exactly $1 times] ',
        '/\{\}/'            => ' [empty quantifier] ',
        '/(?<!\\\\)\*\+/'   => ' [zero or more times (possessive)] ',
        '/(?<!\\\\)\+\+/'   => ' [one or more times (possessive)] ',
        '/(?<!\\\\)\?\+/'   => ' [zero or one time (possessive)] ',
        '/(?<!\\\\)\*?\?/'  => ' [zero or more times (lazy)] ',
        '/(?<!\\\\)\+\?/'   => ' [one or more times (lazy)] ',
        '/(?<!\\\\)\?\?/'   => ' [zero or one time (lazy)] ',
        '/(?<!\\\\)\*/'     => ' [zero or more times] ',
        '/(?<!\\\\)\+/'     => ' [one or more times] ',
        '/(?<!\\\\)\?/'     => ' [zero or one time] ',

        // Character Classes (negated before normal)
        '/\[\^([^\]]+)\]/' => ' [negated character class: $1] ',
        '/\[([^\]]+)\]/'   => ' [character class: $1] ',
        '/\[\^\]/'         => ' [negated empty character class] ',
        '/\[\]/'           => ' [empty character class] ',
        '/\[\^/'           => ' [start of negated character class] ',
        '/\[\[:\]\]/'      => ' [POSIX character class] ',

        // Predefined Character Classes
        '/\\\\N/'        => ' [any character except newline] ',
        '/\\\\R/'        => ' [any linebreak sequence] ',
        '/\\\\V/'        => ' [non-vertical whitespace] ',
        '/\\\\v/'        => ' [vertical whitespace] ',
        '/\\\\H/'        => ' [non-horizontal whitespace] ',
        '/\\\\h/'        => ' [horizontal whitespace] ',
        '/\\\\S/'        => ' [non-whitespace character] ',
        '/\\\\s/'        => ' [whitespace character] ',
        '/\\\\W/'        => ' [non-word character] ',
        '/\\\\w/'        => ' [word character] ',
        '/\\\\D/'        => ' [non-digit] ',
        '/\\\\d/'        => ' [digit] ',

        // Anchors and Boundaries
        '/\\\\K/'        => ' [reset match start] ',
        '/\\\\B/'        => ' [non-word boundary] ',
        '/\\\\b/'        => ' [word boundary] ',
        '/\\\\G/'        => ' [start of match attempt] ',
        '/\\\\z/'        => ' [absolute end of string] ',
        '/\\\\Z/'        => ' [end of string] ',
        '/\\\\A/'        => ' [start of string] ',
        '/(?<!\\\\)\$/'  => ' [end of line] ',
        '/(?<!\\\\)\^/'  => ' [start of line] ',

        // ---------------------------------------------------------------------
        // Simple Patterns (Lowest Priority)
        // ---------------------------------------------------------------------
        // Escaped Literals
        '/\\\\\\\\/'    => ' [literal backslash] ',
        '/\\\\\./'      => ' [literal dot] ',
        '/\\\\-/'       => ' [literal dash] ',
        '/\\\\\//'      => ' [literal slash] ',
        '/\\\\\*/'      => ' [literal asterisk] ',
        '/\\\\\+/'      => ' [literal plus] ',
        '/\\\\\?/'      => ' [literal question mark] ',
        '/\\\\\{/'      => ' [literal opening brace] ',
        '/\\\\\}/'      => ' [literal closing brace] ',
        '/\\\\\|/'      => ' [literal pipe] ',
        '/\\\\\(/'      => ' [literal opening parenthesis] ',
        '/\\\\\)/'      => ' [literal closing parenthesis] ',
        '/\\\\\[/'      => ' [literal opening bracket] ',
        '/\\\\\]/'      => ' [literal closing bracket] ',
        '/\\\\\^/'      => ' [literal caret] ',
        '/\\\\\$/'      => ' [literal dollar] ',

        // Basic Metacharacters
        '/(?<!\\\\)\./' => ' [any character except newline] ',
        '/(?<!\\\\)\|/' => ' [alternation (OR)] ',
    ];

    $explanation = $regex;

    // Apply all patterns at once for better performance
    $explanation = preg_replace(array_keys($patterns), array_values($patterns), $explanation);

    // Clean up the explanation
    $cleanup = [
        '/\s+/' => ' ',
        '/ , /' => ', ',
        '/ \[/' => '[',
        '/\] /' => ']'

    ];
    $explanation = preg_replace(array_keys($cleanup), array_values($cleanup), $explanation);  // Remove space after ]

    return trim($explanation, " ,\t\n\r\0\x0B");
}


function print_rpre(...$something)
{
    echo '<pre>';
    print_r($something);
    echo '</pre>';
}

/**
 * Print anything and exit
 *
 * @param mixed[]  ...$something
 * @return void
 */
function print_rpred(...$something)
{
    echo '<pre>';
    print_r($something);
    echo '</pre>';
    die(1);
}

function isValidClass($class)
{
    return class_exists($class) || interface_exists($class) || trait_exists($class);
}

function getBoolEnv(string $name, $default = false)
{
    return filter_var(env($name, $default), FILTER_VALIDATE_BOOLEAN);
}

function getArrEnv(string $name, $separator = ',')
{
    return array_filter(array_map('trim', explode($separator, env($name, ''))), fn($v) => $v !== '');
}

/**
 * Retrieves an environment variable with type validation
 *
 * @param string $name Name of the environment variable
 * @param 'string'|'int'|'float'|'bool'|'array' $type Expected type ('string', 'int', 'float', 'bool', 'array')
 * @param mixed $default Default value if variable doesn't exist
 * @return mixed The filtered environment variable or default value
 * @throws InvalidArgumentException If type validation fails
 */
function retrieveEnv(string $name, string $type = 'string', $default = null)
{
    $value = env($name, $default);

    // If it's the default value, return as-is
    if ($value === $default) {
        return $value;
    }

    switch (strtolower($type)) {
        case 'string':
            return (string) $value;
        case 'int':
            $filtered = filter_var($value, FILTER_VALIDATE_INT);
            if ($filtered === false) {
                throw new InvalidArgumentException("Environment variable {$name} is not a valid integer");
            }
            return $filtered;
        case 'float':
            $filtered = filter_var($value, FILTER_VALIDATE_FLOAT);
            if ($filtered === false) {
                throw new InvalidArgumentException("Environment variable {$name} is not a valid float");
            }
            return $filtered;
        case 'bool':
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        case 'array':
            if (!is_array($value)) {
                return explode(',', $value);
            }
            return $value;
        default:
            throw new InvalidArgumentException("Unsupported type {$type} for environment variable {$name}");
    }
}

function abort($errorCode, $message = '', $subMessage = '')
{
    switch ($errorCode) {
        case 404:
            throw new NotFoundException($message, $subMessage);
        case 403:
            throw new UnauthorizedException($message, $subMessage);
        default:
    }
}

function createInstance(object|string $class, ?callable $func = null, ?string $name = null, mixed ...$args)
{
    if (is_object($class)) {
        $classI = $class;
    } else {
        $classI = new $class(...$args);
    }

    if (is_callable($func)) {
        $func($classI);
    }
    InstanceManager::setInstance($name ?? (is_object($class) ? $class::class : $class), $classI);
    return $classI;
}


function base_path($path = '')
{
    return $path == '' ? dirname(__DIR__, 3) : dirname(__DIR__, 3) . '/' . ltrim($path, " \n\r\t\v\0/\\");
}

function uuidv4(): string
{
    $b = random_bytes(16);

    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

    $hex = bin2hex($b);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}
