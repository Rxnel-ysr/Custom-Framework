<?php

namespace App\Support\Facades;

use App\Support\Facades\Facade;
use BadMethodCallException;

/**
 * @method self status(int $code)
 * @method self header(string $key, string $value)
 * @method void json(mixed $value)
 * @method void html(string $content)
 * @method void text(string $content)
 * @method void redirect(string $url, int $code = 302)
 * @method void download(string $filePath, string $fileName = null)
 * @method void make(string $content = '', int $status = 200, array $headers = [])
 */
class Response extends Facade
{
    public static function __callStatic($name, $arguments)
    {
        $instance = self::getInstance(\App\Foundation\Http\Response::class);
        if (method_exists($instance, $name)) {
            return call_user_func([$instance, $name], ...$arguments);
        }
        throw new BadMethodCallException('Method ' . $name . ' is not available on  App\Support\Facades\Response::class');
    }
}
