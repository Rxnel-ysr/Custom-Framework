<?php

class TestErrHandler
{
    private static $isEven = false;
    private static $num = 0;

    public static function test()
    {
        echo 'it work' . PHP_EOL;
    }

    public static function testError(...$name)
    {
        print_r($name);
        throw new Exception('It work');
        return $name;
    }

    public static function testShutdown(...$name)
    {
        print_r($name);
        while (true) {
            echo 'Forever ';
        }
    }

    public static function testSomeError()
    {
        self::$num++;

        if (self::$num % 2 == 0) {
            throw new Exception(self::$num . ' Its even!');
        } else {
            echo '<br>' . self::$num . ' Its Odd!' . PHP_EOL;
        }
    }

    public static function testFunc($name)
    {
        echo "It work $name";
        return true;
    }
}
