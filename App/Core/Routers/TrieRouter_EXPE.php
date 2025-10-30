<?php

namespace App\Foundation\Http;

use App\Debug\Debugger;
use App\Foundation\Http\Request;
use Closure;
use RouterBase;
use RouterInterface;

class TrieNode
{
    public array $children = [];
    public array $handlers = [];
    public array $paramKeys = [];
    public array $middleware = [];
    public bool $isLeaf = false;
}

/**
 * High-Performance Trie Router with Method-Based Roots
 */
class Route extends RouterBase implements RouterInterface
{
    public static string $name = 'TrieRouter';
    
    // Method-based root nodes for optimal performance
    private static array $roots = [];
    
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
        // Initialize method-specific roots
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
        foreach ($methods as $method) {
            self::$roots[$method] = new TrieNode();
        }
        
        self::$dirRoot = $root ?? $_SERVER['DOCUMENT_ROOT'] ?? null;
        self::$plugins = $plugins;
    }

    public static function getRequestMethod(): string
    {
        // Optimized method detection
        return $_POST['_HTTP_METHOD'] 
            ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] 
            ?? $_SERVER['REQUEST_METHOD'] 
            ?? 'GET';
    }

    public static function middleware(string|array $middleware, ?callable $callback = null): null|self
    {
        if (!is_array($middleware)) {
            $middleware = [$middleware];
        }

        if (!is_null($callback)) {
            return self::group(['middleware' => $middleware], $callback);
        }

        // Store route-specific middleware for the last added route
        if (self::$lastRoute !== null) {
            $method = self::$lastRoute['method'];
            $url = self::$lastRoute['url'];

            // Find and update the node with the new middleware
            $segments = self::splitPath($url);
            
            if (isset(self::$roots[$method])) {
                $node = self::$roots[$method];
                foreach ($segments as $segment) {
                    if (isset($segment[0]) && $segment[0] === '{' && substr($segment, -1) === '}') {
                        $segment = '{}';
                    }

                    if (isset($node->children[$segment])) {
                        $node = $node->children[$segment];
                    } else {
                        // Node not found, can't update middleware
                        return new self();
                    }
                }

                // Update the middleware for this specific node
                $node->middleware = array_merge($node->middleware, $middleware);

                // Also update lastRoute for chaining
                self::$lastRoute['middleware'] = array_merge(
                    self::$lastRoute['middleware'] ?? [],
                    $middleware
                );
            }
        }

        return new self();
    }

    private static function splitPath(string $path): array
    {
        // Optimized path splitting without strtok
        $path = trim($path, '/');
        return $path === '' ? [] : explode('/', $path);
    }

    public static function add(string $method, string $url, callable|array $action, array $middleware = [])
    {
        self::$lastRoute = null;
        
        // Apply group prefix efficiently
        $prefix = self::$currentGroup['prefix'];
        $url = $prefix === '' ? $url : trim($prefix . '/' . trim($url, '/'), '/');
        
        // Store route for debugging
        self::$routeList[$method][] = '/' . $url;

        // Apply group middleware
        $middleware = array_merge(self::$currentGroup['middleware'], $middleware);

        // Apply namespace to controller actions efficiently
        if (is_array($action) && !empty(self::$currentGroup['namespace'])) {
            $namespace = self::$currentGroup['namespace'];
            $action[0] = $namespace . '\\' . ltrim($action[0], '\\');
        }

        $segments = self::splitPath($url);
        $paramKeys = [];

        // Use method-specific root
        if (!isset(self::$roots[$method])) {
            self::$roots[$method] = new TrieNode();
        }
        
        $node = self::$roots[$method];

        foreach ($segments as $segment) {
            // Fast parameter detection without regex
            if (isset($segment[0]) && $segment[0] === '{' && substr($segment, -1) === '}') {
                $paramKeys[] = substr($segment, 1, -1);
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
        $node->isLeaf = true;

        self::$lastRoute = [
            'method' => $method,
            'url' => $url,
            'action' => $action,
            'middleware' => $middleware
        ];

        return new Self();
    }

    // Optimized HTTP verb methods - each uses its own trie
    public static function get(string $url, callable|array $action, array|string $middleware = []): self
    {
        return self::add('GET', $url, $action, (array) $middleware);
    }

    public static function post(string $url, callable|array $action, array|string $middleware = []): self
    {
        return self::add('POST', $url, $action, (array) $middleware);
    }

    public static function put(string $url, callable|array $action, array|string $middleware = []): self
    {
        return self::add('PUT', $url, $action, (array) $middleware);
    }

    public static function patch(string $url, callable|array $action, array|string $middleware = []): self
    {
        return self::add('PATCH', $url, $action, (array) $middleware);
    }

    public static function delete(string $url, callable|array $action, array|string $middleware = []): self
    {
        return self::add('DELETE', $url, $action, (array) $middleware);
    }

    public static function head(string $url, callable|array $action, array|string $middleware = []): self
    {
        return self::add('HEAD', $url, $action, (array) $middleware);
    }

    public static function options(string $url, callable|array $action, array|string $middleware = []): self
    {
        return self::add('OPTIONS', $url, $action, (array) $middleware);
    }

    // Named routes
    public function name(string $name): self
    {
        if (!empty($name) && self::$lastRoute !== null) {
            self::$namedRoutes[$name] = self::$lastRoute;
        }
        return $this;
    }

    // Optimized route groups
    public static function group(array $attributes, callable $callback): void
    {
        // Push current group attributes to stack
        self::$groupStack[] = self::$currentGroup;

        // Efficient attribute merging
        $prefix = trim(self::$currentGroup['prefix'] . '/' . trim($attributes['prefix'] ?? '', '/'), '/');
        $middleware = array_merge(self::$currentGroup['middleware'], (array)($attributes['middleware'] ?? []));
        $namespace = self::$currentGroup['namespace'] . 
                    (isset($attributes['namespace']) ? '\\' . trim($attributes['namespace'], '\\') : '');

        self::$currentGroup = compact('prefix', 'middleware', 'namespace');

        // Execute the callback
        $callback();

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
            $route = str_replace('{' . $key . '}', (string)$value, $route);
        }

        return '/' . $route;
    }

    // Optimized resourceful routing (RESTful)
    public static function resource(string $name, string $controller, array $options = []): void
    {
        $name = trim($name, '/');
        $only = array_flip($options['only'] ?? ['index', 'show', 'create', 'store', 'edit', 'update', 'destroy']);
        $except = array_flip($options['except'] ?? []);
        $names = $options['names'] ?? [];
        $middleware = $options['middleware'] ?? [];

        $routes = [
            'index' => ['GET', "/{$name}", 'index'],
            'create' => ['GET', "/{$name}/create", 'create'],
            'store' => ['POST', "/{$name}", 'store'],
            'show' => ['GET', "/{$name}/{id}", 'show'],
            'edit' => ['GET', "/{$name}/{id}/edit", 'edit'],
            'update' => ['PUT', "/{$name}/{id}", 'update'],
            'destroy' => ['DELETE', "/{$name}/{id}", 'destroy'],
        ];

        self::group(['middleware' => $middleware], function () use ($routes, $only, $except, $names, $name, $controller) {
            foreach ($routes as $action => [$method, $path, $handler]) {
                if (isset($only[$action]) && !isset($except[$action])) {
                    self::add($method, $path, [$controller, $handler])
                        ->name($names[$action] ?? "{$name}.{$handler}");
                }
            }
        });
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

    public static function routeList(): array
    {
        return self::$routeList;
    }

    public static function namedRoutes(): array
    {
        return self::$namedRoutes;
    }

    public static function fallback(callable|array $callback): void
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

    public static function dispatch(Request $request)
    {
        $method = self::getRequestMethod();
        $requestUri = trim($request->uri(), '/');
        
        // Use method-specific trie root
        if (!isset(self::$roots[$method])) {
            return self::handleNotFound();
        }

        $segments = self::splitPath($requestUri);
        $node = self::$roots[$method];
        $params = [];

        // Direct traversal of method-specific trie
        foreach ($segments as $segment) {
            if (isset($node->children[$segment])) {
                $node = $node->children[$segment];
            } elseif (isset($node->children['{}'])) {
                $node = $node->children['{}'];
                $params[] = $segment;
            } else {
                return self::handleNotFound();
            }
        }

        if (isset($node->handlers[$method]) && $node->isLeaf) {
            foreach (self::$plugins as $fn) {
                $fn();
            }

            // Get middleware from the matched node
            $middleware = array_merge(self::$globalMiddleware, $node->middleware);
            
            $destination = fn() => self::execute(
                $node->handlers[$method], 
                array_combine($node->paramKeys, $params)
            );

            return self::pipeline($request, $middleware, $destination);
        }

        return self::handleNotFound();
    }

    private static function handleNotFound()
    {
        if (isset(self::$fallback)) {
            return self::execute(self::$fallback, []);
        }
        return Debugger::showErrorPage(404, 'Not found');
    }

    // Debug methods
    public static function debugTree(?string $method = 'GET', ?TrieNode $node = null, int $indent = 0): void
    {
        if ($method !== null) {
            $node = self::$roots[$method] ?? self::$roots['GET'];
        }
        
        $node = $node ?? self::$roots['GET'];
        $indentStr = str_repeat(' ', $indent * 2);

        echo $indentStr . "Node [children: " . count($node->children) . "]" .
            ($node->isLeaf ? ' [LEAF]' : '') .
            (!empty($node->handlers) ? ' [HANDLERS: ' . implode(',', array_keys($node->handlers)) . ']' : '') . "\n";

        foreach ($node->children as $path => $child) {
            echo $indentStr . "  -> {$path}\n";
            self::debugTree(null, $child, $indent + 1);
        }
    }

    // Performance monitoring
    public static function getStats(): array
    {
        $stats = [];
        foreach (self::$roots as $method => $root) {
            $stats[$method] = [
                'routes' => count(self::$routeList[$method] ?? []),
                'nodes' => self::countNodes($root),
            ];
        }
        return $stats;
    }

    private static function countNodes(TrieNode $node): int
    {
        $count = 1;
        foreach ($node->children as $child) {
            $count += self::countNodes($child);
        }
        return $count;
    }
}