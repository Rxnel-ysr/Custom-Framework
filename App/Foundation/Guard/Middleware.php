<?php

namespace App\Foundation\Http;

use Exception;

class MiddlewareException extends Exception {}

class Middleware
{
    private array $aliases = [];

    public function aliases($aliases = []): self
    {
        $this->aliases = $aliases;
        return $this;
    }

    /**
     * Undocumented function
     *
     * @param string $alias
     * @return array{instance: object, parameters: array}
     */
    public function resolveAlias(string $alias): array
    {
        [$realAlias, $args] = strpos($alias, ':') !== false ?  explode(':', $alias, 2) : [$alias, null];
        // dd($realAlias, $args);

        $args = explode(',', $args ?? '');
        $instances = isset($this->aliases[$realAlias]) ? new $this->aliases[$realAlias] : (class_exists($realAlias) ? new $realAlias : null);
        if(!$instances instanceof Middleware){
            throw new MiddlewareException("{$realAlias} was not child of App\Foundation\Http\Middleware");
        }
        return ['instance' => $instances, 'parameters' => $args];
    }
}
