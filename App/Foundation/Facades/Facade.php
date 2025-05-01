<?php

namespace App\Support\Facades;

use App\Foundation\Manager\InstanceManager;
use App\EXPE\Foundation\Manager\ClassManager;

class Facade
{
    private static $instances = [];

    /**
     * Get the instance of a class
     *
     * @param string $class Class name to resolve
     * @return object
     */
    protected static function getInstance($class)
    {
        // Prevent creating a new instance if it's already been instantiated
        if (!isset(self::$instances['real_' . $class])) {
            self::$instances['real_' . $class] = InstanceManager::getInstance($class);
        }

        // Avoid class aliasing if it already exists
        if (!class_exists('real_' . $class)) {
            class_alias($class, 'real_' . $class);
        }

        // Return the stored instance
        return self::$instances['real_' . $class];
    }
}
