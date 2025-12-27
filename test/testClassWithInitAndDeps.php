<?php

namespace App\Test;

use App\Foundation\Support\Collection;
use App\Foundation\Support\Str;
use App\Foundation\Traits\Macroable;
use Dep;
use Boot;

#[Dep(Str::class)]
#[Dep(Collection::class)]
#[Dep(Macroable::class)]
#[Boot([testClassWithInitAndDeps::class, 'init'])]
#[Boot('App\\Test\\say')]
/**
 * A test class with deps and init
 * @depends App\Foundation\Support\Str
 * @depends App\Foundation\Support\Collection
 * 
 * @boot App\Test\say
 * @boot self::init
 */
class testClassWithInitAndDeps
{
    use Macroable;
    
    private static bool $state = false;

    public static function init()
    {
        self::$state = true;
    }

    public static function sayHi()
    {
        if (self::$state) {
            echo 'Hello, I am alive!';
        } else {
            echo 'Hello, ...I am... dead';
        }

        if (class_exists('App\Foundation\Support\Str', false)) {
            echo "\nAnd this class is exist!";
        } else {
            echo "\nAnd this class is... not exist";
        }
    }
}

function say()
{
    echo "hi\n";
}
