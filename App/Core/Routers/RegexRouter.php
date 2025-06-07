<?php

namespace App\Foundation\Http;

use App\Debug\Debugger;
use Closure;
use RouterInterface;
use Throwable;

class Route implements RouterInterface
{
    private static array $routes = [];
    private static array $globalMiddleware = [];
    private static array $routeMiddleware = [];
    private static null|array|Closure $fallback = null;
    private static array $routeList = [];
    private static array $plugins = [];
    private static array $namedRoutes = [];
    private static ?array $lastRoute = null;

    // Group stack for nested groups
    private static array $groupStack = [];
    private static array $appliedGroup = [];

    // Current group attributes
    private static array $currentGroup = [
        'prefix' => '',
        'middleware' => [],
        'namespace' => '',
    ];

    public static function init(array $plugins = [])
    {
        self::$plugins = $plugins;
    }

    public static function getRequestMethod(): string
    {
        return $_POST['_HTTP_METHOD'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    private static function normalizePath(string $path): string
    {
        return '/' . trim($path, '/');
    }

    public static function middleware(string|array $middleware)
    {
        if (!is_array($middleware)) {
            $middleware = [$middleware];
        }
        self::$globalMiddleware = array_merge(self::$globalMiddleware, $middleware);
        return new Self();
    }

    private static function parseRoutePattern(string $pattern): array
    {
        $segments = explode('/', trim($pattern, '/'));
        $paramKeys = [];
        $regexPattern = '';

        // if ($pattern === '/') {
        //     return [
        //         'pattern' => '/^\/$/',  // Proper regex for root path
        //         'paramKeys' => []
        //     ];
        // }

        foreach ($segments as $segment) {
            if (empty($segment)) {
                continue; // Skip empty segments
            }

            // $parts = explode(':', $segment, 2);

            // if (count($parts) > 1) {
            //     $paramKeys[] = $parts[0];
            // }

            // '/^{([a-zA-Z0-9_]+)(?::(.+))?}/'

            if (preg_match('/^{([a-zA-Z0-9_]+)(?::([^:}]+))?}$/', $segment, $matches)) {
                $paramName = $matches[1];
                $paramPattern = $matches[2] ?? '[^/]+';

                // Properly handle regex patterns
                // if (
                //     !preg_match('/^\(.*\)/', $paramPattern) &&
                //     !preg_match('/^\[.*\]/', $paramPattern)
                // ) {
                //     $paramPattern = preg_quote($paramPattern, '/');
                // }

                $regexPattern .= '\/(?P<' . $paramName . '>' . $paramPattern . ')';
                // echo 'Param name: '.$paramName . '<br>';
                // // echo 'Param pattern: ' .$paramPattern . '<br>';
                // echo 'Regex pattern: ' . htmlspecialchars($regexPattern) . '<br>';

                // echo 'Regex pattern: ' .$regexPattern . '<br>';
                $paramKeys[] = $paramName;
            } else {
                $regexPattern .= '#^' . preg_quote($segment, '/');
            }

            // echo $regexPattern . '<br>';
            // echo  'END<br>';

        }

        if (empty($regexPattern)) {
            $regexPattern = '#^\/$#';
        } else {
            $regexPattern =  $regexPattern . '$#';
        }


        return [
            'pattern' => $regexPattern,
            'paramKeys' => $paramKeys
        ];
    }

    public static function add(string $method, string $url, callable|array $action, array $middleware = [])
    {
        self::$lastRoute = null;

        // Apply group prefix
        $url = trim(self::$currentGroup['prefix'] . self::normalizePath($url), '/');
        $fullUrl = '/' . $url;
        self::$routeList[$method][] = $fullUrl;

        // Apply group middleware
        $middleware = array_merge(self::$currentGroup['middleware'], $middleware);

        // Apply namespace to controller actions
        if (is_array($action) && !empty(self::$currentGroup['namespace'])) {
            $action[0] = self::$currentGroup['namespace'] . '\\' . ltrim($action[0], '\\');
        }

        // Parse the URL pattern
        $parsed = self::parseRoutePattern($url);
        $regexPattern = $parsed['pattern'];
        $paramKeys = $parsed['paramKeys'];

        // Store the route
        self::$routes[$method][] = [
            'pattern' => $regexPattern,
            'action' => $action,
            'middleware' => $middleware,
            'paramKeys' => $paramKeys,
            'url' => $fullUrl
        ];

        self::$lastRoute = [
            'method' => $method,
            'url' => $fullUrl,
            'action' => $action,
            'middleware' => $middleware
        ];

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

    public static function patch(string $url, callable|array $action, array|string $middleware = []): self
    {
        self::add('PATCH', $url, $action, (array) $middleware);
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
        self::$appliedGroup[] = self::$currentGroup = [
            'prefix' => trim(self::$currentGroup['prefix'] . '/' . trim($attributes['prefix'] ?? '', '/'), '/'),
            'middleware' => array_merge(self::$currentGroup['middleware'], $attributes['middleware'] ?? []),
            'namespace' => self::$currentGroup['namespace'] . '\\' . trim($attributes['namespace'] ?? '', '\\'),
        ];

        // Execute the callback
        call_user_func($callback);

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
            $route = str_replace('{' . $key . ':.+}', $value, $route);
        }

        return $route;
    }

    // Resourceful routing (RESTful)
    public static function resource(string $name, string $controller, array $options = []): void
    {
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

    public static function routeList()
    {
        return self::$routeList;
    }

    public static function namedRouteList()
    {
        return self::$namedRoutes;
    }

    public static function stackList()
    {
        return self::$appliedGroup;
    }

    public static function dump()
    {
        return [
            'routes' => self::$routeList,
            'named_routes' => self::$namedRoutes,
            'stacks' => self::$appliedGroup,
        ];
    }

    public static function fallback(callable|array $callback)
    {
        self::$fallback = $callback;
    }

    private static function execute(callable|array $action, mixed $params)
    {
        if (is_array($action) && count($action) === 2) {
            $instance = new $action[0];
            $action = [$instance, $action[1]];
        }

        if (is_callable($action)) {
            $result = callFuncWithParams($action, $params, true, true);
            if (is_string($result)) {
                echo $result;
                exit;
            }
            return $result;
        }
        return Debugger::showErrorPage(500, 'Invalid callback');
    }

    public static function dispatch(string $requestUri)
    {
        $requestUri = trim($requestUri, '/');
        empty($requestUri) && $requestUri = '/';
        // echo 'Request uri: ' . $requestUri . '<br>';
        $method = self::getRequestMethod();

        if (!isset(self::$routes[$method])) {
            if (isset(self::$fallback)) {
                return self::execute(self::$fallback, []);
            }
            return Debugger::showErrorPage(404, 'Not found');
        }

        foreach (self::$routes[$method] as $route) {
            // echo 'Dispatch: ' . htmlspecialchars($route['pattern']) . '<br>';
            // echo $route['pattern'];
            // continue;
            if (preg_match($route['pattern'], $requestUri, $matches)) {
                // Filter out numeric keys (full pattern matches)
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Reorder params according to paramKeys
                $orderedParams = [];
                foreach ($route['paramKeys'] as $key) {
                    if (isset($params[$key])) {
                        $orderedParams[$key] = $params[$key];
                    }
                }

                foreach (self::$plugins as $fn) {
                    $fn();
                }

                $middleware = array_merge(self::$globalMiddleware, $route['middleware'] ?? []);

                foreach ($middleware as $m) {
                    if (is_callable($m)) {
                        $response = call_user_func($m);
                        if ($response !== true) {
                            return $response;
                        }
                    } elseif (is_string($m)) {
                        $middlewareInstance = new $m();
                        $response = $middlewareInstance->handle();
                        if ($response !== true) {
                            return $response;
                        }
                    }
                }

                return self::execute($route['action'], $orderedParams);
            }
        }
        // die;

        if (isset(self::$fallback)) {
            return self::execute(self::$fallback, []);
        }
        return Debugger::showErrorPage(404, 'Not found');
    }

    public static function debugRoutes()
    {
        echo "Registered Routes:\n";
        foreach (self::$routeList as $method => $routes) {
            echo "$method:\n";
            foreach ($routes as $route) {
                echo "  $route\n";
            }
        }
    }
    public static function debugPatterns(): array
    {
        $debug = [];
        foreach (self::$routes as $method => $routes) {
            foreach ($routes as $route) {
                $debug[$method][] = [
                    'url' => $route['url'],
                    'pattern' => $route['pattern'],
                    'paramKeys' => $route['paramKeys']
                ];
            }
        }
        return $debug;
    }
}
