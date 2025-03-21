<?php
namespace App\Utils\Http;

abstract class Controller{
    public static function getServerRequestUri(){
        return $_SERVER['REQUEST_URI'];
    }
}