<?php

namespace App\Foundation\Http;

use App\Debug\Debugger;
use Closure;

class TrieNode
{
    public array $children = [];
    public array $handlers = []; // Store handlers by method
    public array $paramKeys = [];
}

class Route
{
    private static TrieNode $root;
    // private static array $cache = [];
    // private static int $cacheSize = 100;
    private static array $globalMiddleware = [];
    private static array $routeMiddleware = [];
    private static null|array|Closure $fallback = null;
    private static array $routeList = [];
    private static array $plugins = [];

    public static function init(array $plugins = [])
    {
        self::$root = new TrieNode();
        self::$plugins = $plugins;
    }

    public static function getRequestMethod(): string
    {
        return $_POST['_HTTP_METHOD'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public static function middleware(string|array $middleware)
    {
        if (!is_array($middleware)) {
            $middleware = [$middleware];
        }
        self::$globalMiddleware = array_merge(self::$globalMiddleware, $middleware);
    }

    public static function add(string $method, string $url, callable|array $action, array $middleware)
    {
        $segments = [];
        $token = strtok(trim($url, '/'), '/');
        while ($token !== false) {
            $segments[] = $token;
            $token = strtok('/');
        }
        $node = self::$root;
        $paramKeys = [];

        foreach ($segments as $segment) {
            if (preg_match('/\{([a-zA-Z0-9_]+)\}/', $segment, $matches)) {
                $paramKeys[] = $matches[1];
                $segment = '{}';
            }

            if (!isset($node->children[$segment])) {
                $node->children[$segment] = new TrieNode();
            }
            $node = $node->children[$segment];
        }

        $node->handlers[$method] = $action;
        $node->paramKeys = $paramKeys;
        self::$routeMiddleware[$method][$url] = $middleware;
    }

    /**
     * Register url with GET method
     * 
     * @param string $url The URL pattern.
     * @param callable|array{class, non-empty-string} $action
     * @param array|string $middleware Middleware(s) to apply.
     */
    public static function get(string $url, callable|array $action, array|string $middleware = []): void
    {
        self::$routeList['GET'][] = $url;
        self::add('GET', $url, $action, (array) $middleware);
    }
    public static function post(string $url, callable|array $action, array|string $middleware = []): void
    {
        self::$routeList['POST'][] = $url;
        self::add('POST', $url, $action, (array) $middleware);
    }
    public static function put(string $url, callable|array $action, array|string $middleware = []): void
    {
        self::$routeList['PUT'][] = $url;
        self::add('PUT', $url, $action, (array) $middleware);
    }
    public static function delete(string $url, callable|array $action, array|string $middleware = []): void
    {
        self::$routeList['DELETE'][] = $url;
        self::add('DELETE', $url, $action, (array) $middleware);
    }

    public static function routeList()
    {
        return self::$routeList;
    }

    public static function fallback(callable|array $callback)
    {
        self::$fallback = $callback;
    }


    public static function dispatch(string $requestUri)
    {
        $method = self::getRequestMethod();

        // var_dump(self::$cache);

        // if (isset(self::$cache[$requestUri][$method])) {
        //     return self::execute(self::$cache[$requestUri][$method]['action'], self::$cache[$requestUri][$method]['params']);
        // }

        $segments = [];
        $token = strtok(trim($requestUri, '/'), '/');
        while ($token !== false) {
            $segments[] = $token;
            $token = strtok('/');
        }
        $node = self::$root;
        $params = [];

        foreach ($segments as $segment) {
            if (isset($node->children[$segment])) {
                $node = $node->children[$segment];
            } elseif (isset($node->children['{}'])) {
                $node = $node->children['{}'];
                $params[] = $segment;
            } else {
                // return self::showError(404, 'Not Found');
                if (isset(self::$fallback)) {
                    return self::execute(self::$fallback, $params);
                }
                return Debugger::showErrorPage(404, 'Not found');
            }
        }

        if (isset($node->handlers[$method])) {

            foreach (self::$plugins as $fn) {
                $fn();
            }

            $middleware = array_merge(self::$globalMiddleware, self::$routeMiddleware[$method][$requestUri] ?? []);

            foreach ($middleware as $m) {
                if (is_callable($m)) {
                    if (!call_user_func($m)) {
                        return;
                    }
                }
            }

            // self::cacheRoute($requestUri, $method, $node->handlers[$method], $params);
            return self::execute($node->handlers[$method], array_combine($node->paramKeys, $params));
        }

        // return self::showError(405, 'Method Not Allowed');
        return Debugger::showErrorPage(405, 'Method not allowed');
    }

    private static function execute(callable|array $action, mixed $params)
    {
        if (is_array($action) && count($action) === 2) {
            $instance = new $action[0];
            $action = [$instance, $action[1]];
        }

        if (is_callable($action)) {
            $result = callFuncWithParams($action, true, getBoolEnv('AUTO_LOAD_CLASS_DEPENDENCIES', true), $params);
            if (is_string($result)) {
                echo $result;
                exit;
            }
            return $result;
        }


        // return self::showError(500, 'Invalid Callback');
        http_response_code(500);
        return Debugger::showErrorPage(500, 'Invalid callback');
    }
}

// private static function cacheRoute(string $uri, string $method, $action, array $params)
// {
//     if (count(self::$cache) >= self::$cacheSize) {
//         array_shift(self::$cache);
//     }
//     error_log('Cache size: ' . count(self::$cache));
//     self::$cache[$uri][$method] = ['action' => $action, 'params' => $params];
// }

// if (getBoolEnv('AUTO_LOAD_USER_PATH_DEFINED_CLASS', $auto_resolve = ClassManager::getAttr()['auto_resolve'])) {
//     require_once FOUNDATION . 'Http/Request.php';
//     require_once FOUNDATION . 'Manager/ClassManager.php';
//     require_once FOUNDATION . 'Manager/SessionManager.php';
//     Route::post('/AUTO-LOAD/REGISTER', function () {
//         SessionManager::sessionInit();
//         $class_path = Request::post('class-path') . '.php';
//         $class_name = Request::post('class-name');
//         $class_name = explode(': ', $class_name, 2)[1];
//         $debug = ClassManager::getAttr()['debug'];

//         if (file_exists(ROOT . $class_path)) {
//             // try {
//             if ($debug) error_log("Auto-loader: file found");
//             require_once ROOT . $class_path;
//             error_log(var_export(class_exists($class_name), true));

//             if (!class_exists($class_name, false)) {
//                 if (getBoolEnv('AUTO_LOAD_CLASS_ALWAYS_EXISTS', $auto_resolve)) {
//                     SessionManager::set('Level-6', 'failed');
//                 }
//                 throw new \Exception("$class::class has not yet registered.", 6);
//             }

//             ClassManager::registerNewClass($class_name, $class_path);
//             return redirectBack();
//             // } catch (\Throwable $e) {
//             //     if ($debug) error_log('Auto-loader: Method 6 failed (invalid file)');
//             //     if (getBoolEnv('AUTO_LOAD_CLASS_ALWAYS_EXISTS', $auto_resolve)) {
//             //         SessionManager::set('Level-6', 'failed');
//             //         return redirectBack();
//             //     }
//             // }
//         } else {
//             if ($debug) error_log('Auto-loader: Method 6 failed (file not found)');
//             echo "I doubt your life choice";
//             throw new \Exception("$class::class has not yet registered.", 6);
//             return redirectBack();
//         }
//     });
// }
