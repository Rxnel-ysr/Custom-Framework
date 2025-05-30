<?php

namespace App\Foundation\Support;

use DateTime;
use DateTimeInterface;
use Traversable;
use ReflectionClass;
use Closure;
use ArrayAccess;
use Countable;
use Iterator;
use Throwable;
use Stringable;
use SplFileInfo;
use JsonSerializable;
use Serializable;
use UnitEnum;
use BackedEnum;
use DateTimeZone;
use RuntimeException;
use Exception;
use RangeException;
use InvalidArgumentException;
use Normalizer;

/**
 * Comprehensive type handling and conversion utility with brutal edge-case coverage
 */
class Type
{
    // Option constants for better UX
    public const CONVERT = [
        'STRICT' => 1,         // Throw exceptions on conversion failures
        'LENIENT' => 2,        // Return null on conversion failures
        'AUTO_CORRECT' => 4,   // Attempt to fix common issues
        'THROW' => 8,          // Throw detailed exceptions with context
    ];

    public const JSON = [
        'PRETTY' => JSON_PRETTY_PRINT,
        'ESCAPE_SLASHES' => JSON_UNESCAPED_SLASHES,
        'UNICODE' => JSON_UNESCAPED_UNICODE,
        'FORCE_OBJECT' => JSON_FORCE_OBJECT,
        'NUMERIC_CHECK' => JSON_NUMERIC_CHECK,
        'PRESERVE_ZERO_FRACTION' => JSON_PRESERVE_ZERO_FRACTION,
    ];

    public const CSV = [
        'HEADERS' => 1,         // Include headers in output
        'NO_HEADERS' => 2,      // Skip headers
        'ESCAPE_FORMULAS' => 4, // Add tab prefix to prevent CSV injection
        'MULTI_DIMENSIONAL' => 8, // Handle multi-dimensional arrays
    ];

    public const NORMALIZE = [
        'TRIM' => 1,
        'EMPTY_TO_NULL' => 2,
        'LOWERCASE' => 4,
        'UPPERCASE' => 8,
        'COLLAPSE_WHITESPACE' => 16,
        'REMOVE_ACCENTS' => 32,
        'STRIP_TAGS' => 64,
        'HTML_ENTITIES' => 128,
    ];

    public const DATETIME = [
        'ISO8601' => 'Y-m-d\TH:i:sP',
        'MYSQL' => 'Y-m-d H:i:s',
        'SHORT_DATE' => 'Y-m-d',
        'RFC2822' => 'r',
        'ATOM' => DateTimeInterface::ATOM,
        'COOKIE' => DateTimeInterface::COOKIE,
        'RFC822' => DateTimeInterface::RFC822,
        'RFC850' => DateTimeInterface::RFC850,
        'RFC1036' => DateTimeInterface::RFC1036,
        'RFC1123' => DateTimeInterface::RFC1123,
        'RFC7231' => DateTimeInterface::RFC7231,
        'RSS' => DateTimeInterface::RSS,
        'W3C' => DateTimeInterface::W3C,
    ];

    public const COMPARE = [
        'STRICT' => 1,          // Strict type comparison
        'LOOSE' => 2,           // Loose type comparison
        'NUMERIC' => 4,         // Numeric comparison
        'CASE_SENSITIVE' => 8,  // Case-sensitive string comparison
        'DATETIME' => 16,       // DateTime comparison
    ];

