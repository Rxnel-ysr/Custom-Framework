<?php

namespace App\Support\Facades;

use App\Support\Facades\Facade;

/**
 * @mixin class App\Foundation\Database\QueryBuilder
 * @method static mixed __callStatic(string $method, array $args)
 * @method static mixed __call(string $method, array $args)
 */
class DB extends Facade
{
    protected static function getFacadeAccessor(): string|object
    {
        return \App\EXPE\Foundation\Database\QueryBuilder::class;
    }
}
