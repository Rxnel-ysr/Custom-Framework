<?php

namespace App\Support\Facades\Nop;

use App\Foundation\Model as BaseModel;
use App\Foundation\Manager\InstanceManager;

class Model
{

    private static $instance;

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = InstanceManager::getInstance(BaseModel::class);
        }

        return self::$instance;
    }

    public static function __callStatic($method, $arguments)
    {
        self::getInstance();

        $result = self::$instance->$method(...$arguments);

        return $result === self::$instance ? self::$instance : $result;
    }
}