    /**
     * Get detailed type information for a variable with brutal precision
     */
    public static function getType($var): string
    {
        $type = gettype($var);

        // Normalize type names
        $type = match ($type) {
            'double' => 'float',
            'NULL' => 'null',
            'resource (closed)' => 'closed_resource',
            'array' => 'array',
            'boolean' => 'bool',
            'integer' => 'int',
            default => $type,
        };

        // Handle resources with extreme detail
        if ($type === 'resource') {
            $resourceType = get_resource_type($var);
            $meta = [];

            // Add resource-specific metadata
            if ($resourceType === 'stream') {
                $meta = stream_get_meta_data($var);
                $meta = [
                    'wrapper' => $meta['wrapper_type'] ?? null,
                    'protocol' => $meta['stream_type'] ?? null,
                    'mode' => $meta['mode'] ?? null,
                    'seekable' => $meta['seekable'] ?? null,
                ];
            }

            $metaString = $meta ? json_encode($meta) : '';
            return match ($resourceType) {
                'stream' => "stream:$metaString",
                'curl' => 'curl_handle',
                'gd' => 'gd_image',
                'xml' => 'xml_parser',
                'zlib' => 'zlib_compression',
                'openssl' => 'openssl_certificate',
                default => "resource:$resourceType" . ($metaString ? ":$metaString" : ''),
            };
        }

        // Special object detection with brutal specificity
        if ($type === 'object') {
            // Check for common internal PHP classes
            if ($var instanceof Closure) return 'closure';
            if ($var instanceof DateTimeInterface) return 'datetime';

            // Check for SPL classes
            $splTypes = [
                'ArrayObject',
                'SplFileInfo',
                'SplFileObject',
                'SplTempFileObject',
                'SplDoublyLinkedList',
                'SplStack',
                'SplQueue',
                'SplHeap',
                'SplMinHeap',
                'SplMaxHeap',
                'SplPriorityQueue',
                'SplFixedArray',
                'SplObjectStorage',
            ];

            foreach ($splTypes as $splType) {
                if ($var instanceof $splType) {
                    return 'spl:' . strtolower($splType);
                }
            }

            // Check for common interfaces
            return match (true) {
                $var instanceof ArrayAccess => 'array_access',
                $var instanceof Countable => 'countable',
                $var instanceof Iterator => 'iterator',
                $var instanceof Throwable => 'throwable',
                $var instanceof Traversable => 'traversable',
                $var instanceof Stringable => 'stringable',
                $var instanceof JsonSerializable => 'json_serializable',
                $var instanceof Serializable => 'serializable',
                $var instanceof UnitEnum => 'enum',
                $var instanceof BackedEnum => 'backed_enum:' . gettype($var->value),
                default => 'object:' . get_class($var),
            };
        }

        // Special string cases with brutal analysis
        if ($type === 'string') {
            $stringType = [];

            if ($var === '') {
                $stringType[] = 'empty';
            } else {
                if (class_exists('Normalizer') && Normalizer::isNormalized($var)) {
                    $stringType[] = 'normalized';
                }
                if (preg_match('//u', $var) === 1) {
                    $stringType[] = 'unicode';
                }
                if (is_numeric($var)) {
                    $stringType[] = 'numeric';
                }
                if (ctype_print($var)) {
                    $stringType[] = 'printable';
                }
                if (mb_detect_encoding($var, 'UTF-8', true) === false) {
                    $stringType[] = 'non_utf8';
                }
                if (strlen($var) !== mb_strlen($var)) {
                    $stringType[] = 'multibyte';
                }
                if (preg_match('/[\x00-\x1F\x7F]/', $var)) {
                    $stringType[] = 'control_chars';
                }
                if (self::isBinary($var)) {
                    $stringType[] = 'binary';
                }
                if (self::isJson($var)) {
                    $stringType[] = 'json';
                }
                if (self::isDateTime($var)) {
                    $stringType[] = 'datetime_string';
                }
            }

            return $stringType ? 'string:' . implode('_', $stringType) : 'string';
        }

        // Callable detection (needs to be after object check)
        if (is_callable($var) && $type !== 'closure') {
            return 'callable';
        }

        return $type;
    }

