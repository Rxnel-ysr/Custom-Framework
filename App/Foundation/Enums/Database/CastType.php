<?php

namespace App\Foundation\Enums\Database;

use App\Foundation\Support\Time\Time;

enum CastType: string
{
    case STRING = 'string';
    case INTEGER = 'int';
    case BOOLEAN = 'bool';
    case FLOAT = 'float';
    case JSON = 'json';

    case DATETIME = 'datetime';
    case ARRAY = 'array';

    public function apply(mixed $value, ?string $param = null): mixed
    {
        return match ($this) {
            self::STRING => (string) $value,
            self::INTEGER => (int) $value,
            self::BOOLEAN => (bool) $value,
            self::FLOAT => (float) $value,
            self::JSON => json_decode($value, null, 512, JSON_THROW_ON_ERROR),
            self::DATETIME => $this->toDateTime($value, $param),
            self::ARRAY => $this->toArray($value, $param),
        };
    }

    private function toDateTime(mixed $value, ?string $param): Time
    {
        if (!$param) {
            return Time::from($value, Time::MYSQL_DATETIME_FORMAT);
        }
        return Time::from($value, Time::MYSQL_DATETIME_FORMAT)
            ->formatWhenString($param);
    }

    private function toArray(mixed $value, ?string $separator): array
    {
        if (is_array($value)) {
            return $value;
        }

        $separator ??= ',';
        return explode($separator, (string)$value);
    }

    /**
     * Undocumented function
     *
     * @param string $definition
     * @return array{0: self, 1: ?string}
     */
    public static function parse(string $definition): array
    {

        $position = strpos($definition, ':');

        [$baseType, $param] = $position !== false
            ? [substr($definition, 0, $position), substr($definition, $position + 1)]
            : [$definition, null];

        return [static::from($baseType), $param];
    }
}







