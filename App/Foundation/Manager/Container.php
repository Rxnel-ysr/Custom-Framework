<?php

namespace App\Foundation\Manager;

class Container
{
    protected $bindings = [];

    public function bind($key, callable $resolver)
    {
        $this->bindings[$key] = $resolver;
    }

    public function get($key)
    {
        return $this->bindings[$key]();
    }
}
