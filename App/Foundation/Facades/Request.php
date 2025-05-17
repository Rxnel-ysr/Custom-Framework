<?php

namespace App\Support\Facades;

use App\Support\Facades\Facade;

/**
 * @method static getBearerToken
 * @method static array all()
 * @method static array only(array $keys)
 * @mixin \App\Foundation\Http\Request::class
 */
class Request extends Facade
{
    protected static function getFacadeAccessor(): string|object
    {
        return \App\Foundation\Http\Request::class;
    }
}
