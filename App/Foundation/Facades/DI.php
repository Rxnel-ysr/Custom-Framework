<?php

namespace App\Support\Facades;

use App\Support\Facades\Facade;

/**
 * A facade class of Container class
 * @method static void bind(string $key, callable $resolver)
 * @method static mixed get(string $key)
 * 
 * @depends App\Support\Facades\Facade
 */
class DI extends Facade
{
    protected static function getFacadeAccessor(): string|object
    {
        return \App\Foundation\Manager\Container::class;
    }
}
