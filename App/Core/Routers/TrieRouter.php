<?php

namespace App\Foundation\Http;

use App\Debug\Debugger;
use Closure;
use RouterBase;
use RouterInterface;

class TrieNode
{
    public array $children = [];
    public array $handlers = [];
    public array $paramKeys = [];
    public array $middleware = [];
}

/**
 * Trie Router
 */
class Route extends RouterBase implements RouterInterface
{
    public static string $name = 'TrieRouter';
    private static TrieNode $root;
    private static string $dirRoot;
    private static array $globalMiddleware = [];
    private static array $routeMiddleware = [];
    private static null|array|Closure $fallback = null;
    private static array $routeList = [];
    private static array $plugins = [];
    private static array $namedRoutes = [];
    private static ?array $lastRoute = null;

    // Group stack for nested groups
    private static array $groupStack = [];

    // Current group attributes
    private static array $currentGroup = [
        'prefix' => '',
        'middleware' => [],
        'namespace' => '',
    ];

    public static function init(?string $root = null, array $plugins = [])
    {
        self::$root = new TrieNode();
        self::$dirRoot = $root ?? $_SERVER['DOCUMENT_ROOT'] ?? null;
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
        return new Self();
    }

    public static function add(string $method, string $url, callable|array $action, array $middleware = [])
    {
        // echo 'called for ' . $url . '<br>';
        self::$lastRoute = null;
        // Apply group prefix
        $url = trim(self::$currentGroup['prefix'] . '/' . trim($url, '/'), '/');
        self::$routeList[$method][] = '/' . $url;

        // Apply group middleware
        $middleware = array_merge(self::$currentGroup['middleware'], $middleware);
        // dd($middleware);

        // Apply namespace to controller actions
        if (is_array($action) && !empty(self::$currentGroup['namespace'])) {
            $action[0] = self::$currentGroup['namespace'] . '\\' . ltrim($action[0], '\\');
        }

        $segments = [];
        $token = strtok($url, '/');
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
        $node->middleware = $middleware;


        // self::$routeMiddleware[$method][$url] = $middleware;
        self::$lastRoute = [
            'method' => $method,
            'url' => $url,
            'action' => $action,
            'middleware' => $middleware
        ];

        // echo 'At add:
        // <pre>';
        // var_dump($url, self::$currentGroup['middleware'], self::$routeMiddleware[$method][$url]);
        // echo '</pre>';

        return new Self();
    }

    // HTTP verb methods (now return $this for chaining)
    public static function get(string $url, callable|array $action, array|string $middleware = []): self
    {
        self::add('GET', $url, $action, (array) $middleware);
        return new self();
    }

    public static function post(string $url, callable|array $action, array|string $middleware = []): self
    {
        self::add('POST', $url, $action, (array) $middleware);
        return new self();
    }

    public static function put(string $url, callable|array $action, array|string $middleware = []): self
    {
        self::add('PUT', $url, $action, (array) $middleware);
        return new self();
    }

    public static function delete(string $url, callable|array $action, array|string $middleware = []): self
    {
        self::add('DELETE', $url, $action, (array) $middleware);
        return new self();
    }

    // Named routes
    public function name(string $name): self
    {
        if (empty($name)) return $this;
        self::$namedRoutes[$name] = self::$lastRoute;
        return $this;
    }

    // Route groups
    public static function group(array $attributes, callable $callback): void
    {
        // Push current group attributes to stack
        self::$groupStack[] = self::$currentGroup;

        // Merge new attributes
        self::$currentGroup = [
            'prefix' => trim(self::$currentGroup['prefix'] . '/' . trim($attributes['prefix'] ?? '', '/'), '/'),
            'middleware' => array_merge(self::$currentGroup['middleware'], $attributes['middleware'] ?? []),
            'namespace' => self::$currentGroup['namespace'] . '\\' . trim($attributes['namespace'] ?? '', '\\'),
        ];


        // Execute the callback
        call_user_func($callback);

        // echo 'At group:
        // <pre>';
        // var_dump(self::$currentGroup['middleware']);
        // echo '</pre>';
        // die;

        // Restore previous group attributes
        self::$currentGroup = array_pop(self::$groupStack);
    }

    // Reverse routing
    public static function route(string $name, array $parameters = []): string
    {
        if (!isset(self::$namedRoutes[$name])) {
            throw new \InvalidArgumentException("Route name [$name] not found.");
        }

        $route = self::$namedRoutes[$name]['url'];

        foreach ($parameters as $key => $value) {
            $route = str_replace('{' . $key . '}', $value, $route);
        }

        return '/' . $route;
    }

