<?php

namespace App\Foundation\Http;

use App\Foundation\Exceptions\Framework\LowLevelException;

class MiddlewareException extends LowLevelException {}

class Middleware
{
    private array $aliases = [];
    protected static HttpHeaders $header;

    public static function setup()
    {
        self::$header = withHeader();
    }
    
    public function __call($name, $arguments)
    {
        return method_exists(self::$header, $name) ? self::$header->$name(...$arguments) : throw new MiddlewareException("Call to undefined method ". self::class . '::'. $name);
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

        $args = explode(',', $args ?? '');
        $instances = isset($this->aliases[$realAlias]) ? new $this->aliases[$realAlias] : (class_exists($realAlias) ? new $realAlias : null);
        if (!$instances instanceof Middleware) {
            $self = self::class;
            throw new MiddlewareException("{$realAlias} was not child of {$self}");
        }
        return ['instance' => $instances, 'parameters' => $args];
    }
}
