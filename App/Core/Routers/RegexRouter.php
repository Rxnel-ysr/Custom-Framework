<?php

namespace App\Foundation\Http;

use App\Debug\Debugger;
use Closure;
use RouterBase;
use RouterInterface;
use Throwable;

/**
 * Regex Router
 */
class RouteRegex extends RouterBase implements RouterInterface
{
    public string $name = 'RegexRouter';
    private string $dirRoot;
    private array $routes = [];
    private array $globalMiddleware = [];
    private array $routeMiddleware = [];
    private null|array|Closure $fallback = null;
    private array $routeList = [];
    private array $plugins = [];
    private array $namedRoutes = [];
    private ?array $lastRoute = null;

    // Group stack for nested groups
    private array $groupStack = [];
    private array $appliedGroup = [];

    // Current group attributes
    private array $currentGroup = [
        'prefix' => '',
        'middleware' => [],
        'namespace' => '',
    ];

    public function init(?string $root = null, array $plugins = [])
    {
        $this->dirRoot = $root ?? $_SERVER['DOCUMENT_ROOT'] ?? null;
        $this->plugins = $plugins;
    }

    public function getRequestMethod(): string
    {
        return $_POST['_HTTP_METHOD'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    private function normalizePath(string $path): string
    {
        return '/' . trim($path, '/');
    }

    public function middleware(string|array $middleware, ?callable $callback = null): null|self
    {
        if (!is_array($middleware)) {
            $middleware = [$middleware];
        }

        if (!is_null($callback)) {
            return self::group(['middleware' => $middleware], $callback);
        }

        // Store route-specific middleware for the last added route
        if ($this->lastRoute !== null) {
            $method = $this->lastRoute['method'];
            $url = $this->lastRoute['url'];

            // Update the middleware for this specific route
            if (isset($this->routes[$method][$url])) {
                $this->routes[$method][$url]['middleware'] = array_merge(
                    $this->routes[$method][$url]['middleware'] ?? [],
                    $middleware
                );
            }

            // Also update lastRoute for chaining
            $this->lastRoute['middleware'] = array_merge(
                $this->lastRoute['middleware'] ?? [],
                $middleware
            );
        } 

        return new self();
    }

    private function parseRoutePattern(string $pattern): array
    {
        $segments = explode('/', trim($pattern, '/'));
        $paramKeys = [];
        $regexPattern = '#^';

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
                $regexPattern .= preg_quote($segment, '/');
            }

            // echo $regexPattern . '<br>';
            // echo  'END<br>';

        }

        if (! empty($regexPattern)) {
            $regexPattern =  $regexPattern . '$#';
        } else {
            $regexPattern = null;
        }


        return [
            'pattern' => $regexPattern,
            'paramKeys' => $paramKeys
        ];
    }

    public function add(string $method, string $url, callable|array $action, array $middleware = [])
    {
        $this->lastRoute = null;

        // Apply group prefix
        $url = trim($this->currentGroup['prefix'] . self::normalizePath($url), '/');
        $this->routeList[$method][] = '/' . $url;

        // Apply group middleware
        $middleware = array_merge($this->currentGroup['middleware'], $middleware);

        // Apply namespace to controller actions
        if (is_array($action) && !empty($this->currentGroup['namespace'])) {
            $action[0] = $this->currentGroup['namespace'] . '\\' . ltrim($action[0], '\\');
        }

        // Parse the URL pattern
        $parsed = self::parseRoutePattern($url);
        $regexPattern = $parsed['pattern'];
        $paramKeys = $parsed['paramKeys'];

        // Store the route
        $this->routes[$method][$url] = [
            'pattern' => $regexPattern,
            'patternExplanation' => translateRegex($regexPattern),
            'action' => $action,
            'middleware' => $middleware, // Initialize middleware here
            'paramKeys' => $paramKeys,
            'url' => $url
        ];

        $this->lastRoute = [
            'method' => $method,
            'url' => $url,
            'action' => $action,
            'middleware' => $middleware
        ];

        return new Self();
    }

