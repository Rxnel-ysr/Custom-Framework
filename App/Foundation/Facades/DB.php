<?php

namespace App\Support\Facades;

use App\Support\Facades\Facade;
use Dep;

/**
 * @method static \App\Foundation\Database\QueryBuilder table(string $name)
 * @mixin \App\Foundation\Database\QueryBuilder
 * @extends Facade<\App\Foundation\Database\QueryBuilder>
 * 
 * @depends App\Support\Facades\Facade
*/
#[Dep(Facade::class)]
class DB extends Facade
{
    protected static function getFacadeAccessor(): string|object
    {
        return \App\Foundation\Database\QueryBuilder::class;
    }
    
}
