<?php

namespace App\Foundation\Http;

use App\Debug\Debugger;
use App\Foundation\Http\Request;
use App\Foundation\Manager\InstanceManager;
use Closure;

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
class RouteRadix extends RouterBase implements RouterInterface
{
    public string $name = 'RadixRouter';

    // Method-based root nodes for optimal performance
    private array $roots = [];

    private string $dirRoot;
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
        // Initialize method-specific roots
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
        foreach ($methods as $method) {
            $this->roots[$method] = new RadixNode();
        }

        $this->dirRoot = $root ?? $_SERVER['DOCUMENT_ROOT'] ?? null;
        $this->plugins = $plugins;
    }

    public function getRequestMethod(): string
    {
        // Single lookup pattern - most common case first
        return $_POST['_HTTP_METHOD'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    private function normalizePath(string $path): string
    {
        // Ultra-fast path normalization
        $path = trim($path, '/');
        return $path === '' ? '/' : '/' . $path;
    }

    public function middleware(string|array $middleware, ?callable $callback = null)
    {
        if (!is_array($middleware)) {
            $middleware = [$middleware];
        }

        if (!is_null($callback)) {
            return self::group(['middleware' => $middleware], $callback);
        }
        // dd($this->lastRoute);

        if ($this->lastRoute !== null) {
            $this->lastRoute['middleware'] = array_merge(
                $this->lastRoute['middleware'] ?? [],
                $middleware
            );

            // Update the specific method's node
            $method = $this->lastRoute['method'];
            $url = trim($this->lastRoute['url'], '/');
            $segments = $url === '' ? [] : explode('/', $url);

            if (isset($this->roots[$method])) {
                $result = self::searchNode($this->roots[$method], $segments);
                if ($result !== null) {
                    $result['node']->middleware = array_merge(
                        $result['node']->middleware,
                        $middleware
                    );
                }
            }
        }

        return $this;
    }

    private function insertNode(RadixNode $node, array $segments, string $method, callable|array $action, array $middleware, array $paramKeys): RadixNode
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

    private function fastCommonPrefix(string $a, string $b): string
    {
        $minLen = min(strlen($a), strlen($b));
        for ($i = 0; $i < $minLen; $i++) {
            if ($a[$i] !== $b[$i]) {
                return $i === 0 ? '' : substr($a, 0, $i);
            }
        }
        return $minLen === 0 ? '' : substr($a, 0, $minLen);
    }

    private function splitAndInsert(RadixNode $parent, RadixNode $child, string $segment, array $remainingSegments, string $commonPrefix, string $method, callable|array $action, array $middleware, array $paramKeys, bool $isParam): RadixNode
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

    public function add(string $method, string $url, callable|array $action, array $middleware = [])
    {
        $this->lastRoute = null;

        // Apply group prefix
        $prefix = $this->currentGroup['prefix'];
        $url = $prefix === '' ? $url : trim($prefix . '/' . trim($url, '/'), '/');
        $normalizedUrl = self::normalizePath($url);

        // Store route for debugging
        $this->routeList[$method][] = $normalizedUrl;

        // Apply group middleware
        $middleware = array_merge($this->currentGroup['middleware'], $middleware);

        // Apply namespace
        if (is_array($action) && !empty($this->currentGroup['namespace'])) {
            $namespace = $this->currentGroup['namespace'];
            $action[0] = $namespace . '\\' . ltrim($action[0], '\\');
        }

        // Prepare segments
        $trimmedUrl = trim($url, '/');
        $segments = $trimmedUrl === '' ? [] : explode('/', $trimmedUrl);
        $paramKeys = [];

        // Insert into method-specific root - no cache needed
        if (isset($this->roots[$method])) {
            self::insertNode($this->roots[$method], $segments, $method, $action, $middleware, $paramKeys);
        }

        $this->lastRoute = [
            'method' => $method,
            'url' => $url,
            'action' => $action,
            'middleware' => $middleware
        ];

        return $this;
    }

    private function searchNode(RadixNode $node, array $segments, array $params = []): ?array
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
    public function get(string $url, callable|array $action, array|string $middleware = []): self
    {
        return self::add('GET', $url, $action, (array) $middleware);
    }

    public function post(string $url, callable|array $action, array|string $middleware = []): self
    {
        return self::add('POST', $url, $action, (array) $middleware);
    }

    public function patch(string $url, callable|array $action, array|string $middleware = []): self
    {
        return self::add('PATCH', $url, $action, (array) $middleware);
    }

    public function put(string $url, callable|array $action, array|string $middleware = []): self
    {
        return self::add('PUT', $url, $action, (array) $middleware);
    }

    public function delete(string $url, callable|array $action, array|string $middleware = []): self
    {
        return self::add('DELETE', $url, $action, (array) $middleware);
    }

    public function head(string $url, callable|array $action, array|string $middleware = []): self
    {
        return self::add('HEAD', $url, $action, (array) $middleware);
    }

    // Named routes
    public function name(string $name): self
    {
        if ($name !== '' && $this->lastRoute !== null) {
            $this->namedRoutes[$name] = $this->lastRoute;
        }
        return $this;
    }

    // Route groups
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $this->currentGroup;

        $prefix = trim($this->currentGroup['prefix'] . '/' . trim($attributes['prefix'] ?? '', '/'), '/');
        $middleware = array_merge(
            $this->currentGroup['middleware'],
            (array)($attributes['middleware'] ?? [])
        );
        $namespace = $this->currentGroup['namespace'] .
            (isset($attributes['namespace']) ? '\\' . trim($attributes['namespace'], '\\') : '');

        $this->appliedGroup[] = $this->currentGroup = compact('prefix', 'middleware', 'namespace');

        $callback();

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
            $route = str_replace('{' . $key . '}', (string)$value, $route);
        }

        return self::normalizePath($route);
    }

    // Resource routing
    public function resource(string $name, string $controller, array $options = []): void
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
    public function view(string $uri, string $view, array $data = []): self
    {
        return self::get($uri, fn() => view($view, $data));
    }

    // Redirect routes
    public function redirect(string $from, string $to, int $status = 302): self
    {
        return self::get($from, fn() => header("Location: $to", true, $status));
    }

    public function fallback(callable|array $callback): void
    {
        $this->fallback = $callback;
    }

    private function execute(callable|array $action, mixed $params)
    {
        if (is_array($action) && count($action) === 2) {
            $instance = InstanceManager::getInstance('container')->make($action[0]);
            $action = [$instance, $action[1]];
        }

        if (is_callable($action)) {
            return callFuncWithParams($action, $params, true, true);
        }
        return Debugger::showErrorPage(500, 'Invalid callback');
    }

    public function dispatch(Request $request)
    {
        $method = $request->method();
        $requestUri = trim($request->uri(), '/');
        $segments = $requestUri === '' ? [] : explode('/', $requestUri);

        // Direct lookup in method-specific radix tree - no cache needed!
        $root = $this->roots[$method] ?? null;
        // dd($root);

        if ($root === null) {
            return self::handleNotFound($request);
        }

        $result = self::searchNode($root, $segments);

        if ($result !== null) {
            $node = $result['node'];
            $params = $result['params'];

            if (isset($node->handlers[$method])) {
                // Execute plugins
                foreach ($this->plugins as $fn) {
                    $fn();
                }

                $middleware = array_merge($this->globalMiddleware, $node->middleware);
                $destination = fn() => self::execute(
                    $node->handlers[$method],
                    array_combine($node->paramKeys, $params)
                );

                return self::pipeline($request, $middleware, $destination);
            }
        }

        return self::handleNotFound($request);
    }

    private function handleNotFound(Request $request)
    {
        if (isset($this->fallback)) {
            return self::pipeline($request, [], fn() => self::execute($this->fallback, []));
        }
        return Debugger::showErrorPage(404, 'Not found');
    }

    // Debug methods
    public function debugTree(?string $method = 'GET', ?RadixNode $node = null, int $indent = 0): void
    {
        if ($method !== null) {
            $node = $this->roots[$method] ?? $this->roots['GET'];
        }
        
        $node = $node ?? $this->roots['GET'];
        $indentStr = str_repeat(' ', $indent * 2);

        echo $indentStr . "Node: " . $node->path .
            ($node->isParam ? ' (param)' : '') .
            ($node->isLeaf ? ' [LEAF]' : '') .
            (!empty($node->handlers) ? ' [HANDLERS: ' . implode(',', array_keys($node->handlers)) . ']' : '') . "\n";

        foreach ($node->children as $child) {
            self::debugTree(null, $child, $indent + 1);
        }
    }

    public function routeList(): array
    {
        return $this->routeList;
    }

    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }
}
