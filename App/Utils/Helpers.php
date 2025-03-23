<?php

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

function callFuncWithParams(callable|string|array $func, array $params)
{
    $args = [];

    if (!is_array($func) && is_callable($func)) {
        $ref = new ReflectionFunction($func);
    } elseif (is_array($func) && is_callable($func)) {
        if (is_string($func[0])) {
            $ref = new ReflectionMethod($func[0], $func[1]);
        } else {
            $ref = new ReflectionMethod($func[0], $func[1]);
        }
    } else if (is_string($func) && function_exists($func)) {
        $ref = new ReflectionFunction($func);
    } else {
        throw new InvalidArgumentException('Invalid callable provided.');
    }

    if (!empty($params) && !is_numeric(array_keys($params)[0])) {
        foreach ($ref->getParameters() as $param) {
            $name = $param->getName();
            if (!array_key_exists($name, $params)) {
                if ($param->isOptional()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    throw new InvalidArgumentException("Missing required parameter: $name");
                }
            } else {
                $args[] = $params[$name];
            }
        }
    } else {
        foreach ($ref->getParameters() as $i => $param) {
            if (isset($params[$i])) {
                $args[] = $params[$i];
            } elseif ($param->isOptional()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new InvalidArgumentException('Missing required parameter: ' . $param->getName());
            }
        }
    }

    if ($ref instanceof ReflectionFunction) {
        return $ref->invokeArgs($args);
    } elseif ($ref->isStatic()) {
        return $ref->invokeArgs(null, $args);
    } else {
        return $ref->invokeArgs($func[0], $args);
    }
}

/**
 * Executes a callable safely, handling errors and optionally executing a fallback function.
 *
 * @param callable|string|array $closure The function to be executed.
 * @param mixed $parameter Parameters to be passed to the function, can be an array or a single value.
 * @param mixed &$result Reference variable to store the result of the function execution.
 * @param bool $ignoreError If true, errors will be ignored after logging.
 * @param callable|null $ifCodeFails Optional callable executed when an error occurs.
 *
 * @return void
 */
function safe(callable|string|array $closure, mixed $parameter = [], mixed &$result = null, bool $ignoreError = false, callable|null $ifCodeFails = null)
{
    try {
        $params = is_array($parameter) ? $parameter : [$parameter];
        $result = callFuncWithParams($closure, $params);
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
