<?php

namespace App\Foundation\Traits;


trait Strings
{

    private static $camelCache;

    /**
     * Return the remainder of a string after the first occurrence of a given value.
     *
     * @param  string  $subject
     * @param  string  $search
     * @return string
     */
    public static function after($subject, $search)
    {
        return $search === '' ? $subject : array_reverse(explode($search, $subject, 2))[0];
    }

    /**
     * Return the remainder of a string after the last occurrence of a given value.
     *
     * @param  string  $subject
     * @param  string  $search
     * @return string
     */
    public static function afterLast($subject, $search)
    {
        if ($search === '') {
            return $subject;
        }

        $position = strrpos($subject, (string) $search);

        if ($position === false) {
            return $subject;
        }

        return substr($subject, $position + strlen($search));
    }

    /**
     * Get the portion of a string before the first occurrence of a given value.
     *
     * @param  string  $subject
     * @param  string  $search
     * @return string
     */
    public static function before($subject, $search)
    {
        if ($search === '') {
            return $subject;
        }

        $result = strstr($subject, (string) $search, true);

        return $result === false ? $subject : $result;
    }

    /**
     * Get the portion of a string before the last occurrence of a given value.
     *
     * @param  string  $subject
     * @param  string  $search
     * @return string
     */
    public static function beforeLast($subject, $search)
    {
        if ($search === '') {
            return $subject;
        }

        $pos = mb_strrpos($subject, $search);

        if ($pos === false) {
            return $subject;
        }

        return substr($subject, 0, $pos);
    }

    /**
     * Get the character at the specified index.
     *
     * @param  string  $subject
     * @param  int  $index
     * @return string|false
     */
    public static function charAt($subject, $index)
    {
        $length = mb_strlen($subject);

        if ($index < 0 ? $index < -$length : $index > $length - 1) {
            return false;
        }

        return mb_substr($subject, $index, 1);
    }

    /**
     * Remove the given string(s) if it exists at the start of the haystack.
     *
     * @param  string  $subject
     * @param  string|array  $needle
     * @return string
     */
    public static function chopStart($subject, $needle)
    {
        foreach ((array) $needle as $n) {
            if (str_starts_with($subject, $n)) {
                return substr($subject, strlen($n));
            }
        }

        return $subject;
    }

    /**
     * Remove the given string(s) if it exists at the end of the haystack.
     *
     * @param  string  $subject
     * @param  string|array  $needle
     * @return string
     */
    public static function chopEnd($subject, $needle)
    {
        foreach ((array) $needle as $n) {
            if (str_ends_with($subject, $n)) {
                return substr($subject, 0, -strlen($n));
            }
        }

        return $subject;
    }

