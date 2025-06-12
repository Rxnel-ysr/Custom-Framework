<?php

namespace App\Foundation\Authentication;

(session_status() !== PHP_SESSION_ACTIVE) && session_start();

class Auth
{
    protected string $type;

    public static function check(){

    }
}
