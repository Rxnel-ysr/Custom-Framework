<?php

namespace App\Support\Facades;

use App\Support\Facades\Facade;

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
    protected static function getFacadeAccessor()
    {
        return \App\Foundation\Http\Response::class;
    }
}