    /**
     * Determine if a given string contains a given substring.
     *
     * @param  string  $haystack
     * @param  string|iterable<string>  $needles
     * @param  bool  $ignoreCase
     * @return bool
     */
    public static function contains($haystack, $needles, $ignoreCase = false)
    {
        if ($ignoreCase) {
            $haystack = mb_strtolower($haystack);
        }

        if (! is_iterable($needles)) {
            $needles = (array) $needles;
        }

        foreach ($needles as $needle) {
            if ($ignoreCase) {
                $needle = mb_strtolower($needle);
            }

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a given string contains all array values.
     *
     * @param  string  $haystack
     * @param  iterable<string>  $needles
     * @param  bool  $ignoreCase
     * @return bool
     */
    public static function containsAll($haystack, $needles, $ignoreCase = false)
    {
        foreach ($needles as $needle) {
            if (! static::contains($haystack, $needle, $ignoreCase)) {
                return false;
            }
        }

        return true;
    }


    /**
     * Determine if a given string doesn't contain a given substring.
     *
     * @param  string  $haystack
     * @param  string|iterable<string>  $needles
     * @param  bool  $ignoreCase
     * @return bool
     */
    public static function doesntContain($haystack, $needles, $ignoreCase = false)
    {
        return ! static::contains($haystack, $needles, $ignoreCase);
    }

    /**
     * Convert the case of a string.
     *
     * @param  string  $string
     * @param  int  $mode
     * @param  string|null  $encoding
     * @return string
     */
    public static function convertCase(string $string, int $mode = MB_CASE_FOLD, ?string $encoding = 'UTF-8')
    {
        return mb_convert_case($string, $mode, $encoding);
    }

    /**
     * Replace consecutive instances of a given character with a single character in the given string.
     *
     * @param  string  $string
     * @param  string  $character
     * @return string
     */
    public static function deduplicate(string $string, string $character = ' ')
    {
        return preg_replace('/' . preg_quote($character, '/') . '+/u', $character, $string);
    }

    /**
     * Determine if a given string ends with a given substring.
     *
     * @param  string  $haystack
     * @param  string|iterable<string>  $needles
     * @return bool
     */
    public static function endsWith($haystack, $needles)
    {
        if (! is_iterable($needles)) {
            $needles = (array) $needles;
        }

        if (is_null($haystack)) {
            return false;
        }

        foreach ($needles as $needle) {
            if ((string) $needle !== '' && str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cap a string with a single instance of a given value.
     *
     * @param  string  $value
     * @param  string  $cap
     * @return string
     */
    public static function finish($value, $cap)
    {
        $quoted = preg_quote($cap, '/');

        return preg_replace('/(?:' . $quoted . ')+$/u', '', $value) . $cap;
    }

    /**
     * Wrap the string with the given strings.
     *
     * @param  string  $value
     * @param  string  $before
     * @param  string|null  $after
     * @return string
     */
    public static function wrap($value, $before, $after = null)
    {
        return $before . $value . ($after ??= $before);
    }

    /**
     * Convert a string to kebab case.
     *
     * @param  string  $value
     * @return string
     */
    public static function kebab(string $value): string
    {
        return str_replace(' ', '-', mb_strtolower($value));
    }

    /**
     * Convert a string to snake case.
     *
     * @param  string  $value
     * @return string
     */
    public static function snake(string $value): string
    {
        return str_replace(' ', '_', mb_strtolower($value));
    }

    public static function mb_squish(string $value): string {
        return preg_replace('/\p{Zs}+/u',' ',trim($value));
    }
    public static function squish(string $value): string {
        return preg_replace('/[ \t]+/',' ',trim($value));
    }


    /**
     * Return the length of the given string.
     *
     * @param  string  $value
     * @param  string|null  $encoding
     * @return int
     */
    public static function length(string $value, ?string $encoding = null)
    {
        return mb_strlen($value, $encoding);
    }


    /**
     * Limit the number of characters in a string.
     *
     * @param  string  $value
     * @param  int  $limit
     * @param  string  $end
     * @param  bool  $preserveWords
     * @return string
     */
    public static function limit(string $value, int $limit = 100, string $end = '...', bool $preserveWords = false)
    {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }

        if (! $preserveWords) {
            return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
        }

        $value = trim(preg_replace('/[\n\r]+/', ' ', strip_tags($value)));

        $trimmed = rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8'));

        if (mb_substr($value, $limit, 1, 'UTF-8') === ' ') {
            return $trimmed . $end;
        }

        return preg_replace("/(.*)\s.*/", '$1', $trimmed) . $end;
    }

    /**
     * Convert the given string to lower-case.
     *
     * @param  string  $value
     * @return string
     */
    public static function lower(string $value)
    {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * Get the string matching the given pattern.
     *
     * @param  string  $pattern
     * @param  string  $subject
     * @return string
     */
    public static function match(string $pattern, string $subject): string
    {
        preg_match($pattern, $subject, $matches);

        if (! $matches) {
            return '';
        }

        return $matches[1] ?? $matches[0];
    }

    /**
     * Determine if a given string matches a given pattern.
     *
     * @param  string|iterable<string>  $pattern
     * @param  string  $value
     * @return bool
     */
    public static function isMatch(string $pattern, string $value): bool
    {
        $value = (string) $value;

        if (! is_iterable($pattern)) {
            $pattern = [$pattern];
        }

        foreach ($pattern as $pattern) {
            $pattern = (string) $pattern;

            if (preg_match($pattern, $value) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove all non-numeric characters from a string.
     *
     * @param  string  $value
     * @return string
     */
    public static function numbers(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * Remove all non-alphabetical characters from a string, with custom
     * 
     * @param  string  $value
     * @return string
     */
    public static function alpha(string $value, string $extra = ''): string
    {
        return preg_replace("/[^a-zA-Z$extra]/", '', $value);
    }

    /**
     * Remove all non-alpha-numeric characters from a string, with custom
     * 
     * @param  string  $value
     * @return string
     */
    public static function alphaNum(string $value, string $extra = ''): string
    {
        return preg_replace("/[^a-zA-Z0-9$extra]/", '', $value);
    }
}
