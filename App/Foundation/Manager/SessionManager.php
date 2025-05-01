<?php

namespace App\Foundation\Manager;

class SessionManager
{
    private static bool $session_active;

    public static function sessionInit(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
            self::$session_active = true;
            error_log('Session-manager: Session started.');
            return true;
        }

        self::$session_active = true; // Ensure this is set even if session was already active
        error_log('Session-manager: Session reused.');
        return true;
    }


    public static function get(string $key)
    {
        if (!self::$session_active) {
            error_log("Session-manager: Requested [{$key}] but session is inactive.");
            return null;
        }

        $value = $_SESSION[$key] ?? null;
        error_log("Session-manager: Requested [{$key}] result [" . json_encode($value, JSON_UNESCAPED_SLASHES) . "]");

        return $value;
    }


    public static function set(string $key, mixed $value): bool
    {
        if (!self::$session_active) {
            error_log("Session-manager: Failed to set key [{$key}] because session is inactive.");
            return false;
        }

        $_SESSION[$key] = $value;
        error_log("Session-manager: Set key [{$key}] with value [" . json_encode($value, JSON_UNESCAPED_SLASHES) . "]");

        return true;
    }

    public static function forget(string $key): bool
    {
        if (!self::$session_active) {
            error_log("Session-manager: Failed to set key [{$key}] because session is inactive.");
            return false;
        }

        unset($_SESSION[$key]);
        error_log("Session-manager: Forgetting key [{$key}]");

        return true;
    }


    // public static function ifSessionActive(callable $action, mixed ...$args)
    // {
    //     return callFuncWithParams($action, true, getBoolEnv('AUTO_LOAD_CLASS_DEPENDENCIES', ClassManager::getAttr()['auto_resolve']), ...$args);
    // }
}
