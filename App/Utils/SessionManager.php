<?php

namespace App\Utils\Manager;

class SessionManager
{
    private static bool $session_active;

    public static function sessionInit()
    {
        if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
            self::$session_active  = true;
        }
    }

    public static function get(string $key)
    {
        return self::$session_active ? $_SESSION[$key] ?? null : null;
    }
    
    public static function set(string $key, mixed $value){
        return self::$session_active ? $_SESSION[$key] = $value : false;
    }

    // public static function ifSessionActive(callable $action, mixed ...$args)
    // {
    //     return callFuncWithParams($action, true, getBoolEnv('AUTO_LOAD_CLASS_DEPENDENCIES', ClassManager::getAttr()['auto_resolve']), ...$args);
    // }
}
