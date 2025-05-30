<?php

namespace App\Support\Facades;

use App\Support\Facades\Facade;

/**
 * @method static \App\EXPE\Foundation\Database\QueryBuilder table($name)
 * @mixin \App\EXPE\Foundation\Database\QueryBuilder
 * @extends Facade<\App\EXPE\Foundation\Database\QueryBuilder>
 */
class DB extends Facade
{
    protected static function getFacadeAccessor(): string|object
    {
        return \App\EXPE\Foundation\Database\QueryBuilder::class;
    }
}
