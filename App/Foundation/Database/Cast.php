<?php

namespace App\Foundation\Database;

use App\Foundation\Enums\Database\CastType;
use InvalidArgumentException;

class Cast
{
    private CastType $type;
    private array $args = [];

    public function __construct(CastType $type, mixed ...$args)
    {
        $this->type = $type;
        $this->args = $args;
    }

    public static function from(CastType $type, mixed ...$args): self
    {
        return new self($type, ...$args);
    }

    public function getType(): CastType
    {
        return $this->type;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public function apply(mixed $value): mixed
    {
        // Convert args array to param string if needed
        $param = $this->argsToString();

        return $this->type->apply($value, $param);
    }

    public function __toString(): string
    {
        $base = $this->type->value;
        if (empty($this->args)) {
            return $base;
        }

        return $base . ':' . $this->argsToString();
    }

    private function argsToString(): string
    {
        return implode(':', array_map('strval', $this->args));
    }

    public static function parse(string $definition): Cast
    {
        if (!str_contains($definition, ':')) {
            $type = CastType::from($definition);
            return new self($type);
        }

        $parts = explode(':', $definition);
        $typeStr = array_shift($parts);

        try {
            $type = CastType::from($typeStr);
        } catch (\ValueError $e) {
            throw new InvalidArgumentException("Unknown cast type: $typeStr");
        }

        return new self($type, ...$parts);
    }
}