    // HTTP verb methods (now return $this for chaining)
    public function get(string $url, callable|array $action, array|string $middleware = []): self
    {
        self::add('GET', $url, $action, (array) $middleware);
        return new self();
    }

    public function post(string $url, callable|array $action, array|string $middleware = []): self
    {
        self::add('POST', $url, $action, (array) $middleware);
        return new self();
    }

    public function patch(string $url, callable|array $action, array|string $middleware = []): self
    {
        self::add('PATCH', $url, $action, (array) $middleware);
        return new self();
    }

    public function put(string $url, callable|array $action, array|string $middleware = []): self
    {
        self::add('PUT', $url, $action, (array) $middleware);
        return new self();
    }

    public function delete(string $url, callable|array $action, array|string $middleware = []): self
    {
        self::add('DELETE', $url, $action, (array) $middleware);
        return new self();
    }

    // Named routes
    public function name(string $name): self
    {
        if (empty($name)) return $this;
        $this->namedRoutes[$name] = $this->lastRoute;
        return $this;
    }

    // Route groups
    public function group(array $attributes, callable $callback): void
    {
        // Push current group attributes to stack
        $this->groupStack[] = $this->currentGroup;

        // Merge new attributes
        $this->appliedGroup[] = $this->currentGroup = [
            'prefix' => trim($this->currentGroup['prefix'] . '/' . trim($attributes['prefix'] ?? '', '/'), '/'),
            'middleware' => array_merge($this->currentGroup['middleware'], $attributes['middleware'] ?? []),
            'namespace' => $this->currentGroup['namespace'] . '\\' . trim($attributes['namespace'] ?? '', '\\'),
        ];

        // Execute the callback
        call_user_func($callback);

        // Restore previous group attributes
        $this->currentGroup = array_pop($this->groupStack);
    }

    // Reverse routing
    public function route(string $name, array $parameters = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \InvalidArgumentException("Route name [$name] not found.");
        }

        $route = $this->namedRoutes[$name]['url'];

        foreach ($parameters as $key => $value) {
            $route = str_replace('{' . $key . '}', $value, $route);
            $route = str_replace('{' . $key . ':.+}', $value, $route);
        }

