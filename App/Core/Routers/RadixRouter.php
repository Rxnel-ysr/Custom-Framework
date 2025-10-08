<?php

namespace App\Foundation\Http;

use App\Debug\Debugger;
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
}

/**
 * Radix Router
 */
class Route extends RouterBase implements RouterInterface
{
    public static string $name = 'RadixRouter';
    private static RadixNode $root;
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
        self::$root = new RadixNode();
        self::$dirRoot = $root ?? $_SERVER['DOCUMENT_ROOT'] ?? null;
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
            self::$lastRoute['middleware'] = array_merge(
                self::$lastRoute['middleware'] ?? [],
                $middleware
            );

            // Update the node with the new middleware
            $url = trim(self::$lastRoute['url'], '/');
            $segments = explode('/', $url);
            $result = self::searchNode(self::$root, $segments);

            if ($result !== null) {
                $result['node']->middleware = array_merge(
                    $result['node']->middleware,
                    $middleware
                );
            }
        }

        return new self();
    }

    private static function insertNode(RadixNode $node, array $segments, string $method, callable|array $action, array $middleware, array $paramKeys): RadixNode
    {
        if (empty($segments)) {
            $node->handlers[$method] = $action;
            $node->middleware = $middleware; // Set middleware for this node
            $node->paramKeys = $paramKeys;
            return $node;
        }

        $segment = $segments[0];
        $remainingSegments = array_slice($segments, 1);

        // Check for parameter segment
        if (preg_match('/\{([a-zA-Z0-9_]+)\}/', $segment, $matches)) {
            $paramKeys[] = $matches[1];
            $segment = '{}';
            $isParam = true;
        } else {
            $isParam = false;
        }

        // Look for a matching child
        foreach ($node->children as $child) {
            // Exact match or parameter match
            if ($child->path === $segment || $child->isParam) {
                return self::insertNode($child, $remainingSegments, $method, $action, $middleware, $paramKeys);
            }

            // Partial match - split the node
            $commonPrefix = self::longestCommonPrefix($child->path, $segment);
            if ($commonPrefix !== '') {
                // Split the existing node
                $splitNode = new RadixNode();
                $splitNode->path = substr($child->path, strlen($commonPrefix));
                $splitNode->children = $child->children;
                $splitNode->handlers = $child->handlers;
                $splitNode->middleware = $child->middleware;
                $splitNode->paramKeys = $child->paramKeys;
                $splitNode->isParam = $child->isParam;

                // Reset the existing node
                $child->path = $commonPrefix;
                $child->children = [$splitNode];
                $child->handlers = [];
                $child->isParam = false;

                // If we didn't match the full segment, add the remaining part as a new child
                if ($commonPrefix !== $segment) {
                    $newNode = new RadixNode();
                    $newNode->path = substr($segment, strlen($commonPrefix));
                    $newNode->isParam = $isParam;
                    $child->children[] = $newNode;
                    return self::insertNode($newNode, $remainingSegments, $method, $action, $middleware, $paramKeys);
                }

                return self::insertNode($child, $remainingSegments, $method, $action, $middleware, $paramKeys);
            }
        }

        // No matching child found, create a new one
        $newNode = new RadixNode();
        $newNode->path = $segment;
        $newNode->isParam = $isParam;
        $node->children[] = $newNode;
        return self::insertNode($newNode, $remainingSegments, $method, $action, $middleware, $paramKeys);
    }

    private static function longestCommonPrefix(string $a, string $b): string
    {
        $len = min(strlen($a), strlen($b));
        $result = '';
        for ($i = 0; $i < $len; $i++) {
            if ($a[$i] !== $b[$i]) {
                break;
            }
            $result .= $a[$i];
        }
        return $result;
    }

    public static function add(string $method, string $url, callable|array $action, array $middleware = [])
    {
        self::$lastRoute = null;
        // Apply group prefix
        $url = trim(self::$currentGroup['prefix'] . self::normalizePath($url), '/');
        self::$routeList[$method][] = '/' . $url;

        // Apply group middleware
        $middleware = array_merge(self::$currentGroup['middleware'], $middleware);

        // Apply namespace to controller actions
        if (is_array($action) && !empty(self::$currentGroup['namespace'])) {
            $action[0] = self::$currentGroup['namespace'] . '\\' . ltrim($action[0], '\\');
        }

        $segments = explode('/', trim($url, '/'));
        $paramKeys = [];

        self::insertNode(self::$root, $segments, $method, $action, $middleware, $paramKeys);

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
            if (empty($node->handlers)) {
                return null; // No handlers for this node
            }
            return [
                'node' => $node,
                'params' => $params
            ];
        }

        $segment = $segments[0];
        $remainingSegments = array_slice($segments, 1);

        foreach ($node->children as $child) {
            if ($child->isParam) {
                // Parameter node matches any segment
                $newParams = $params;
                $newParams[] = $segment;
                $result = self::searchNode($child, $remainingSegments, $newParams);
                if ($result !== null) {
                    return $result;
                }
            } elseif (str_starts_with($segment, $child->path)) {
                // Exact match or prefix match
                if ($segment === $child->path) {
                    // Exact match - proceed with remaining segments
                    $result = self::searchNode($child, $remainingSegments, $params);
                    if ($result !== null) {
                        return $result;
                    }
                } else {
                    // Partial match - check if the remaining part matches any child
                    $remainingPart = substr($segment, strlen($child->path));
                    $newSegments = array_merge([$remainingPart], $remainingSegments);
                    $result = self::searchNode($child, $newSegments, $params);
                    if ($result !== null) {
                        return $result;
                    }
                }
            }
        }

        return null;
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
            'middleware' => array_merge(self::$currentGroup['middleware'], (is_array($attributes['middleware']) ? $attributes['middleware'] : [$attributes['middleware']]) ?? []),
            'namespace' => self::$currentGroup['namespace'] . '\\' . trim($attributes['namespace'] ?? '', '\\'),
        ];


        // Execute the callback
        call_user_func($callback);

        // Restore previous group attributes
        self::$currentGroup  = array_pop(self::$groupStack);
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

    public static function dispatch(Request $request)
    {
        $requestUri = trim($request->uri(), '/');
        $method = self::getRequestMethod();

        $segments = explode('/', $requestUri);
        $result = self::searchNode(self::$root, $segments);

        if ($result === null) {
            if (isset(self::$fallback)) {
                return self::execute(self::$fallback, []);
            }
            return Debugger::showErrorPage(404, 'Not found');
        }

        $node = $result['node'];
        $params = $result['params'];

        if (isset($node->handlers[$method])) {
            foreach (self::$plugins as $fn) {
                $fn();
            }

            $middleware = array_merge(self::$globalMiddleware, $node->middleware ?? []);

            $destination = fn() => self::execute($node->handlers[$method], array_combine($node->paramKeys, $params));

            return self::pipeline($request, $middleware, $destination);
            // return self::execute($node->handlers[$method], array_combine($node->paramKeys, $params));
        }

        if (isset(self::$fallback)) {
            return self::execute(self::$fallback, []);
        }

        return Debugger::showErrorPage(405, 'Method not allowed');
    }

    public static function debugTree(?RadixNode $node = null, int $indent = 0)
    {
        $node = $node ?? self::$root;
        $indentStr = str_repeat(' ', $indent * 2);

        echo $indentStr . "Node: " . $node->path .
            ($node->isParam ? ' (param)' : '') .
            (!empty($node->handlers) ? ' [has handlers]' : '') . "\n";

        foreach ($node->children as $child) {
            self::debugTree($child, $indent + 1);
        }
    }

    public static function debugRoutes()
    {
        $list = [];

        foreach (self::$routeList as $method => $routes) {
            foreach ($routes as $route) {
                $list[$method][] = $route;
            }
        }

        return $list;
    }
}
