<?php

namespace App\Foundation\Support;

use App\Support\Facades\DI;
use DateTime;
use DateTimeInterface;
use ReflectionClass;
use Traversable;

/**
 * Comprehensive type handling and conversion utility
 */
class Type
{
    // Option constants for better UX
    public const CONVERT = [
        'STRICT' => 1,         // Throw exceptions on conversion failures
        'LENIENT' => 2,        // Return null on conversion failures
        'AUTO_CORRECT' => 4,   // Attempt to fix common issues
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

    /**
     * Get detailed type information for a variable
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

        // Handle resources
        if ($type === 'resource') {
            $resourceType = get_resource_type($var);
            return match ($resourceType) {
                'stream' => 'stream',
                'curl' => 'curl_handle',
                'gd' => 'gd_image',
                'xml' => 'xml_parser',
                'zlib' => 'zlib_compression',
                'openssl' => 'openssl_certificate',
                default => 'resource:' . $resourceType,
            };
        }

        // Special object detection
        if ($type === 'object') {
            return match (true) {
                $var instanceof DateTimeInterface => 'datetime',
                $var instanceof Closure => 'closure',
                $var instanceof ArrayAccess => 'array_access',
                $var instanceof Countable => 'countable',
                $var instanceof Iterator => 'iterator',
                $var instanceof Throwable => 'throwable',
                $var instanceof Traversable => 'traversable',
                $var instanceof Stringable => 'stringable',
                $var instanceof SplFileInfo => 'splfileinfo',
                $var instanceof JsonSerializable => 'json_serializable',
                $var instanceof Serializable => 'serializable',
                $var instanceof UnitEnum => 'enum',
                $var instanceof BackedEnum => 'backed_enum',
                default => 'object:' . get_class($var),
            };
        }

        // Special string cases
        if ($type === 'string') {
            if (preg_match('//u', $var) === 1) {
                return 'unicode_string';
            }
            if ($var === '') {
                return 'empty_string';
            }
        }

        // Numeric string detection
        if ($type === 'string' && is_numeric($var)) {
            return 'numeric_string';
        }

        // Callable detection (needs to be after object check)
        if (is_callable($var) && $type !== 'closure') {
            return 'callable';
        }

        return $type;
    }

    /**
     * Convert value to array
     */
    public static function toArray($value, array $options = []): array
    {
        if (is_array($value)) {
            return $options['recursive'] ?? false
                ? array_map(fn($v) => self::toArray($v, $options), $value)
                : $value;
        }

        if ($value instanceof Traversable) {
            return iterator_to_array($value);
        }

        if (is_object($value)) {
            return ($options['public_only'] ?? true)
                ? get_object_vars($value)
                : (array)$value;
        }

        if (is_scalar($value) || is_null($value)) {
            return [$value];
        }

        if ($options['convert'] ?? self::CONVERT['STRICT'] !== self::CONVERT['STRICT']) {
            return [];
        }

        throw new RuntimeException("Cannot convert type " . gettype($value) . " to array");
    }

    /**
     * Convert value to DateTimeInterface
     */
    public static function toDateTime($value, ?string $format = null, array $options = []): DateTimeInterface
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
                if ($format) {
                    $dt = DateTime::createFromFormat($format, $value);
                    if ($dt !== false) return $dt;
                }
                return new DateTime($value);
            } catch (Exception $e) {
                if ($options['convert'] ?? self::CONVERT['STRICT'] !== self::CONVERT['STRICT']) {
                    return new DateTime();
                }
                throw new RuntimeException("Failed to parse datetime: " . $e->getMessage());
            }
        }

        if ($options['convert'] ?? self::CONVERT['STRICT'] !== self::CONVERT['STRICT']) {
            return new DateTime();
        }

        throw new RuntimeException("Cannot convert type " . gettype($value) . " to DateTime");
    }

    /**
     * Convert value to integer with options
     */
    public static function toInt($value, array $options = []): int
    {
        if (is_int($value)) return $value;

        $int = filter_var($value, FILTER_VALIDATE_INT);

        if ($int === false) {
            if ($options['convert'] ?? self::CONVERT['STRICT'] !== self::CONVERT['STRICT']) {
                return 0;
            }
            throw new RuntimeException("Cannot convert value to integer");
        }

        if (isset($options['min']) && $int < $options['min']) {
            if ($options['convert'] ?? self::CONVERT['STRICT'] !== self::CONVERT['STRICT']) {
                return $options['min'];
            }
            throw new RangeException("Integer must be >= {$options['min']}");
        }

        if (isset($options['max']) && $int > $options['max']) {
            if ($options['convert'] ?? self::CONVERT['STRICT'] !== self::CONVERT['STRICT']) {
                return $options['max'];
            }
            throw new RangeException("Integer must be <= {$options['max']}");
        }

        return $int;
    }

    /**
     * Convert value to boolean
     */
    public static function toBool($value, array $options = []): bool
    {
        if (is_bool($value)) return $value;

        $falsey = $options['false_values'] ?? ['false', '0', 'no', 'off', ''];

        if (is_numeric($value)) {
            return $value != 0;
        }

        if (is_string($value)) {
            return !in_array(strtolower($value), $falsey);
        }

        return (bool)$value;
    }

    /**
     * Check if value is valid JSON
     */
    public static function isJson($value): bool
    {
        if (!is_string($value)) return false;

        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Check if value can be converted to DateTime
     */
    public static function isDateTime($value): bool
    {
        return $value instanceof DateTimeInterface
            || (is_string($value) && strtotime($value) !== false)
            || is_numeric($value);
    }

    /**
     * Check if value is a valid timestamp
     */
    public static function isTimestamp($value): bool
    {
        return is_numeric($value)
            && (int)$value == $value
            && $value <= PHP_INT_MAX
            && $value >= ~PHP_INT_MAX;
    }

    /**
     * Check if value is binary data
     */
    public static function isBinary($value): bool
    {
        return is_string($value) && !ctype_print($value);
    }

    /**
     * Normalize value based on options
     */
    public static function normalize($value, array $options = [])
    {
        $type = self::getType($value);

        switch ($type) {
            case 'string':
                $trimmed = trim($value);

                if ($options[self::NORMALIZE['TRIM']] ?? true) {
                    $value = $trimmed;
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

            default:
                return $value;
        }
    }

    /**
     * Main conversion method with options
     */
    public static function to($value, string $targetType, array $options = [])
    {
        $options = array_merge([
            'convert' => self::CONVERT['STRICT'],
            'format' => null,
        ], $options);

        try {
            switch (strtolower($targetType)) {
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
                    return (float)$value;
                case 'string':
                    return (string)$value;
                case 'datetime':
                    return self::toDateTime($value, $options['format'] ?? null, $options);
                case 'json':
                    return self::toJson($value, $options);
                case 'csv':
                    return self::toCsv($value, $options);
                case 'object':
                    return (object)$value;
                default:
                    throw new InvalidArgumentException("Unsupported target type: {$targetType}");
            }
        } catch (Exception $e) {
            if ($options['convert'] === self::CONVERT['LENIENT']) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Convert value to JSON string
     */
    public static function toJson($value, array $options = []): string
    {
        $options = array_merge([
            'flags' => self::JSON['ESCAPE_SLASHES'] | self::JSON['UNICODE'],
            'depth' => 512,
        ], $options);

        $json = json_encode($value, $options['flags'], $options['depth']);

        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($options['convert'] ?? self::CONVERT['STRICT'] !== self::CONVERT['STRICT']) {
                return 'null';
            }
            throw new RuntimeException("JSON encode error: " . json_last_error_msg());
        }

        return $json;
    }

    /**
     * Convert value to CSV string
     */
    public static function toCsv($value, array $options = []): string
    {
        $options = array_merge([
            'mode' => self::CSV['HEADERS'],
            'delimiter' => ',',
            'enclosure' => '"',
        ], $options);

        $array = self::toArray($value);
        $output = fopen('php://temp', 'r+');

        if (($options['mode'] & self::CSV['HEADERS']) && !empty($array) && !array_is_list($array)) {
            fputcsv($output, array_keys($array), $options['delimiter'], $options['enclosure']);
        }

        fputcsv($output, $array, $options['delimiter'], $options['enclosure']);
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        if (($options['mode'] & self::CSV['ESCAPE_FORMULAS']) && str_starts_with(trim($csv), '=')) {
            $csv = "\t" . $csv; // Prevent CSV injection
        }

        return rtrim($csv);
    }

    /**
     * Type checking with multiple possible types
     */
    public static function is($value, $types, array $options = []): bool
    {
        if (is_string($types)) {
            $types = array_map('trim', explode('|', $types));
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
        $type = strtolower($type);

        // Special type checks
        switch ($type) {
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
        return strtolower(self::getType($value)) === $type;
    }

    /**
     * Fluent interface entry point
     */
    public static function check($value): TypeChecker
    {
        return new TypeChecker($value);
    }

    /**
     * Dump and Die - Advanced debugging function with type-aware output
     * 
     * Displays variables with detailed type information, syntax highlighting, and collapsible sections.
     * Integrates with Type class for consistent type handling and conversion display.
     *
     * @param mixed ...$args Variables to dump (accepts multiple arguments)
     */
    public static function dd(...$args): void
    {
        // Output styling and scripting
        echo <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <title>Debug Dump</title>
                <meta charset="UTF-8">
                <style>
                    body { 
                        background: #111; 
                        color: #f0f0f0; 
                        font-family: "Fira Code", "Consolas", monospace; 
                        padding: 20px; 
                        line-height: 1.5;
                    }
                    .dump-container { 
                        background: #1e1e1e; 
                        padding: 15px; 
                        border-radius: 5px; 
                        margin: 15px 0; 
                        box-shadow: 0 2px 10px rgba(0,0,0,0.5);
                    }
                    .dump-header { 
                        color: #ff6b6b; 
                        font-weight: bold; 
                        margin-bottom: 8px; 
                        cursor: pointer; 
                        padding: 8px 12px;
                        border-radius: 4px;
                        background: #252525;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        user-select: none;
                        transition: background 0.2s;
                    }
                    .dump-header:hover {
                        background: #2e2e2e;
                    }
                    .dump-content { 
                        white-space: pre-wrap; 
                        font-size: 14px; 
                        display: none; 
                        padding: 12px; 
                        border-left: 3px solid #ff6b6b; 
                        background: #252525;
                        margin-top: 8px;
                        border-radius: 0 0 4px 4px;
                        overflow-x: auto;
                    }
                    .dump-type {
                        color: #4dabf7;
                        font-size: 0.85em;
                        background: rgba(77, 171, 247, 0.1);
                        padding: 2px 6px;
                        border-radius: 3px;
                        margin-left: 8px;
                    }
                    .dump-size {
                        color: #94d82d;
                        font-size: 0.85em;
                    }
                    .file-info {
                        color: #adb5bd;
                        font-size: 0.85em;
                        margin: 20px 0;
                        padding: 10px;
                        background: #1e1e1e;
                        border-radius: 4px;
                    }
                    .debug-title {
                        color: #ff922b;
                        margin-bottom: 20px;
                        font-size: 1.5em;
                    }
                    /* Syntax highlighting - High contrast color scheme */
                    .string    { color: #4EC9B0; }  /* Teal - stands out clearly */
                    .number    { color: #569CD6; }  /* Soft blue - easy on eyes */
                    .boolean   { color: #FF7B72; }  /* Coral red - pops for true/false */
                    .null      { color: #C586C0; }  /* Muted purple - distinct */
                    .key       { color: #9CDCFE; }  /* Light blue - good contrast */
                    .index     { color: #858585; }  /* Medium gray - subtle for indexes */
                    .object    { color: #FFA657; }  /* Vibrant orange - clear for objects */            </style>
            </head>
            <body>
            HTML;

        // echo "<div class='debug-title'>Debug Dump</div>";

        // Get caller file information
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1] ?? null;

        if ($caller) {
            $file = $caller['file'] ?? 'unknown';
            $line = $caller['line'] ?? 'unknown';
            echo "<div class='file-info'>";
            echo "Called in: <strong>" . htmlspecialchars(basename($file)) . "</strong> on line <strong>{$line}</strong><br>";
            echo "<small>" . htmlspecialchars(dirname($file)) . "</small>";
            echo "</div>";
        }

        // Process each argument
        foreach ($args as $index => $arg) {
            $dumpId = 'dump_' . uniqid();
            $varType = Type::getType($arg);
            $sizeInfo = '';

            // Get size information
            if (is_array($arg)) {
                $sizeInfo = ' · <span class="dump-size">' . count($arg) . ' items</span>';
            } elseif (is_string($arg)) {
                $sizeInfo = ' · <span class="dump-size">' . strlen($arg) . ' chars</span>';
            } elseif (is_object($arg)) {
                $sizeInfo = ' · <span class="dump-size">' . count(get_object_vars($arg)) . ' properties</span>';
            }

            echo "<div class='dump-container'>";
            echo "<div class='dump-header' onclick='toggleDump(\"$dumpId\")' data-dump-id='$dumpId'>";
            echo "<span>Debug #" . ($index + 1) . " <span class='dump-type'>{$varType}</span>{$sizeInfo}</span>";
            echo "<span class='toggle-icon'>▼</span>";
            echo "</div>";

            echo "<div class='dump-content' id='$dumpId'><pre>";

            // Enhanced output using Type class information
            if (is_object($arg)) {
                echo htmlspecialchars(self::formatObject($arg));
            } elseif (is_array($arg)) {
                echo htmlspecialchars(self::formatArray($arg));
            } else {
                echo htmlspecialchars(self::formatValue($arg));
            }

            echo '</pre></div></div>';
        }
        echo <<<HTML
        <script>
            function toggleDump(id) {
                const el = document.getElementById(id);
                el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
                
                const header = document.querySelector(`[data-dump-id=\"\${id}\"]`);
                const icon = header.querySelector('.toggle-icon');
                icon.textContent = el.style.display === 'none' ? '▶' : '▼';
            }

            // Add syntax highlighting
            function highlightSyntax() {
                document.querySelectorAll('.dump-content pre').forEach(pre => {
                    let html = pre.innerHTML;
                    
                    // Highlight strings
                    html = html.replace(/(['\"])(.*?)\\1/g, '<span class=\"string\">$1$2$1</span>');
                    
                    // Highlight numbers
                    html = html.replace(/(\b\d+\.?\d*\b)/g, '<span class=\"number\">$1</span>');
                    
                    // Highlight booleans
                    html = html.replace(/\b(true|false)\b/g, '<span class=\"boolean\">$1</span>');
                    
                    // Highlight null
                    html = html.replace(/\b(null)\b/g, '<span class=\"null\">$1</span>');
                    
                    // Highlight array keys
                    html = html.replace(/(\=\>\s*)([^\[\{]+)(\\n|\s*\[|\s*\{)/g, '$1<span class=\"key\">$2</span>$3');
                    
                    // Highlight array indexes
                    html = html.replace(/(\[)(\d+)(\]\s*\=\>)/g, '$1<span class=\"index\">$2</span>$3');

                    html = html.replace(/\bobject\(([^)]+)\)/gi, '<span class=\"object\">object($1)</span>');
                    
                    pre.innerHTML = html;
                });
            }
            
            // Highlight after page loads
            window.addEventListener('DOMContentLoaded', highlightSyntax);
        </script>
        HTML;


        echo '</body></html>';
        exit;
    }

    /**
     * Dump - Advanced debugging function with type-aware output
     * 
     * Displays variables with detailed type information, syntax highlighting, and collapsible sections.
     * Integrates with Type class for consistent type handling and conversion display.
     *
     * @param mixed ...$args Variables to dump (accepts multiple arguments)
     */
    public static function dump(...$args): void
    {
        // Output styling and scripting
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Debug Dump</title>
            <meta charset="UTF-8">
            <style>
                body { 
                    background: #111; 
                    color: #f0f0f0; 
                    font-family: "Fira Code", "Consolas", monospace; 
                    padding: 20px; 
                    line-height: 1.5;
                }
                .dump-container { 
                    background: #1e1e1e; 
                    padding: 15px; 
                    border-radius: 5px; 
                    margin: 15px 0; 
                    box-shadow: 0 2px 10px rgba(0,0,0,0.5);
                }
                .dump-header { 
                    color: #ff6b6b; 
                    font-weight: bold; 
                    margin-bottom: 8px; 
                    cursor: pointer; 
                    padding: 8px 12px;
                    border-radius: 4px;
                    background: #252525;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    user-select: none;
                    transition: background 0.2s;
                }
                .dump-header:hover {
                    background: #2e2e2e;
                }
                .dump-content { 
                    white-space: pre-wrap; 
                    font-size: 14px; 
                    display: none; 
                    padding: 12px; 
                    border-left: 3px solid #ff6b6b; 
                    background: #252525;
                    margin-top: 8px;
                    border-radius: 0 0 4px 4px;
                    overflow-x: auto;
                }
                .dump-type {
                    color: #4dabf7;
                    font-size: 0.85em;
                    background: rgba(77, 171, 247, 0.1);
                    padding: 2px 6px;
                    border-radius: 3px;
                    margin-left: 8px;
                }
                .dump-size {
                    color: #94d82d;
                    font-size: 0.85em;
                }
                .file-info {
                    color: #adb5bd;
                    font-size: 0.85em;
                    margin: 20px 0;
                    padding: 10px;
                    background: #1e1e1e;
                    border-radius: 4px;
                }
                .debug-title {
                    color: #ff922b;
                    margin-bottom: 20px;
                    font-size: 1.5em;
                }
                /* Syntax highlighting - High contrast color scheme */
                .string    { color: #4EC9B0; }  /* Teal - stands out clearly */
                .number    { color: #569CD6; }  /* Soft blue - easy on eyes */
                .boolean   { color: #FF7B72; }  /* Coral red - pops for true/false */
                .null      { color: #C586C0; }  /* Muted purple - distinct */
                .key       { color: #9CDCFE; }  /* Light blue - good contrast */
                .index     { color: #858585; }  /* Medium gray - subtle for indexes */
                .object    { color: #FFA657; }  /* Vibrant orange - clear for objects */            </style>
        </head>
        <body>';

        // echo "<div class='debug-title'>Debug Dump</div>";

        // Get caller file information
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1] ?? null;

        if ($caller) {
            $file = $caller['file'] ?? 'unknown';
            $line = $caller['line'] ?? 'unknown';
            echo "<div class='file-info'>";
            echo "Called in: <strong>" . htmlspecialchars(basename($file)) . "</strong> on line <strong>{$line}</strong><br>";
            echo "<small>" . htmlspecialchars(dirname($file)) . "</small>";
            echo "</div>";
        }

        // Process each argument
        foreach ($args as $index => $arg) {
            $dumpId = 'dump_' . uniqid();
            $varType = Type::getType($arg);
            $sizeInfo = '';

            // Get size information
            if (is_array($arg)) {
                $sizeInfo = ' · <span class="dump-size">' . count($arg) . ' items</span>';
            } elseif (is_string($arg)) {
                $sizeInfo = ' · <span class="dump-size">' . strlen($arg) . ' chars</span>';
            } elseif (is_object($arg)) {
                $sizeInfo = ' · <span class="dump-size">' . count(get_object_vars($arg)) . ' properties</span>';
            }

            echo "<div class='dump-container'>";
            echo "<div class='dump-header' onclick='toggleDump(\"$dumpId\")' data-dump-id='$dumpId'>";
            echo "<span>Debug #" . ($index + 1) . " <span class='dump-type'>{$varType}</span>{$sizeInfo}</span>";
            echo "<span class='toggle-icon'>▼</span>";
            echo "</div>";

            echo "<div class='dump-content' id='$dumpId'><pre>";

            // Enhanced output using Type class information
            if (is_object($arg)) {
                echo htmlspecialchars(self::formatObject($arg));
            } elseif (is_array($arg)) {
                echo htmlspecialchars(self::formatArray($arg));
            } else {
                echo htmlspecialchars(self::formatValue($arg));
            }

            echo '</pre></div></div>';
        }
        echo "<script>
            function toggleDump(id) {
                const el = document.getElementById(id);
                el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
                
                const header = document.querySelector(`[data-dump-id=\"\${id}\"]`);
                const icon = header.querySelector('.toggle-icon');
                icon.textContent = el.style.display === 'none' ? '▶' : '▼';
            }

            // Add syntax highlighting
            function highlightSyntax() {
                document.querySelectorAll('.dump-content pre').forEach(pre => {
                    let html = pre.innerHTML;
                    
                    // Highlight strings
                    html = html.replace(/(['\"])(.*?)\\1/g, '<span class=\"string\">$1$2$1</span>');
                    
                    // Highlight numbers
                    html = html.replace(/(\b\d+\.?\d*\b)/g, '<span class=\"number\">$1</span>');
                    
                    // Highlight booleans
                    html = html.replace(/\b(true|false)\b/g, '<span class=\"boolean\">$1</span>');
                    
                    // Highlight null
                    html = html.replace(/\b(null)\b/g, '<span class=\"null\">$1</span>');
                    
                    // Highlight array keys
                    html = html.replace(/(\=\>\s*)([^\[\{]+)(\\n|\s*\[|\s*\{)/g, '$1<span class=\"key\">$2</span>$3');
                    
                    // Highlight array indexes
                    html = html.replace(/(\[)(\d+)(\]\s*\=\>)/g, '$1<span class=\"index\">$2</span>$3');

                    html = html.replace(/\bobject\(([^)]+)\)/gi, '<span class=\"object\">object($1)</span>');
                    
                    pre.innerHTML = html;
                });
            }
            
            // Highlight after page loads
            window.addEventListener('DOMContentLoaded', highlightSyntax);
        </script>";


        echo '</body></html>';
    }

    /**
     * Format object for display
     */
    private static function formatObject($object): string
    {
        $class = get_class($object);
        $output = "Object ($class) {\n";

        // Use Reflection to get all properties including private/protected
        $reflection = new ReflectionClass($object);
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            // $property->setAccessible(true);
            $name = $property->getName();
            $value = $property->getValue($object);

            $output .= "    [$name] => " . self::formatValue($value, 1) . "\n";
        }

        $output .= "}";
        return $output;
    }

    /**
     * Format array for display
     */
    private static function formatArray(array $array, int $indent = 0): string
    {
        if (empty($array)) {
            return '[]';
        }

        $indentStr = str_repeat('    ', $indent);
        $output = "[\n";

        foreach ($array as $key => $value) {
            $output .= $indentStr . "    [$key] => " . self::formatValue($value, $indent + 1) . "\n";
        }

        $output .= $indentStr . "]";
        return $output;
    }

    /**
     * Format single value for display
     */
    private static function formatValue($value, int $indent = 0): string
    {
        if (is_array($value)) {
            return self::formatArray($value, $indent);
        }

        if (is_object($value)) {
            return 'Object(' . get_class($value) . ')';
        }

        if (is_string($value)) {
            return '"' . addcslashes($value, "\0..\37\"\\") . '"';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        if (is_resource($value)) {
            return 'Resource #' . (int)$value;
        }

        return (string)$value;
    }
}

/**
 * Fluent interface for type checking
 */
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
            throw new RuntimeException("Type check failed for value: " . print_r($this->value, true));
        }
    }
}


