<?php

interface RouterInterface
{
    /** Add new route */
    public static function add(string $method, string $url, callable|array $action, array $middleware = []);
    /** Dispatch router */
    public static function dispatch(string $url);
    /** Add middleware */
    public static function middleware(string|array $middleware);
    /** Define fallback if no match */
    public static function fallback(callable|array $callback);
}
