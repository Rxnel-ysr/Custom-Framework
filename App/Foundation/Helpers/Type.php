<?php
class Type
{
    // Option constants for better UX
    public const CONVERT = [
        'STRICT' => 1,         // Throw exceptions on conversion failures
        'LENIENT' => 2,         // Return null on conversion failures
        'AUTO_CORRECT' => 4,    // Attempt to fix common issues
    ];

    public const JSON = [
        'PRETTY' => JSON_PRETTY_PRINT,
        'ESCAPE_SLASHES' => JSON_UNESCAPED_SLASHES,
        'UNICODE' => JSON_UNESCAPED_UNICODE,
    ];

    public const CSV = [
        'HEADERS' => 1,         // Include headers in output
        'NO_HEADERS' => 2,      // Skip headers
        'ESCAPE_FORMULAS' => 4, // Add tab prefix to prevent CSV injection
    ];

    public const NORMALIZE = [
        'TRIM' => 1,
        'EMPTY_TO_NULL' => 2,
        'LOWERCASE' => 4,
        'UPPERCASE' => 8,
        'COLLAPSE_WHITESPACE' => 16,
    ];

    public const DATETIME = [
        'ISO8601' => 'Y-m-d\TH:i:sP',
        'MYSQL' => 'Y-m-d H:i:s',
        'SHORT_DATE' => 'Y-m-d',
    ];

    // Core type checks
    public static function getType($var): string
    {
        $type = gettype($var);

        if ($type === 'double') return 'float';
        if ($type === 'NULL') return 'null';
        if ($type === 'resource (closed)') return 'closed_resource';

        // Special object detection
        if ($type === 'object') {
            if ($var instanceof DateTimeInterface) return 'datetime';
            if ($var instanceof Closure) return 'closure';
            if ($var instanceof ArrayAccess) return 'array_access';
            return 'object:' . get_class($var);
        }

        return $type;
    }

    // Advanced conversion methods
    public static function toArray($value): array
    {
        if (is_array($value)) return $value;
        if ($value instanceof Traversable) return iterator_to_array($value);
        if (is_object($value)) return get_object_vars($value);
        if (is_scalar($value)) return [$value];
        if (is_null($value)) return [];

        throw new RuntimeException("Cannot convert type " . gettype($value) . " to array");
    }


    public static function toDateTime($value, ?string $format = null): DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (is_numeric($value)) {
            $dt = new DateTime();
            $dt->setTimestamp((int)$value);
            return $dt;
        }

        if (is_string($value)) {
            try {
                return $format ? DateTime::createFromFormat($format, $value) : new DateTime($value);
            } catch (Exception $e) {
                throw new RuntimeException("Failed to parse datetime: " . $e->getMessage());
            }
        }