    // Resourceful routing (RESTful)
    public static function resource(string $name, string $controller, array $options = []): void
    {
        $name = trim($name, '/');
        $only = $options['only'] ?? ['index', 'show', 'create', 'store', 'edit', 'update', 'destroy'];
        $except = $options['except'] ?? [];
        $names = $options['names'] ?? [];
        $middleware = $options['middleware'] ?? [];

        $routes = [
            'index' => ['GET', "/$name", [$controller, 'index']],
            'create' => ['GET', "/$name/create", [$controller, 'create']],
            'store' => ['POST', "/$name", [$controller, 'store']],
            'show' => ['GET', '/' . $name . '/{id}', [$controller, 'show']],
            'edit' => ['GET', '/' . $name . '/{id}/edit', [$controller, 'edit']],
            'update' => ['PUT', '/' . $name . '/{id}', [$controller, 'update']],
            'destroy' => ['DELETE', '/' . $name . '/{id}', [$controller, 'destroy']],
        ];

        $index = -1;
        foreach ($routes as $action => $route) {
            $index++;
            if (in_array($action, $only) && !in_array($action, $except)) {
                // echo $route[0] . '<br>';
                // self::$routeList[$route[0]][] = $route[1];
                self::add($route[0], $route[1], $route[2])->middleware($middleware)->name($names[$index] ?? '');
            }
        }
    }

    // View routes
    public static function view(string $uri, string $view, array $data = []): self
    {
        return self::get($uri, fn() => view($view, $data));
    }

    // Redirect routes
    public static function redirect(string $from, string $to, int $status = 302): self
    {
        return self::get($from, fn() => header("Location: $to", true, $status));
    }

    // Other methods remain the same...
    public static function routeList()
    {
        return self::$routeList;
    }

    public static function namedRoutes()
    {
        return self::$namedRoutes;
    }

    public static function fallback(callable|array $callback)
    {
        self::$fallback = $callback;
    }

    private static function execute(callable|array $action, mixed $params)
    {
        // echo  '<pre>';
        // var_dump($action);
        // echo  '</pre>';
        // call_user_func_array($action, $params);
        // die;
        // dd($action);
        if (is_array($action) && count($action) === 2) {
            $instance = new $action[0];
            $action = [$instance, $action[1]];
        }

        if (is_callable($action)) {
            // dd($action,$params);
            $result = callFuncWithParams($action, $params, true,  true);
            // var_dump(is_string($result));
            // var_dump($result);
            if (is_string($result)) {
                echo $result;
                exit;
            }
            return $result;
        }
        // return self::showError(500, 'Invalid Callback');
        // http_response_code(500);
        return Debugger::showErrorPage(500, 'Invalid callback');
    }

    public static function dispatch(Request $request)
    {
        $requestUri = trim($request->uri(), '/');
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

            // $middleware = array_merge(self::$globalMiddleware, self::$routeMiddleware[$method][strtok($requestUri, '/') . implode('/', $params)] ?? []);
            // Get middleware from the matched node
            $middleware = array_merge(self::$globalMiddleware, $node->middleware ?? []);
            // echo 'At dispatch:
            // <pre>';
            // var_dump($requestUri, self::$currentGroup['middleware'], self::$routeMiddleware[$method][trim($requestUri,'/')] ?? []);
            // echo '</pre>';
            // die;


            // die;

            // die;

            //     if (is_callable($m)) {
            //         $response = call_user_func($m);
            //         if ($response !== true) {
            //             return $response; // Allow middleware to return responses
            //         }
            //     } elseif (is_string($m)) {
            //         // Handle class-based middleware
            //         $middlewareInstance = new $m();
            //         $response = $middlewareInstance->handle();
            //         if ($response !== true) {
            //             return $response;
            //         }
            //     }
            // }

            $destination = fn() => self::execute($node->handlers[$method], array_combine($node->paramKeys, $params));

            return self::pipeline($request, $middleware, $destination);
        }

        // return self::showError(405, 'Method Not Allowed');
        if (isset(self::$fallback)) {
            return self::execute(self::$fallback, []);
        }
        return Debugger::showErrorPage(405, 'Method not allowed');
    }
}

// namespace App\Foundation\Http;

// use App\Debug\Debugger;
// use Closure;

// class TrieNode
// {
//     public array $children = [];
//     public array $handlers = []; // Store handlers by method
//     public array $paramKeys = [];
// }

// class Route
// {
//     private static TrieNode $root;
//     // private static array $cache = [];
//     // private static int $cacheSize = 100;
//     private static array $globalMiddleware = [];
//     private static array $routeMiddleware = [];
//     private static null|array|Closure $fallback = null;
//     private static array $routeList = [];
//     private static array $plugins = [];

//     public static function init(array $plugins = [])
//     {
//         self::$root = new TrieNode();
//         self::$plugins = $plugins;
//     }

//     public static function getRequestMethod(): string
//     {
//         return $_POST['_HTTP_METHOD'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
//     }

//     public static function middleware(string|array $middleware)
//     {
//         if (!is_array($middleware)) {
//             $middleware = [$middleware];
//         }
//         self::$globalMiddleware = array_merge(self::$globalMiddleware, $middleware);
//     }

//     public static function add(string $method, string $url, callable|array $action, array $middleware)
//     {
//         $segments = [];
//         $token = strtok(trim($url, '/'), '/');
//         while ($token !== false) {
//             $segments[] = $token;
//             $token = strtok('/');
//         }
//         $node = self::$root;
//         $paramKeys = [];