        return $route;
    }

    // Resourceful routing (RESTful)
    public function resource(string $name, string $controller, array $options = []): void
    {
        $name = trim($name, '/');
        $only = array_fill_keys($options['only'] ?? ['index', 'show', 'create', 'store', 'edit', 'update', 'destroy'], true);
        $except = array_fill_keys($options['except'] ?? [], true);
        $names = $options['names'] ?? [];
        $middleware = $options['middleware'] ?? [];

        $routes = [
            'index' => ['GET', "/{$name}", [$controller, 'index']],
            'create' => ['GET', "/{$name}/create", [$controller, 'create']],
            'store' => ['POST', "/{$name}", [$controller, 'store']],
            'show' => ['GET', "/{$name}/{id}", [$controller, 'show']],
            'edit' => ['GET', "/{$name}/{id}/edit", [$controller, 'edit']],
            'update' => ['PUT', "/{$name}/{id}", [$controller, 'update']],
            'destroy' => ['DELETE', "/{$name}/{id}", [$controller, 'destroy']],
        ];

        self::group(['middleware' => $middleware], function () use ($routes, $only, $except, $names, $name) {
            $index = -1;
            foreach ($routes as $action => $route) {
                $index++;
                if (($only[$action] ?? false) && (!$except[$action] ?? true)) {
                    self::add($route[0], $route[1], $route[2])->name($names[$index] ?? "{$name}.{$route[2][1]}");
                }
            }
        });
    }

    // View routes
    public function view(string $uri, string $view, array $data = []): self
    {
        return self::get($uri, fn() => view($view, $data));
    }

    // Redirect routes
    public function redirect(string $from, string $to, int $status = 302): self
    {
        return self::get($from, fn() => header("Location: $to", true, $status));
    }

    public function routeList()
    {
        return $this->routeList;
    }

    public function namedRouteList()
    {
        return $this->namedRoutes;
    }

    public function stackList()
    {
        return $this->appliedGroup;
    }

    public function dump()
    {
        return [
            'routes' => $this->routeList,
            'named_routes' => $this->namedRoutes,
            'stacks' => $this->appliedGroup,
        ];
    }

    public function fallback(callable|array $callback)
    {
        $this->fallback = $callback;
    }

    private function execute(callable|array $action, mixed $params)
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

    public function dispatch(Request $request)
    {
        $requestUri = trim($request->uri(), '/');
        // echo 'Request uri: ' . $requestUri . '<br>';
        // die;
        $method = self::getRequestMethod();

        if (!isset($this->routes[$method])) {
            if (isset($this->fallback)) {
                return self::execute($this->fallback, []);
            }
            return Debugger::showErrorPage(404, 'Not found');
        }

        if (isset($this->routes[$method][$requestUri])) {
            // echo 'Here';
            // die;
            $res = $this->routes[$method][$requestUri];

            foreach ($this->plugins as $fn) {
                $fn();
            }

            $middleware = array_merge($this->globalMiddleware, $res['middleware'] ?? []);

            $destination = fn() => self::execute($res['action'], []);

            return self::pipeline($request, $middleware, $destination);;
        }

        // echo '<pre>';
        // print_r($this->routes[$method]);
        // echo '</pre>';
        // die;
        foreach ($this->routes[$method] as $route) {
            if ($route['pattern'] == null) continue;
            // echo $route['pattern'];
            // continue;

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

                foreach ($this->plugins as $fn) {
                    $fn();
                }

                $middleware = array_merge($this->globalMiddleware, $route['middleware'] ?? []);

                $destination = fn() => self::execute($route['action'], $orderedParams);

                return self::pipeline($request, $middleware, $destination);
            }
        }

        // die;

        // $routes = $this->routes[$method];
        // $patterns = array_column($routes, 'pattern');

        // $matchResults = safe_bulk_match($patterns, $requestUri, 0.05); // 30ms per regex
        // $matchedIndex = null;
        // $tempInt = 0;
        // // var_dump($matchResults);
        // // die;

        // foreach ($matchResults as $i => $result) {
        //     if ($result === true) {
        //         $matchedIndex = $i;
        //         break;
        //     }
        //     $tempInt++;
        // }
        // // echo $matchedIndex;
        // // die;

        // if ($matchedIndex === null) {
        //     return Debugger::showErrorPage(404, 'Not found');
        // }

        // $route = $routes[$tempInt];
        // preg_match($route['pattern'], $requestUri, $matches);
        // $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

        // $orderedParams = [];
        // foreach ($route['paramKeys'] as $key) {
        //     if (isset($params[$key])) {
        //         $orderedParams[$key] = $params[$key];
        //     }
        // }

        // foreach ($this->plugins as $fn) {
        //     $fn();
        // }

        // $middleware = array_merge($this->globalMiddleware, $route['middleware'] ?? []);

        // foreach ($middleware as $m) {
        //     if (is_callable($m)) {
        //         $response = call_user_func($m);
        //         if ($response !== true) return $response;
        //     } elseif (is_string($m)) {
        //         $middlewareInstance = new $m();
        //         $response = $middlewareInstance->handle();
        //         if ($response !== true) return $response;
        //     }
        // }

        // return self::execute($route['action'], $orderedParams);


        // die;

        if (isset($this->fallback)) {
            return self::execute($this->fallback, []);
        }
        return Debugger::showErrorPage(404, 'Not found');
    }

    public function debugRoutes()
    {
        echo "Registered Routes:\n";
        foreach ($this->routeList as $method => $routes) {
            echo "$method:\n";
            foreach ($routes as $route) {
                echo "  $route\n";
            }
        }
    }
    public function debugPatterns(): array
    {
        $debug = [];
        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $route) {
                $debug[$method][] = [
                    'url' => $route['url'],
                    'pattern' => $route['pattern'],
                    'patternExplanation' => $route['patternExplanation'],
                    'paramKeys' => $route['paramKeys']
                ];
            }
        }
        return $debug;
    }
}
