<?php

namespace App\Foundation\Configuration;

use ArrayAccess;

class Config implements ArrayAccess
{
    public function __construct(protected string $cachePath, protected array $configs = []) {}

    public function readCache()
    {
        $cfg = require $this->cachePath;
        $this->configs = is_array($cfg) ? $cfg : [];
        return $this;
    }

    public function cached(array $except = [])
    {
        $dirname = dirname($this->cachePath);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0777, true);
        }

        $tobeSaved = empty($except) ? $this->configs : array_filter($this->configs, fn($key) => !in_array($key, $except), ARRAY_FILTER_USE_KEY);;

        file_put_contents($this->cachePath, <<<PHP
        <?php
        return 
        PHP . var_export($tobeSaved, true) . ";\n");
        return $this;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->configs[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
            return $this->configs[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->configs[] = $value;
        } else {
            $this->configs[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        if (isset($this->configs[$offset])) {
            unset($this->configs[$offset]);
        }
    }
}
