<?php

namespace App\Support\Facades;

use App\Support\Facades\Facade;

/**
 * @method static \Experimental\App\Foundation\Database\QueryBuilder table($name)
 * @mixin \Experimental\App\Foundation\Database\QueryBuilder
 * @extends Facade<\Experimental\App\Foundation\Database\QueryBuilder>
 * 
 * @depends App\Support\Facades\Facade
 */
class DB extends Facade
{
    protected static function getFacadeAccessor(): string|object
    {
        return \Experimental\App\Foundation\Database\QueryBuilder::class;
    }
}