        throw new RuntimeException("Cannot convert type " . gettype($value) . " to DateTime");
    }

    public static function toInt($value): int
    {
        return (int)$value;
    }

    public static function toBool($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return $value != 0;
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
        }
        return (bool)$value;
    }

    // Special validation methods
    public static function isJson($value): bool
    {
        if (!is_string($value)) return false;

        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public static function isDateTime($value): bool
    {
        return $value instanceof DateTimeInterface
            || (is_string($value) && strtotime($value) !== false)
            || is_numeric($value);
    }

    public static function isTimestamp($value): bool
    {
        return is_numeric($value)
            && (int)$value == $value
            && $value <= PHP_INT_MAX
            && $value >= ~PHP_INT_MAX;
    }

    public static function isBinary($value): bool
    {
        return is_string($value) && !ctype_print($value);
    }

    // Advanced data processing
    public static function normalize($value, array $options = [])
    {
        $type = self::getType($value);

        switch ($type) {
            case 'string':
                $trimmed = trim($value);
                if ($trimmed === '' && ($options['empty_to_null'] ?? false)) {
                    return null;
                }
                if (self::isJson($trimmed) && ($options['json_string_to_array'] ?? false)) {
                    return json_decode($trimmed, true);
                }
                if (is_numeric($trimmed) && ($options['numeric_string_to_number'] ?? false)) {
                    return ctype_digit($trimmed) ? (int)$trimmed : (float)$trimmed;
                }
                return $trimmed;

            case 'array':
                if ($options['filter_empty'] ?? false) {
                    $value = array_filter($value, fn($v) => !empty($v) || $v === 0 || $v === '0');
                }
                if ($options['recursive'] ?? false) {
                    return array_map(fn($v) => self::normalize($v, $options), $value);
                }
                return $value;

            default:
                return $value;
        }
    }

    // Type juggling safety
    public static function safeInt($value, ?int $min = null, ?int $max = null): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);

        if ($int === false) {
            throw new InvalidArgumentException("Value is not a valid integer");
        }

        if ($min !== null && $int < $min) {
            throw new RangeException("Integer must be >= {$min}");
        }

        if ($max !== null && $int > $max) {
            throw new RangeException("Integer must be <= {$max}");
        }

        return $int;
    }


    // Main conversion method with improved UX
    public static function to($value, string $targetType, array $options = [])
    {
        // Default options
        $options = array_merge([
            'convert' => self::CONVERT['STRICT'],
            'format' => null,
            'delimiter' => ',',
            'enclosure' => '"',
        ], $options);

        try {
            return self::performConversion($value, $targetType, $options);
        } catch (Exception $e) {
            if ($options['convert'] === self::CONVERT['LENIENT']) {
                return null;
            }
            throw $e;
        }
    }

    private static function performConversion($value, string $targetType, array|string $options)
    {
        $currentType = self::getType($value);

        // Early return for matching types
        if (strtolower($currentType) === strtolower($targetType)) {
            return $value;
        }

        // Conversion matrix
        $conversions = [
            'json' => fn($v) => self::toJson($v, $options),
            'array' => fn($v) => self::toArray($v, $options),
            'datetime' => fn($v) => self::toDateTime($v, $options),
            'csv' => fn($v) => self::toCsv($v, $options),
            'int' => fn($v) => self::toInt($v, $options),
            'bool' => fn($v) => self::toBool($v, $options),
            // ... other conversions
        ];

        $targetType = strtolower($targetType);

        if (isset($conversions[$targetType])) {
            return $conversions[$targetType]($value);
        }

        // Fallback to PHP's settype for basic types
        if (in_array($targetType, ['string', 'float', 'object'])) {
            settype($value, $targetType);
            return $value;
        }

        throw new InvalidArgumentException("Unsupported conversion to {$targetType}");
    }

    // Enhanced conversion methods with better options handling
    public static function toJson($value, array $options = []): string
    {
        $options = array_merge([
            'flags' => self::JSON['ESCAPE_SLASHES'] | self::JSON['UNICODE'],
            'depth' => 512,
        ], $options);

        $json = json_encode($value, $options['flags'], $options['depth']);

        if (json_last_error() !== JSON_ERROR_NONE && $options['convert'] !== self::CONVERT['LENIENT']) {
            throw new RuntimeException("JSON encode error: " . json_last_error_msg());
        }

        return $json;
    }

    public static function toCsv($value, array $options = []): string
    {
        $options = array_merge([
            'mode' => self::CSV['HEADERS'],
            'delimiter' => ',',
            'enclosure' => '"',
        ], $options);

        $array = self::toArray($value);
        $output = fopen('php://temp', 'r+');

        if ($options['mode'] & self::CSV['HEADERS'] && !array_is_list($array)) {
            fputcsv($output, array_keys($array), $options['delimiter'], $options['enclosure']);
        }

        fputcsv($output, $array, $options['delimiter'], $options['enclosure']);
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        if ($options['mode'] & self::CSV['ESCAPE_FORMULAS'] && str_starts_with(trim($csv), '=')) {
            $csv = "\t" . $csv; // Prevent CSV injection
        }

        return rtrim($csv);
    }

    // Type checking with better UX
    public static function is($value, $types, array $options = []): bool
    {
        if (is_string($types)) {
            $types = explode('|', $types);
        }

        foreach ($types as $type) {
            if (self::checkSingleType($value, $type, $options)) {
                return true;
            }
        }

        return false;
    }

    private static function checkSingleType($value, string $type, array $options): bool
    {
        // Special type checks
        switch (strtolower($type)) {
            case 'numeric':
                return is_numeric($value);
            case 'scalar':
                return is_scalar($value);
            case 'empty':
                return empty($value);
            case 'json':
                return self::isJson($value);
            case 'datetime':
                return self::isDateTime($value);
            case 'callable':
                return is_callable($value);
            case 'countable':
                return is_countable($value);
            case 'iterable':
                return is_iterable($value);
            case 'resource':
                return is_resource($value);
            case 'binary':
                return self::isBinary($value);
            case 'timestamp':
                return self::isTimestamp($value);
        }

        // Normal type comparison
        return strtolower(self::getType($value)) === strtolower($type);
    }

    // Fluent interface alternative
    public static function check($value): TypeChecker
    {
        return new TypeChecker($value);
    }
}

// Fluent interface helper
class TypeChecker
{
    private $value;
    private $lastResult = true;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function is($type, array $options = []): self
    {
        $this->lastResult = $this->lastResult && Type::is($this->value, $type, $options);
        return $this;
    }

    public function not($type, array $options = []): self
    {
        $this->lastResult = $this->lastResult && !Type::is($this->value, $type, $options);
        return $this;
    }

    public function ok(): bool
    {
        return $this->lastResult;
    }

    public function assert(): void
    {
        if (!$this->lastResult) {
            throw new RuntimeException("Type check failed");
        }
    }
}
