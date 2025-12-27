<?php

namespace App\Support\Facades;

use App\Support\Facades\Facade;
use Dep;

#[Dep(Facade::class)]
/**
 * @method static void register(string $name, callable $func) Register Rx directive on runtime
 * 
 * @depends App\Support\Facades\Facade
 * 
 */
class Rx extends Facade
{
    protected static function getFacadeAccessor(): string|object
    {
        return \App\Foundation\Compiler\Compile::class;
    }
}