    /**
     * Convert value to array with brutal options
     */
    public static function toArray($value, array $options = []): array
    {
        $options = array_merge([
            'recursive' => false,
            'public_only' => true,
            'include_methods' => false,
            'max_depth' => 10,
            'convert' => self::CONVERT['STRICT'],
        ], $options);

        if (is_array($value)) {
            if ($options['recursive']) {
                return array_map(function ($v) use ($options) {
                    return is_array($v) || is_object($v)
                        ? self::toArray($v, array_merge($options, ['max_depth' => $options['max_depth'] - 1]))
                        : $v;
                }, $value);
            }
            return $value;
        }

        if ($value instanceof Traversable) {
            return iterator_to_array($value);
        }

        if (is_object($value)) {
            $result = [];

            // Handle public properties
            if ($options['public_only']) {
                $result = get_object_vars($value);
            } else {
                // Use reflection to get all properties including private/protected
                $reflection = new ReflectionClass($value);
                foreach ($reflection->getProperties() as $property) {
                    $property->setAccessible(true);
                    $result[$property->getName()] = $property->getValue($value);
                }
            }

            // Include methods if requested
            if ($options['include_methods']) {
                $reflection = new ReflectionClass($value);
                foreach ($reflection->getMethods() as $method) {
                    $result['methods'][$method->getName()] = $method->getParameters();
                }
            }

            if ($options['recursive'] && $options['max_depth'] > 0) {
                foreach ($result as $key => $val) {
                    if (is_object($val) || is_array($val)) {
                        $result[$key] = self::toArray($val, array_merge($options, ['max_depth' => $options['max_depth'] - 1]));
                    }
                }
            }

            return $result;
        }

        if (is_scalar($value) || is_null($value)) {
            return [$value];
        }

        if ($options['convert'] & self::CONVERT['LENIENT']) {
            return [];
        }

        if ($options['convert'] & self::CONVERT['THROW']) {
            throw new TypeConversionException(
                "Cannot convert type " . self::getType($value) . " to array",
                ['value' => $value, 'options' => $options]
            );
        }

        throw new RuntimeException("Cannot convert type " . self::getType($value) . " to array");
    }

    /**
     * Convert value to DateTimeInterface with brutal precision
     */
    public static function toDateTime($value, ?string $format = null, array $options = []): DateTimeInterface
    {
        $options = array_merge([
            'timezone' => null,
            'fallback' => null,
            'convert' => self::CONVERT['STRICT'],
        ], $options);

        if ($value instanceof DateTimeInterface) {
            if ($options['timezone']) {
                $value = clone $value;
                $value->setTimezone(new DateTimeZone($options['timezone']));
            }
            return $value;
        }

        if (is_numeric($value)) {
            $dt = new DateTime();
            $dt->setTimestamp((int)$value);
            if ($options['timezone']) {
                $dt->setTimezone(new DateTimeZone($options['timezone']));
            }
            return $dt;
        }

        if (is_string($value)) {
            try {
                // Handle ISO 8601 with timezone offset
                if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})$/i', $value)) {
                    return new DateTime($value);
                }

                if ($format) {
                    $dt = DateTime::createFromFormat($format, $value);
                    if ($dt !== false) {
                        if ($options['timezone']) {
                            $dt->setTimezone(new DateTimeZone($options['timezone']));
                        }
                        return $dt;
                    }
                }

