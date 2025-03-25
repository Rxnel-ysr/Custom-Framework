<?php

use App\Utils\Manager\InstanceManager;

/**
 * Will include given views when the URL is matched or execute a callback.
 * @deprecated
 */
function route($uri, $viewOrCallback): void
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
                showErrorPage(404);
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
            showErrorPage(404);
        }
    }
}

function includeView($view, array $data = []): void
{
    global $views;
    $viewPath = $views . '/' . $view . '.php';

    if (file_exists($viewPath)) {
        extract($data);
        require_once $viewPath;
    } else {
        echo 'View not found: ' . $viewPath;
        exit();
    }
}

function view($view, array $data = []): void
{
    global $views;
    $viewPath = $views . '/' . $view . '.php';

    if (file_exists($viewPath)) {
        extract($data);
        require_once $viewPath;
    } else {
        echo 'View not found: ' . $viewPath;
        exit();
    }
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
        showErrorPage(403);
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

function redirectBack(): void
{
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Location: ' . $_SERVER['HTTP_REFERER'], true, 303);
    exit;
}

function getReferer()
{
    return $_SERVER['HTTP_REFERER'];
}

function getRequestMethod()
{
    return $_SERVER['REQUEST_METHOD'];
}

function redirect($uri): void
{
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Location: ' . $uri, true, 303);
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

function compacts(...$keys)
{
    $vars = get_defined_vars();
    $result = [];

    foreach ($keys as $key) {
        if (isset($GLOBALS[$key])) {
            $result[$key] = $GLOBALS[$key];
        }
    }

    return $result;
}

/**
 * Calls a function with the given parameters, supporting automatic dependency resolution.
 *
 * @param callable|string|array $func The function, method, or callable array to invoke.
 * @param bool $auto_resolve Whether to automatically resolve class dependencies.
 * @param mixed ...$params Parameters to pass to the function.
 *
 * @throws InvalidArgumentException If the callable is invalid or required parameters are missing.
 *
 * @return mixed The result of the function execution.
 */
function callFuncWithParams(callable|string|array $func, bool $strict = false, bool $auto_resolve = false, mixed ...$params,)
{
    $args = [];

    if (is_callable($func)) {
        if (is_array($func)) {
            $ref = new ReflectionMethod($func[0], $func[1]);
        } elseif (is_string($func) && function_exists($func)) {
            $ref = new ReflectionFunction($func);
        } else {
            $ref = new ReflectionFunction($func);
        }
    } else {
        throw new InvalidArgumentException('Invalid callable provided.');
    }

    $isAssoc = !empty($params) && !is_numeric(array_keys($params)[0]);
    // error_log('Is assoc: '.var_export($isAssoc,true));

    foreach ($ref->getParameters() as $i => $param) {
        // error_log("I = $i");
        if ($auto_resolve) $i--;
        // error_log("I = $i");
        // error_log('Param: '.$param);
        // error_log("Length of param" . count($params));
        // error_log(isset($params[$i]) ? "isset for $i" : "Not set for $i");
        $name = $param->getName();
        $type = $param->getType();

        if ($auto_resolve && $type && !$type->isBuiltin()) {
            $className = $type->getName();
            // error_log('Called auto resolve');
            $args[] = InstanceManager::getInstance($className);
        } elseif ($isAssoc && array_key_exists($name, $params)) {
            $args[] = $params[$name];
        } elseif (!$isAssoc && isset($params[$i])) {
            // error_log('This enter here: '.$params[$i]);
            if ($strict && $type && $type->isBuiltin()) {
                if (get_debug_type($params[$i]) === $type->getName()) {
                    $args[] = $params[$i];
                } else {
                    throw new InvalidArgumentException("Type mismatch for parameter: $name");
                }
            } else {
                // error_log('CIhut: '.$params[$i]);
                $args[] = $params[$i];
            }
        } elseif ($param->isOptional()) {
            $args[] = $param->getDefaultValue();
        } else {
            throw new InvalidArgumentException("Missing required parameter: $name");
        }
    }

    return $ref instanceof ReflectionFunction
        ? $ref->invokeArgs($args)
        : $ref->invokeArgs(is_array($func) ? $func[0] : null, $args);
}

/**
 * Safely executes a callable, handling errors and allowing a fallback function.
 *
 * @param callable|string|array $closure The function to be executed.
 * @param mixed $parameter Parameters for the function, either an array or a single value.
 * @param mixed &$result Reference to store the function's return value.
 * @param bool $ignoreError Whether to ignore errors after logging.
 * @param callable|null $ifCodeFails Optional fallback function if execution fails.
 * @param bool $autoResolve Whether to automatically resolve class dependencies.
 *
 * @return void
 */
function safe(callable|string|array $closure, mixed $parameter = [], mixed &$result = null, bool $ignoreError = false, callable|null $ifCodeFails = null, bool $strict = false, bool $autoResolve = false)
{
    try {
        $params = is_array($parameter) ? $parameter : [$parameter];
        $result = callFuncWithParams($closure, $strict,  $autoResolve, ...$params);
    } catch (\Throwable $e) {
        if (is_callable($ifCodeFails)) {
            $ifCodeFails();
        }
        \App\Debug\Debugger::dumpErr($e, $ignoreError);
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
            $cleanedContent = preg_replace('/(["\'])(?:\\\1|.)*?\1/s', '', $cleanedContent);

            // Capture namespace (if exists)
            preg_match('/namespace\s+([\w\\\\]+);/i', $cleanedContent, $namespace);

            // Capture all class-like structures (class, interface, abstract class)
            preg_match_all('/\b(?:class|interface|abstract\s+class)\s+([\w]+)\b/i', $cleanedContent, $matches);

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

function getBoolEnv($name, $default = null)
{
    return filter_var(env($name, $default), FILTER_VALIDATE_BOOLEAN);
}
