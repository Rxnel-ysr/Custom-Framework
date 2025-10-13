<?php

namespace App\Test;

/**
 * A something
 * 
 * @init App\Test\testClassWithInitAndDeps::init
 */
class testClassWithInitAndDeps
{
    private static bool $state = false;

    public static function init()
    {
        self::$state = true;
    }

    public static function sayHi(){
        if(self::$state){
            echo 'Hello, I am alive!';
        }else{
            echo 'Hello, ...I am... dead';
        }
    }
}

