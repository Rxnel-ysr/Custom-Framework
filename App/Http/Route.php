<?php

use App\utils\Guard\CSRF;
use App\Utils\Http\Request;
use App\Utils\Manager\ClassManager;

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

    public static function init()
    {
        self::$root = new TrieNode();
    }

    public static function getRequestMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
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

    public static function get(string $url, callable|array $action, array $middleware = [])
    {
        self::add('GET', $url, $action, $middleware);
    }
    public static function post(string $url, callable|array $action, array $middleware = [])
    {
        self::add('POST', $url, $action, $middleware);
    }
    public static function put(string $url, callable|array $action, array $middleware = [])
    {
        self::add('PUT', $url, $action, $middleware);
    }
    public static function delete(string $url, callable|array $action, array $middleware = [])
    {
        self::add('DELETE', $url, $action, $middleware);
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
                return showErrorPage(HTTP_NOT_FOUND, 'Not found');
            }
        }

        if (isset($node->handlers[$method])) {
            if (isset($_REQUEST['csrf_key'])) {
                CSRF::validateCSRF();
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
        return showErrorPage(HTTP_METHOD_NOT_ALLOWED, 'Method not allowed');
    }

    private static function execute($action, $params)
    {
        if (is_array($action) && count($action) === 2) {
            $instance = new $action[0];
            $action = [$instance, $action[1]];
        }

        if (is_callable($action)) {
            $result = callFuncWithParams($action, true, true, ...$params);
            if (is_string($result)) {
                echo $result;
                exit;
            }
            return $result;
        }


        // return self::showError(500, 'Invalid Callback');
        return showErrorPage(HTTP_SERVER_ERROR, 'Invalid callback');
    }

    // private static function cacheRoute(string $uri, string $method, $action, array $params)
    // {
    //     if (count(self::$cache) >= self::$cacheSize) {
    //         array_shift(self::$cache);
    //     }
    //     error_log('Cache size: ' . count(self::$cache));
    //     self::$cache[$uri][$method] = ['action' => $action, 'params' => $params];
    // }
}

Route::init();

if (getBoolEnv('WELCOME_MESSAGE', true)) {
    Route::get('/', function () {
        return view('index');
    });
}

if (getBoolEnv('AUTO_LOAD_USER_PATH_DEFINED_CLASS', $auto_resolve = ClassManager::getAttr()['auto_resolve'])) {
    Route::post($_SERVER['REQUEST_URI'], function () {
        $class_path = Request::post('class-path') . '.php';
        $class_name = Request::post('class-name');
        $class_name = explode(': ', $class_name, 2)[1];
        $debug = ClassManager::getAttr()['debug'];

        if (file_exists(ROOT . $class_path)) {
            require_once ROOT . $class_path;
            if (!class_exists($class_name)) {
                if ($debug) error_log('Auto-loader: Method 6 failed (invalid file)');
                if (getBoolEnv('AUTO_LOAD_CLASS_ALWAYS_EXISTS', $auto_resolve)) {
                    ClassManager::method_7($class_name);
                }
            } else {
                ClassManager::registerNewClass($class_name, $class_path);
                ClassManager::loadClass($class_name);
                ClassManager::messageForResolvedClass($class_name, 6);
            }
        } else {
            if ($debug) error_log('Auto-loader: Method 6 failed (file not found)');
            if (getBoolEnv('AUTO_LOAD_CLASS_ALWAYS_EXISTS', $auto_resolve)) {
                ClassManager::method_7($class_name);
            }
        }
        return redirectBack();
    });
}