//         foreach ($segments as $segment) {
//             if (preg_match('/\{([a-zA-Z0-9_]+)\}/', $segment, $matches)) {
//                 $paramKeys[] = $matches[1];
//                 $segment = '{}';
//             }

//             if (!isset($node->children[$segment])) {
//                 $node->children[$segment] = new TrieNode();
//             }
//             $node = $node->children[$segment];
//         }

//         $node->handlers[$method] = $action;
//         $node->paramKeys = $paramKeys;
//         self::$routeMiddleware[$method][$url] = $middleware;
//     }

//     /**
//      * Register url with GET method
//      * 
//      * @param string $url The URL pattern.
//      * @param callable|array{class, method} $action
//      * @param array|string $middleware Middleware(s) to apply.
//      */
//     public static function get(string $url, callable|array $action, array|string $middleware = []): void
//     {
//         self::$routeList['GET'][] = $url;
//         self::add('GET', $url, $action, (array) $middleware);
//     }
//     /**
//      * Register url with POST method
//      * 
//      * @param string $url The URL pattern.
//      * @param callable|array{class, method} $action
//      * @param array|string $middleware Middleware(s) to apply.
//      */
//     public static function post(string $url, callable|array $action, array|string $middleware = []): void
//     {
//         self::$routeList['POST'][] = $url;
//         self::add('POST', $url, $action, (array) $middleware);
//     }
//     /**
//      * Register url with PUT method
//      * 
//      * @param string $url The URL pattern.
//      * @param callable|array{class, method} $action
//      * @param array|string $middleware Middleware(s) to apply.
//      */
//     public static function put(string $url, callable|array $action, array|string $middleware = []): void
//     {
//         self::$routeList['PUT'][] = $url;
//         self::add('PUT', $url, $action, (array) $middleware);
//     }
//     /**
//      * Register url with DELETE method
//      * 
//      * @param string $url The URL pattern.
//      * @param callable|array{class, method} $action
//      * @param array|string $middleware Middleware(s) to apply.
//      */
//     public static function delete(string $url, callable|array $action, array|string $middleware = []): void
//     {
//         self::$routeList['DELETE'][] = $url;
//         self::add('DELETE', $url, $action, (array) $middleware);
//     }

//     public static function routeList()
//     {
//         return self::$routeList;
//     }

//     public static function fallback(callable|array $callback)
//     {
//         self::$fallback = $callback;
//     }


//     public static function dispatch(string $requestUri)
//     {
//         $method = self::getRequestMethod();

//         // var_dump(self::$cache);

//         // if (isset(self::$cache[$requestUri][$method])) {
//         //     return self::execute(self::$cache[$requestUri][$method]['action'], self::$cache[$requestUri][$method]['params']);
//         // }

//         $segments = [];
//         $token = strtok(trim($requestUri, '/'), '/');
//         while ($token !== false) {
//             $segments[] = $token;
//             $token = strtok('/');
//         }
//         $node = self::$root;
//         $params = [];

//         foreach ($segments as $segment) {
//             if (isset($node->children[$segment])) {
//                 $node = $node->children[$segment];
//             } elseif (isset($node->children['{}'])) {
//                 $node = $node->children['{}'];
//                 $params[] = $segment;
//             } else {
//                 // return self::showError(404, 'Not Found');
//                 if (isset(self::$fallback)) {
//                     return self::execute(self::$fallback, $params);
//                 }
//                 return Debugger::showErrorPage(404, 'Not found');
//             }
//         }

//         if (isset($node->handlers[$method])) {

//             foreach (self::$plugins as $fn) {
//                 $fn();
//             }

//             $middleware = array_merge(self::$globalMiddleware, self::$routeMiddleware[$method][$requestUri] ?? []);

//             foreach ($middleware as $m) {
//                 if (is_callable($m)) {
//                     if (!call_user_func($m)) {
//                         return;
//                     }
//                 }
//             }

//             // self::cacheRoute($requestUri, $method, $node->handlers[$method], $params);
//             return self::execute($node->handlers[$method], array_combine($node->paramKeys, $params));
//         }

//         // return self::showError(405, 'Method Not Allowed');
//         return Debugger::showErrorPage(405, 'Method not allowed');
//     }

//     private static function execute(callable|array $action, mixed $params)
//     {
//         if (is_array($action) && count($action) === 2) {
//             $instance = new $action[0];
//             $action = [$instance, $action[1]];
//         }

//         if (is_callable($action)) {
//             $result = callFuncWithParams($action, true,  true, $params);
//             // var_dump(is_string($result));
//             // var_dump($result);
//             if (is_string($result)) {
//                 echo $result;
//                 exit;
//             }
//             return $result;
//         }


//         // return self::showError(500, 'Invalid Callback');
//         http_response_code(500);
//         return Debugger::showErrorPage(500, 'Invalid callback');
//     }
// }

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
