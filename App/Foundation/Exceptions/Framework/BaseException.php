<?php

namespace App\Foundation\Exceptions\Framework;

use App\Foundation\Http\Response;
use Closure;
use Exception;
use ReflectionFunction;
use ReflectionUnionType;

class BaseException extends Exception
{
    /**
     * @var list<class-string, Closure>
     */
    final protected $renderer = [];

    final public function render(Closure $handler): static
    {
        $reflect = new ReflectionFunction($handler);

        $param = $reflect->getParameters()[0];

        $type = $param->getType();

        if($type instanceof ReflectionUnionType){
            throw HighLevelException::create("Expected type of App\\Foundation\\Exceptions\\Framework\\Exception, found union.");
        }

        $name = $type->getName();

        if ($type->isBuiltin() || !(new $name()) instanceof Exception) {
            throw HighLevelException::create("Expected type of App\\Foundation\\Exceptions\\Framework\\Exception, found " . $param->getType()->getName());
        }

        $this->renderer[$name] = $handler;

        return $this;
    }

    final function has(string $classname): bool
    {
        return isset($this->renderer[$classname]);
    }

    final public function throw(string $classname, ...$parameters): Response
    {
        return callFuncWithParams($this->renderer[$classname], $parameters, true, true);
    }

    final public static function create(...$parameters): static
    {
        return new static(...$parameters);
    }
}
