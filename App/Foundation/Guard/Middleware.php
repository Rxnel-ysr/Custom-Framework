<?php

namespace App\Foundation\Http;

use Exception;

class MiddlewareException extends Exception {}

class Middleware
{
    private array $aliases = [];
    protected HttpHeaders $header;

    public function __construct()
    {
        $this->header = withHeader();
    }

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
        [$realAlias, $args] = array_pad(explode(':', $alias, 2), 2, null);
        // dd($realAlias, $args);

        $args = explode(',', $args ?? '');
        // print_rpre($this->aliases);
        // die;
        // die($this->aliases[$realAlias]);
        $instances = isset($this->aliases[$realAlias]) ? new $this->aliases[$realAlias] : (class_exists($realAlias) ? new $realAlias : null);
        if (!$instances instanceof Middleware) {
            $self = self::class;
            throw new MiddlewareException("{$realAlias} was not child of {$self}");
        }
        return ['instance' => $instances, 'parameters' => $args];
    }
}
