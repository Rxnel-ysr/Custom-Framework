<?php

namespace App\Support\Facades;

use App\Foundation\Exceptions\Framework\Primitive\InvalidArgumentException;
use App\Support\Facades\Facade;
use Dep;

/**
 * Fluent API for route clases
 * 
 * @method static string getName() Return currently in use route name
 * @method static self init(?string $root = null, array $plugins = [])
 * @method static string getRequestMethod()
 * @method static self middleware(string|array $middleware, ?callable $callback = null)
 * @method static self add(string $method, string $url, callable|array $action, array $middleware = [])
 * @method static self get(string $url, callable|array $action, array|string $middleware = [])
 * @method static self post(string $url, callable|array $action, array|string $middleware = [])
 * @method static self patch(string $url, callable|array $action, array|string $middleware = [])
 * @method static self put(string $url, callable|array $action, array|string $middleware = [])
 * @method static self delete(string $url, callable|array $action, array|string $middleware = [])
 * @method static self head(string $url, callable|array $action, array|string $middleware = [])
 * @method static self options(string $url, callable|array $action, array|string $middleware = [])
 * @method static self name(string $name)
 * @method static void group(array $attributes, callable $callback)
 * @method static string route(string $name, array $parameters = [])
 * @method static void resource(string $name, string $controller, array $options = [])
 * @method static self view(string $uri, string $view, array $data = [])
 * @method static self redirect(string $from, string $to, int $status = 302)
 * @method static void fallback(callable|array $callback)
 * @method static mixed dispatch(Request $request)
 * @method static array routeList()
 * @method static array getNamedRoutes()
 * @method static mixed parameter(string $parameter)
 * @method static void handleCORS(Request $request)
 */
#[Dep(Facade::class)]
class Route extends Facade
{
    protected static function getFacadeAccessor(): string|object
    {
        $router = DI::get('appConfig')['router'];
        ini_set('default_mimetype', '');

        $nonce = ''; 

        if (env('CSP')) {
            $nonce = base64_encode(random_bytes(16));
            withHeader()->set('Content-Security-Policy', "default-src 'self'; media-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'nonce-$nonce' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;");
        }

        DI::bind('nonce', fn() => $nonce);

        $choices = array_fill_keys($router['choices'], true);
        $selected = $router['selected'] ?? null;

        if (empty($selected) || !isset($choices[$selected])) {
            throw new InvalidArgumentException("Invalid router: [$selected]");
        }

        $alias = $router['alias'][$selected] ?? null;
        if (!$alias || !class_exists($alias)) {
            throw new InvalidArgumentException("Router alias [$selected] not found or invalid.");
        }
        return $alias;
    }
}
