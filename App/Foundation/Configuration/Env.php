<?php

namespace App\Foundation\Configuration;

use App\Foundation\Exceptions\Framework\LowLevelException;

class EnvException extends LowLevelException {}

class Env
{
    public static function load(string $path): void
    {
        if (!is_readable($path)) {
            throw new EnvException("File ". basename($path) . " was not readable.");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            [$key, $value] = self::parseLine($line);
            self::setEnvironment($key, $value);
        }
    }

    protected static function stripComment(string $line): string
    {
        return trim(strtok($line, '#'));
    }

    protected static function parseLine(string $line): array
    {
        $parts = explode('=', self::stripComment($line), 2);
        if (count($parts) !== 2) {
            return [$parts[0], null];
        }

        $key = trim($parts[0]);
        $value = self::parseValue(trim($parts[1]));

        return [$key, $value];
    }

    protected static function parseValue(string $value)
    {
        if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
            $value = $matches[2];
        }

        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => is_numeric($value) ? (strpos($value, '.') !== false ? (float)$value : (int)$value) : $value,
        };
    }

    protected static function setEnvironment(?string $key, mixed $value): void
    {
        if (is_null($key)) {
            return;
        }
        $stringValue = is_bool($value) ? ($value ? '1' : '0')
            : (is_null($value) ? 'null'
                : (string)$value);

        putenv("$key=$stringValue");
        $_ENV[$key] = $value;
    }
}
