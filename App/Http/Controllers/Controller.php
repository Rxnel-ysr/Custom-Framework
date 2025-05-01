<?php

namespace App\Foundation\Http;

abstract class Controller
{
    public static function getServerRequestUri()
    {
        return $_SERVER['REQUEST_URI'];
    }
}
