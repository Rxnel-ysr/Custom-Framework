<?php

namespace App\Http\Controllers;

abstract class Controller
{
    public static function getServerRequestUri()
    {
        return $_SERVER['REQUEST_URI'];
    }
}