                $dt = new DateTime($value);
                if ($options['timezone']) {
                    $dt->setTimezone(new DateTimeZone($options['timezone']));
                }
                return $dt;
            } catch (Exception $e) {
                if ($options['convert'] & self::CONVERT['LENIENT']) {
                    return $options['fallback'] ?? new DateTime();
                }

                if ($options['convert'] & self::CONVERT['THROW']) {
                    throw new TypeConversionException(
                        "Failed to parse datetime: " . $e->getMessage(),
                        ['value' => $value, 'format' => $format, 'options' => $options],
                        $e
                    );
                }

                throw new RuntimeException("Failed to parse datetime: " . $e->getMessage());
            }
        }

        if ($options['convert'] & self::CONVERT['LENIENT']) {
            return $options['fallback'] ?? new DateTime();
        }

        if ($options['convert'] & self::CONVERT['THROW']) {
            throw new TypeConversionException(
                "Cannot convert type " . self::getType($value) . " to DateTime",
                ['value' => $value, 'options' => $options]
            );
        }

        throw new RuntimeException("Cannot convert type " . self::getType($value) . " to DateTime");
    }

    /**
     * Convert value to integer with brutal validation
     */
    public static function toInt($value, array $options = []): int
    {
        $options = array_merge([
            'min' => null,
            'max' => null,
            'base' => 10,
            'convert' => self::CONVERT['STRICT'],
        ], $options);

        if (is_int($value)) {
            self::validateIntRange($value, $options);
            return $value;
        }

        // Handle numeric strings with different bases
        if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
            $int = intval($value, $options['base']);
            if (strval($int) === $value || ($options['convert'] & self::CONVERT['AUTO_CORRECT'])) {
                self::validateIntRange($int, $options);
                return $int;
            }
        }

        // Handle floats
        if (is_float($value)) {
            if ($value >= PHP_INT_MIN && $value <= PHP_INT_MAX) {
                $int = (int)$value;
                if ($options['convert'] & self::CONVERT['AUTO_CORRECT'] || $int == $value) {
                    self::validateIntRange($int, $options);
                    return $int;
                }
            }
        }

        // Handle booleans
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if ($options['convert'] & self::CONVERT['LENIENT']) {
            return 0;
        }

        if ($options['convert'] & self::CONVERT['THROW']) {
            throw new TypeConversionException(
                "Cannot convert value to integer",
                ['value' => $value, 'options' => $options]
            );
        }

        throw new RuntimeException("Cannot convert value to integer");
    }

    private static function validateIntRange(int $value, array $options): void
    {
        if (isset($options['min']) && $value < $options['min']) {
            if ($options['convert'] & self::CONVERT['LENIENT']) {
                $value = $options['min'];
                return;
            }

            if ($options['convert'] & self::CONVERT['THROW']) {
                throw new RangeException(
                    "Integer must be >= {$options['min']}",
                    ['value' => $value, 'options' => $options]
                );
            }

            throw new RangeException("Integer must be >= {$options['min']}");
        }

        if (isset($options['max']) && $value > $options['max']) {
            if ($options['convert'] & self::CONVERT['LENIENT']) {
                $value = $options['max'];
                return;
            }

            if ($options['convert'] & self::CONVERT['THROW']) {
                throw new RangeException(
                    "Integer must be <= {$options['max']}",
                    ['value' => $value, 'options' => $options]
                );
            }

            throw new RangeException("Integer must be <= {$options['max']}");
        }
    }

    /**
     * Convert value to boolean with brutal options
     */
    public static function toBool($value, array $options = []): bool
    {
        $options = array_merge([
            'false_values' => ['false', '0', 'no', 'off', ''],
            'true_values' => ['true', '1', 'yes', 'on'],
            'strict' => false,
            'convert' => self::CONVERT['STRICT'],
        ], $options);

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));

            if (in_array($value, $options['false_values'], true)) {
                return false;
            }

            if (in_array($value, $options['true_values'], true)) {
                return true;
            }

            if ($options['strict']) {
                if ($options['convert'] & self::CONVERT['LENIENT']) {
                    return false;
                }

                if ($options['convert'] & self::CONVERT['THROW']) {
                    throw new TypeConversionException(
                        "String value not in configured boolean values",
                        ['value' => $value, 'options' => $options]
                    );
                }

                throw new RuntimeException("String value not in configured boolean values");
            }
        }

        if (is_array($value)) {
            return !empty($value);
        }

        if (is_object($value)) {
            return true;
        }

        if (is_null($value)) {
            return false;
        }

        return (bool)$value;
    }

    /**
     * Check if value is valid JSON with brutal validation
     */
    public static function isJson($value, bool $assoc = false, int $depth = 512, int $options = 0): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        // Check for JSONP
        if (strpos($value, '(') === 0) {
            $value = trim($value);
            $value = ltrim($value, '(');
            $value = rtrim($value, ')');
            $value = trim($value, ';');
        }

        // Check for invalid UTF-8
        if (!mb_check_encoding($value, 'UTF-8')) {
            return false;
        }

        // Check for common JSON patterns without full decode
        $firstChar = substr($value, 0, 1);
        if ($firstChar !== '{' && $firstChar !== '[' && $firstChar !== '"' && !is_numeric($firstChar)) {
            return false;
        }

        // Full decode
        json_decode($value, $assoc, $depth, $options);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Check if value can be converted to DateTime with brutal validation
     */
    public static function isDateTime($value): bool
    {
        if ($value instanceof DateTimeInterface) {
            return true;
        }

        if (is_numeric($value)) {
            return $value >= ~PHP_INT_MAX && $value <= PHP_INT_MAX;
        }

        if (!is_string($value)) {
            return false;
        }

        // Check for common date patterns without full parsing
        if (!preg_match('/\d{4}/', $value)) {
            return false;
        }

        try {
            new DateTime($value);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if value is a valid timestamp with brutal validation
     */
    public static function isTimestamp($value): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $value = (int)$value;

        // Check if the timestamp is in a reasonable range (1901-2038 for 32-bit, but wider for 64-bit)
        $year = (int)date('Y', $value);
        return $year >= 1901 && $year <= 2100;
    }

    /**
     * Check if value is binary data with brutal analysis
     */
    public static function isBinary($value, float $threshold = 0.3): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Empty string is not binary
        if ($value === '') {
            return false;
        }

        // Check for null bytes - a strong indicator of binary data
        if (strpos($value, "\0") !== false) {
            return true;
        }

        // Check for high percentage of non-printable characters
        $length = strlen($value);
        $nonPrintable = 0;

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === "\0") {
                return true;
            }
            if (ord($char) < 32 || ord($char) > 126) {
                $nonPrintable++;
            }
        }

        return ($nonPrintable / $length) > $threshold;
    }

    /**
     * Normalize value based on brutal options
     */
    public static function normalize($value, array $options = [])
    {
        $type = self::getType($value);

        switch ($type) {
            case 'string':
                $value = (string)$value;

                if ($options[self::NORMALIZE['TRIM']] ?? true) {
                    $value = trim($value);
                }

                if ($options[self::NORMALIZE['COLLAPSE_WHITESPACE']] ?? false) {
                    $value = preg_replace('/\s+/', ' ', $value);
                }

                if ($options[self::NORMALIZE['LOWERCASE']] ?? false) {
                    $value = strtolower($value);
                }

                if ($options[self::NORMALIZE['UPPERCASE']] ?? false) {
                    $value = strtoupper($value);
                }

                if ($options[self::NORMALIZE['REMOVE_ACCENTS']] ?? false) {
                    $value = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
                }

                if ($options[self::NORMALIZE['STRIP_TAGS']] ?? false) {
                    $value = strip_tags($value);
                }

                if ($options[self::NORMALIZE['HTML_ENTITIES']] ?? false) {
                    $value = htmlentities($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
                }

                if (($options[self::NORMALIZE['EMPTY_TO_NULL']] ?? false) && $value === '') {
                    return null;
                }

                return $value;

            case 'array':
                if ($options['filter_empty'] ?? false) {
                    $value = array_filter($value, fn($v) => !empty($v) || $v === 0 || $v === '0');
                }

                if ($options['recursive'] ?? false) {
                    return array_map(fn($v) => self::normalize($v, $options), $value);
                }

                return $value;

            case 'int':
            case 'float':
                if ($options['round'] ?? false) {
                    return round($value, $options['precision'] ?? 0);
                }
                return $value;

            case 'datetime':
                $format = $options['format'] ?? self::DATETIME['ISO8601'];
                return $value->format($format);

            default:
                return $value;
        }
    }

    /**
     * Main conversion method with brutal options
     */
    public static function to($value, string $targetType, array $options = [])
    {
        $options = array_merge([
            'convert' => self::CONVERT['STRICT'],
            'format' => null,
            'throw' => false,
        ], $options);

        try {
            $targetType = strtolower($targetType);

            switch ($targetType) {
                case 'array':
                    return self::toArray($value, $options);
                case 'int':
                case 'integer':
                    return self::toInt($value, $options);
                case 'bool':
                case 'boolean':
                    return self::toBool($value, $options);
                case 'float':
                case 'double':
                    return self::toFloat($value, $options);
                case 'string':
                    return self::toString($value, $options);
                case 'datetime':
                case 'date':
                case 'time':
                    return self::toDateTime($value, $options['format'] ?? null, $options);
                case 'json':
                    return self::toJson($value, $options);
                case 'csv':
                    return self::toCsv($value, $options);
                case 'object':
                    return self::toObject($value, $options);
                case 'binary':
                    return self::toBinary($value, $options);
                case 'resource':
                    return self::toResource($value, $options);
                default:
                    // Handle class names for object conversion
                    if (class_exists($targetType) || interface_exists($targetType)) {
                        return self::toClass($value, $targetType, $options);
                    }

                    if ($options['convert'] & self::CONVERT['THROW']) {
                        throw new TypeConversionException(
                            "Unsupported target type: {$targetType}",
                            ['value' => $value, 'options' => $options]
                        );
                    }

                    throw new InvalidArgumentException("Unsupported target type: {$targetType}");
            }
        } catch (Exception $e) {
            if ($options['convert'] === self::CONVERT['LENIENT']) {
                return null;
            }

            if ($options['convert'] & self::CONVERT['THROW']) {
                throw new TypeConversionException(
                    "Conversion to {$targetType} failed: " . $e->getMessage(),
                    ['value' => $value, 'options' => $options],
                    $e
                );
            }

            throw $e;
        }
    }

    /**
     * Convert value to JSON string with brutal options
     */
    public static function toJson($value, array $options = []): string
    {
        $options = array_merge([
            'flags' => self::JSON['ESCAPE_SLASHES'] | self::JSON['UNICODE'],
            'depth' => 512,
            'convert' => self::CONVERT['STRICT'],
        ], $options);

        // Handle JSON serialization of resources
        if (is_resource($value)) {
            if ($options['convert'] & self::CONVERT['LENIENT']) {
                return 'null';
            }

            if ($options['convert'] & self::CONVERT['THROW']) {
                throw new TypeConversionException(
                    "Cannot convert resource to JSON",
                    ['value' => $value, 'options' => $options]
                );
            }

            throw new RuntimeException("Cannot convert resource to JSON");
        }

        $json = json_encode($value, $options['flags'], $options['depth']);

        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($options['convert'] & self::CONVERT['LENIENT']) {
                return 'null';
            }

            if ($options['convert'] & self::CONVERT['THROW']) {
                throw new TypeConversionException(
                    "JSON encode error: " . json_last_error_msg(),
                    ['value' => $value, 'options' => $options]
                );
            }

            throw new RuntimeException("JSON encode error: " . json_last_error_msg());
        }

        return $json;
    }

    /**
     * Convert value to CSV string with brutal options
     */
    public static function toCsv($value, array $options = []): string
    {
        $options = array_merge([
            'mode' => self::CSV['HEADERS'],
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\',
            'eol' => PHP_EOL,
            'convert' => self::CONVERT['STRICT'],
        ], $options);

        $array = self::toArray($value, ['convert' => $options['convert']]);
        $output = fopen('php://temp', 'r+');

        // Handle multi-dimensional arrays
        if (($options['mode'] & self::CSV['MULTI_DIMENSIONAL']) && self::isMultiDimensional($array)) {
            $first = true;
            foreach ($array as $row) {
                $row = self::toArray($row, ['convert' => $options['convert']]);
                if ($first && ($options['mode'] & self::CSV['HEADERS'])) {
                    fputcsv($output, array_keys($row), $options['delimiter'], $options['enclosure'], $options['escape']);
                    $first = false;
                }
                fputcsv($output, $row, $options['delimiter'], $options['enclosure'], $options['escape']);
            }
        } else {
            // Single dimensional array
            if (($options['mode'] & self::CSV['HEADERS']) && !empty($array) && !array_is_list($array)) {
                fputcsv($output, array_keys($array), $options['delimiter'], $options['enclosure'], $options['escape']);
            }
            fputcsv($output, $array, $options['delimiter'], $options['enclosure'], $options['escape']);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        // Handle line endings
        if ($options['eol'] !== PHP_EOL) {
            $csv = str_replace(PHP_EOL, $options['eol'], $csv);
        }

        // Prevent CSV injection
        if (($options['mode'] & self::CSV['ESCAPE_FORMULAS']) && str_starts_with(trim($csv), '=')) {
            $csv = "\t" . $csv;
        }

        return rtrim($csv, $options['eol']);
    }

    /**
     * Convert value to float with brutal precision
     */
    public static function toFloat($value, array $options = []): float
    {
        $options = array_merge([
            'min' => null,
            'max' => null,
            'precision' => null,
            'round_mode' => PHP_ROUND_HALF_UP,
            'convert' => self::CONVERT['STRICT'],
        ], $options);

        if (is_float($value)) {
            self::validateFloatRange($value, $options);
            return $value;
        }

        if (is_int($value)) {
            return (float)$value;
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
            if (is_numeric($value)) {
                $float = (float)$value;
                self::validateFloatRange($float, $options);

                if ($options['precision'] !== null) {
                    $float = round($float, $options['precision'], $options['round_mode']);
                }

                return $float;
            }
        }

        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        if ($options['convert'] & self::CONVERT['LENIENT']) {
            return 0.0;
        }

        if ($options['convert'] & self::CONVERT['THROW']) {
            throw new TypeConversionException(
                "Cannot convert value to float",
                ['value' => $value, 'options' => $options]
            );
        }

        throw new RuntimeException("Cannot convert value to float");
    }

    private static function validateFloatRange(float $value, array $options): void
    {
        if (isset($options['min']) && $value < $options['min']) {
            if ($options['convert'] & self::CONVERT['LENIENT']) {
                $value = $options['min'];
                return;
            }

            if ($options['convert'] & self::CONVERT['THROW']) {
                throw new RangeException(
                    "Float must be >= {$options['min']}",
                );
            }

            throw new RangeException("Float must be >= {$options['min']}");
        }

        if (isset($options['max']) && $value > $options['max']) {
            if ($options['convert'] & self::CONVERT['LENIENT']) {
                $value = $options['max'];
                return;
            }

            if ($options['convert'] & self::CONVERT['THROW']) {
                throw new RangeException(
                    "Float must be <= {$options['max']}",
                );
            }

            throw new RangeException("Float must be <= {$options['max']}");
        }
    }

    /**
     * Convert value to string with brutal options
     */
    public static function toString($value, array $options = []): string
    {
        $options = array_merge([
            'encoding' => 'UTF-8',
            'convert_encoding' => false,
            'convert' => self::CONVERT['STRICT'],
        ], $options);

        if (is_string($value)) {
            if ($options['convert_encoding'] && !mb_check_encoding($value, $options['encoding'])) {
                $value = mb_convert_encoding($value, $options['encoding']);
            }
            return $value;
        }

        if (is_scalar($value) || is_null($value)) {
            return (string)$value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }

        if (is_object($value) && $value instanceof Stringable) {
            return (string)$value;
        }

        if (is_resource($value)) {
            return 'Resource #' . (int)$value;
        }

        if (is_array($value)) {
            return 'Array';
        }

        if ($options['convert'] & self::CONVERT['LENIENT']) {
            return '';
        }

        if ($options['convert'] & self::CONVERT['THROW']) {
            throw new TypeConversionException(
                "Cannot convert value to string",
                ['value' => $value, 'options' => $options]
            );
        }

        throw new RuntimeException("Cannot convert value to string");
    }

    /**
     * Convert value to binary string with brutal options
     */
    public static function toBinary($value, array $options = []): string
    {
        $options = array_merge([
            'encoding' => 'UTF-8',
            'convert' => self::CONVERT['STRICT'],
        ], $options);

        if (is_string($value)) {
            if (!mb_check_encoding($value, $options['encoding'])) {
                $value = mb_convert_encoding($value, $options['encoding']);
            }
            return $value;
        }

        if (is_resource($value)) {
            return stream_get_contents($value);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }

        if ($options['convert'] & self::CONVERT['LENIENT']) {
            return '';
        }

        if ($options['convert'] & self::CONVERT['THROW']) {
            throw new TypeConversionException(
                "Cannot convert value to binary string",
                ['value' => $value, 'options' => $options]
            );
        }

        throw new TypeConversionException("Cannot convert value to binary");
    }
}
