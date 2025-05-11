<?php

namespace App\Support\Facades;

use App\Support\Facades\Facade;

/**
 * @method getBearerToken
 */
class Request extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Foundation\Http\Request::class;
    }
}
