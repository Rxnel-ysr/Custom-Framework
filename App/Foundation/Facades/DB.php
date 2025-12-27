<?php

namespace App\Support\Facades;

use App\Support\Facades\Facade;
use Dep;

/**
 * @method static \Experimental\App\Foundation\Database\QueryBuilder table($name)
 * @mixin \Experimental\App\Foundation\Database\QueryBuilder
 * @extends Facade<\Experimental\App\Foundation\Database\QueryBuilder>
 * 
 * @depends App\Support\Facades\Facade
*/
#[Dep(Facade::class)]
class DB extends Facade
{
    protected static function getFacadeAccessor(): string|object
    {
        return \Experimental_V2\App\Foundation\Database\QueryBuilder::class;
    }
}
