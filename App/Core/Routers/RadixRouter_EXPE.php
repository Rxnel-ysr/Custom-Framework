<?php

namespace App\Foundation\Http;

use App\Debug\Debugger;
use App\Foundation\Http\Request;
use Closure;
use RouterBase;
use RouterInterface;

class RadixNode
{
    public array $children = [];
    public array $handlers = [];
    public array $paramKeys = [];
    public array $middleware = [];
    public string $path = '';
    public bool $isParam = false;
    public bool $isLeaf = false;
}

/**
 * High-Performance Radix Router with Method-Based Roots
 */
class Route extends RouterBase implements RouterInterface
{
    public static string $name = 'RadixRouter';
    
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
    private static array $appliedGroup = [];

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
            self::$roots[$method] = new RadixNode();
        }
        
        self::$dirRoot = $root ?? $_SERVER['DOCUMENT_ROOT'] ?? null;
        self::$plugins = $plugins;
    }

    public static function getRequestMethod(): string
    {
        // Single lookup pattern - most common case first
        return $_POST['_HTTP_METHOD'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    private static function normalizePath(string $path): string
    {
        // Ultra-fast path normalization
        $path = trim($path, '/');
        return $path === '' ? '/' : '/' . $path;
    }

    public static function middleware(string|array $middleware, ?callable $callback = null): null|self
    {
        if (!is_array($middleware)) {
            $middleware = [$middleware];
        }

        if (!is_null($callback)) {
            return self::group(['middleware' => $middleware], $callback);
        }

        if (self::$lastRoute !== null) {
            self::$lastRoute['middleware'] = array_merge(
                self::$lastRoute['middleware'] ?? [],
                $middleware
            );

            // Update the specific method's node
            $method = self::$lastRoute['method'];
            $url = trim(self::$lastRoute['url'], '/');
            $segments = $url === '' ? [] : explode('/', $url);
            
            if (isset(self::$roots[$method])) {
                $result = self::searchNode(self::$roots[$method], $segments);
                if ($result !== null) {
                    $result['node']->middleware = array_merge(
                        $result['node']->middleware,
                        $middleware
                    );
                }
            }
        }

        return new self();
    }

    private static function insertNode(RadixNode $node, array $segments, string $method, callable|array $action, array $middleware, array $paramKeys): RadixNode
    {
        if (empty($segments)) {
            $node->handlers[$method] = $action;
            $node->middleware = $middleware;
            $node->paramKeys = $paramKeys;
            $node->isLeaf = true;
            return $node;
        }

        $segment = $segments[0];
        $remainingSegments = array_slice($segments, 1);

        // Fast parameter detection
        if (isset($segment[0]) && $segment[0] === '{' && substr($segment, -1) === '}') {
            $paramKeys[] = substr($segment, 1, -1);
            $segment = '{}';
            $isParam = true;
        } else {
            $isParam = false;
        }

        // Direct child matching - no cache needed, tree is fast enough
        foreach ($node->children as $child) {
            if ($child->path === $segment || ($child->isParam && $isParam)) {
                return self::insertNode($child, $remainingSegments, $method, $action, $middleware, $paramKeys);
            }

            // Prefix matching for radix tree efficiency
            $commonPrefix = self::fastCommonPrefix($child->path, $segment);
            if ($commonPrefix !== '') {
                return self::splitAndInsert($node, $child, $segment, $remainingSegments, $commonPrefix, $method, $action, $middleware, $paramKeys, $isParam);
            }
        }

        // Create new node - this is the slow path but only happens during route registration
        $newNode = new RadixNode();
        $newNode->path = $segment;
        $newNode->isParam = $isParam;
        $node->children[] = $newNode;
        return self::insertNode($newNode, $remainingSegments, $method, $action, $middleware, $paramKeys);
    }

    private static function fastCommonPrefix(string $a, string $b): string
    {
        $minLen = min(strlen($a), strlen($b));
        for ($i = 0; $i < $minLen; $i++) {
            if ($a[$i] !== $b[$i]) {
                return $i === 0 ? '' : substr($a, 0, $i);
            }
        }
        return $minLen === 0 ? '' : substr($a, 0, $minLen);
    }

    private static function splitAndInsert(RadixNode $parent, RadixNode $child, string $segment, array $remainingSegments, string $commonPrefix, string $method, callable|array $action, array $middleware, array $paramKeys, bool $isParam): RadixNode
    {
        $splitNode = new RadixNode();
        $splitNode->path = substr($child->path, strlen($commonPrefix));
        $splitNode->children = $child->children;
        $splitNode->handlers = $child->handlers;
        $splitNode->middleware = $child->middleware;
        $splitNode->paramKeys = $child->paramKeys;
        $splitNode->isParam = $child->isParam;
        $splitNode->isLeaf = $child->isLeaf;

        $child->path = $commonPrefix;
        $child->children = [$splitNode];
        $child->handlers = [];
        $child->isParam = false;
        $child->isLeaf = false;

        if ($commonPrefix !== $segment) {
            $newNode = new RadixNode();
            $newNode->path = substr($segment, strlen($commonPrefix));
            $newNode->isParam = $isParam;
            $child->children[] = $newNode;
            return self::insertNode($newNode, $remainingSegments, $method, $action, $middleware, $paramKeys);
        }

        return self::insertNode($child, $remainingSegments, $method, $action, $middleware, $paramKeys);
    }

    public static function add(string $method, string $url, callable|array $action, array $middleware = [])
    {
        self::$lastRoute = null;
        
        // Apply group prefix
        $prefix = self::$currentGroup['prefix'];
        $url = $prefix === '' ? $url : trim($prefix . '/' . trim($url, '/'), '/');
        $normalizedUrl = self::normalizePath($url);
        
        // Store route for debugging
        self::$routeList[$method][] = $normalizedUrl;

        // Apply group middleware
        $middleware = array_merge(self::$currentGroup['middleware'], $middleware);

        // Apply namespace
        if (is_array($action) && !empty(self::$currentGroup['namespace'])) {
            $namespace = self::$currentGroup['namespace'];
            $action[0] = $namespace . '\\' . ltrim($action[0], '\\');
        }

        // Prepare segments
        $trimmedUrl = trim($url, '/');
        $segments = $trimmedUrl === '' ? [] : explode('/', $trimmedUrl);
        $paramKeys = [];

        // Insert into method-specific root - no cache needed
        if (isset(self::$roots[$method])) {
            self::insertNode(self::$roots[$method], $segments, $method, $action, $middleware, $paramKeys);
        }

        self::$lastRoute = [
            'method' => $method,
            'url' => $url,
            'action' => $action,
            'middleware' => $middleware
        ];

        return new Self();
    }

    private static function searchNode(RadixNode $node, array $segments, array $params = []): ?array
    {
        if (empty($segments)) {
            return $node->isLeaf ? ['node' => $node, 'params' => $params] : null;
        }

        $segment = $segments[0];
        $remainingSegments = array_slice($segments, 1);

        foreach ($node->children as $child) {
            if ($child->isParam) {
                // Parameter node - match any segment
                $newParams = $params;
                $newParams[] = $segment;
                $result = self::searchNode($child, $remainingSegments, $newParams);
                if ($result !== null) {
                    return $result;
                }
            } elseif (str_starts_with($segment, $child->path)) {
                // Exact or prefix match
                if ($segment === $child->path) {
                    $result = self::searchNode($child, $remainingSegments, $params);
                } else {
                    // Partial match
                    $remainingPart = substr($segment, strlen($child->path));
                    $newSegments = array_merge([$remainingPart], $remainingSegments);
                    $result = self::searchNode($child, $newSegments, $params);
                }
                
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    // HTTP verb methods - each uses its own radix tree
    public static function get(string $url, callable|array $action, array|string $middleware = []): self
    {
        return self::add('GET', $url, $action, (array) $middleware);
    }

    public static function post(string $url, callable|array $action, array|string $middleware = []): self
    {
        return self::add('POST', $url, $action, (array) $middleware);
    }

    public static function patch(string $url, callable|array $action, array|string $middleware = []): self
    {
        return self::add('PATCH', $url, $action, (array) $middleware);
    }

    public static function put(string $url, callable|array $action, array|string $middleware = []): self
    {
        return self::add('PUT', $url, $action, (array) $middleware);
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
        if ($name !== '' && self::$lastRoute !== null) {
            self::$namedRoutes[$name] = self::$lastRoute;
        }
        return $this;
    }

    // Route groups
    public static function group(array $attributes, callable $callback): void
    {
        self::$groupStack[] = self::$currentGroup;

        $prefix = trim(self::$currentGroup['prefix'] . '/' . trim($attributes['prefix'] ?? '', '/'), '/');
        $middleware = array_merge(
            self::$currentGroup['middleware'], 
            (array)($attributes['middleware'] ?? [])
        );
        $namespace = self::$currentGroup['namespace'] . 
                    (isset($attributes['namespace']) ? '\\' . trim($attributes['namespace'], '\\') : '');

        self::$appliedGroup[] = self::$currentGroup = compact('prefix', 'middleware', 'namespace');

        $callback();

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

        return self::normalizePath($route);
    }

    // Resource routing
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
            $index = 0;
            foreach ($routes as $action => [$method, $path, $handler]) {
                if (isset($only[$action]) && !isset($except[$action])) {
                    self::add($method, $path, [$controller, $handler])
                        ->name($names[$index] ?? "{$name}.{$handler}");
                }
                $index++;
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
        $segments = $requestUri === '' ? [] : explode('/', $requestUri);
        
        // Direct lookup in method-specific radix tree - no cache needed!
        $root = self::$roots[$method] ?? null;
        // dd($root);
        
        if ($root === null) {
            return self::handleNotFound();
        }
        
        $result = self::searchNode($root, $segments);

        if ($result !== null) {
            $node = $result['node'];
            $params = $result['params'];

            if (isset($node->handlers[$method])) {
                // Execute plugins
                foreach (self::$plugins as $fn) {
                    $fn();
                }

                $middleware = array_merge(self::$globalMiddleware, $node->middleware);
                $destination = fn() => self::execute(
                    $node->handlers[$method], 
                    array_combine($node->paramKeys, $params)
                );

                return self::pipeline($request, $middleware, $destination);
            }
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
    public static function debugTree(?string $method = 'GET', ?RadixNode $node = null, int $indent = 0): void
    {
        if ($method !== null) {
            $node = self::$roots[$method] ?? self::$roots['GET'];
        }
        
        $node = $node ?? self::$roots['GET'];
        $indentStr = str_repeat(' ', $indent * 2);

        echo $indentStr . "Node: " . $node->path .
            ($node->isParam ? ' (param)' : '') .
            ($node->isLeaf ? ' [LEAF]' : '') .
            (!empty($node->handlers) ? ' [HANDLERS: ' . implode(',', array_keys($node->handlers)) . ']' : '') . "\n";

        foreach ($node->children as $child) {
            self::debugTree(null, $child, $indent + 1);
        }
    }

    public static function routeList(): array
    {
        return self::$routeList;
    }

    public static function getNamedRoutes(): array
    {
        return self::$namedRoutes;
    }
}