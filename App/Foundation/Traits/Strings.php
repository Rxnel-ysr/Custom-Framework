<?php

namespace App\Foundation\Traits;


trait Strings
{
    
    private static $camelCache;
    private static array $irregularPlurals = [
        'person' => 'people',
        'man' => 'men',
        'woman' => 'women',
        'child' => 'children',
        'tooth' => 'teeth',
        'foot' => 'feet',
        'mouse' => 'mice',
        'goose' => 'geese',
        'ox' => 'oxen',
        'die' => 'dice',
        'crisis' => 'crises',
        'analysis' => 'analyses',
        'basis' => 'bases',
        'criterion' => 'criteria',
        'phenomenon' => 'phenomena',
        'datum' => 'data',
        'medium' => 'media',
        'curriculum' => 'curricula',
        'bacterium' => 'bacteria',
        'stimulus' => 'stimuli',
        'alumnus' => 'alumni',
        'focus' => 'foci',
        'fungus' => 'fungi',
        'nucleus' => 'nuclei',
        'radius' => 'radii',
        'syllabus' => 'syllabi',
        'index' => 'indices',
        'matrix' => 'matrices',
        'vertex' => 'vertices',
        'appendix' => 'appendices',
        'life' => 'lives',
        'leaf' => 'leaves',
        'knife' => 'knives',
        'wife' => 'wives',
        'elf' => 'elves',
        'loaf' => 'loaves',
        'potato' => 'potatoes',
        'tomato' => 'tomatoes',
        'hero' => 'heroes',
        'echo' => 'echoes',
        'veto' => 'vetoes',
        'embargo' => 'embargoes',
        'buffalo' => 'buffaloes',
    ];

    private static array $uncountableNouns = [
        'advice', 'information', 'news', 'luggage', 'baggage', 'furniture',
        'equipment', 'money', 'music', 'art', 'love', 'water', 'air', 'rice',
        'sugar', 'bread', 'butter', 'cheese', 'coffee', 'tea', 'milk', 'wine',
        'beer', 'knowledge', 'progress', 'homework', 'research', 'evidence',
        'software', 'hardware', 'sheep', 'deer', 'fish', 'species', 'series',
        'means', 'offspring', 'moose', 'bison', 'trout', 'salmon', 'aircraft',
        'spacecraft', 'hovercraft',
    ];

    private static array $irregularPluralsReversed;
    private static array $uncountableNounsLookup;

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
    public static function limitString(string $value, int $limit = 100, string $end = '...', bool $preserveWords = false)
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
    public static function isMatch(string|iterable $pattern, string $value): bool
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

    /**
     * Initialize lookup arrays lazily
     */
    private static function initLookups(): void
    {
        if (!isset(self::$irregularPluralsReversed)) {
            self::$irregularPluralsReversed = array_flip(self::$irregularPlurals);
        }
        if (!isset(self::$uncountableNounsLookup)) {
            self::$uncountableNounsLookup = array_flip(self::$uncountableNouns);
        }
    }

    /**
     * Convert a singular word to its plural form
     */
    public static function singularToPlural(string $value, ?int $count = null): string
    {
        if ($count !== null && $count == 1) {
            return $value;
        }

        self::initLookups();
        $lowerValue = strtolower($value);
        
        // Early return for common cases
        if (isset(self::$irregularPlurals[$lowerValue])) {
            return self::maintainCase($value, self::$irregularPlurals[$lowerValue]);
        }
        
        if (isset(self::$uncountableNounsLookup[$lowerValue])) {
            return $value;
        }

        $length = strlen($lowerValue);
        $lastChar = $lowerValue[$length - 1] ?? '';
        $lastTwoChars = $length >= 2 ? substr($lowerValue, -2) : '';

        // Optimized switch with early returns
        // Words ending in -us (Latin origin)
        if ($lastTwoChars === 'us') {
            return substr($value, 0, -2) . self::maintainCase($value, 'i');
        }
        
        // Words ending in -is (Greek origin)
        if ($lastTwoChars === 'is') {
            return substr($value, 0, -2) . self::maintainCase($value, 'es');
        }
        
        // Words ending in -on (Greek origin)
        if ($lastTwoChars === 'on') {
            return substr($value, 0, -2) . self::maintainCase($value, 'a');
        }
        
        // Words ending in -um (Latin origin)
        if ($lastTwoChars === 'um') {
            return substr($value, 0, -2) . self::maintainCase($value, 'a');
        }
        
        // Words ending in -ex or -ix
        if ($lastTwoChars === 'ex' || $lastTwoChars === 'ix') {
            return substr($value, 0, -2) . self::maintainCase($value, 'ices');
        }
        
        // Words ending in -y preceded by consonant
        if ($lastChar === 'y' && $length >= 2) {
            $secondLast = $lowerValue[$length - 2];
            if ($secondLast !== 'a' && $secondLast !== 'e' && 
                $secondLast !== 'i' && $secondLast !== 'o' && $secondLast !== 'u') {
                return substr($value, 0, -1) . self::maintainCase($value, 'ies');
            }
        }
        
        // Words ending in -f or -fe
        if ($lastChar === 'f') {
            return substr($value, 0, -1) . self::maintainCase($value, 'ves');
        }
        if ($lastTwoChars === 'fe') {
            return substr($value, 0, -2) . self::maintainCase($value, 'ves');
        }
        
        // Words ending in -o
        if ($lastChar === 'o') {
            // Check special cases without array creation
            if ($lowerValue === 'photo' || $lowerValue === 'piano' || 
                $lowerValue === 'halo' || $lowerValue === 'zero' || 
                $lowerValue === 'studio') {
                return $value . 's';
            }
            return $value . 'es';
        }
        
        // Words ending in -s, -x, -z, -ss, -sh, -ch
        if ($lastChar === 's' || $lastChar === 'x' || $lastChar === 'z' ||
            $lastTwoChars === 'ss' || $lastTwoChars === 'sh' || $lastTwoChars === 'ch') {
            return $value . 'es';
        }
        
        // Default rule
        return $value . 's';
    }

    /**
     * Convert a plural word to its singular form
     */
    public static function pluralToSingular(string $value): string
    {
        self::initLookups();
        $lowerValue = strtolower($value);
        
        if (isset(self::$irregularPluralsReversed[$lowerValue])) {
            return self::maintainCase($value, self::$irregularPluralsReversed[$lowerValue]);
        }
        
        if (isset(self::$uncountableNounsLookup[$lowerValue])) {
            return $value;
        }

        $length = strlen($lowerValue);
        
        // Words ending in -i (from -us)
        if ($length >= 1 && $lowerValue[$length - 1] === 'i' && 
            ($length < 2 || substr($lowerValue, -2) !== 'ti')) {
            return substr($value, 0, -1) . self::maintainCase($value, 'us');
        }
        
        // Words ending in -a (from -um or -on)
        if ($length >= 1 && $lowerValue[$length - 1] === 'a') {
            return substr($value, 0, -1) . self::maintainCase($value, 'um');
        }
        
        // Words ending in -ices (from -ex or -ix)
        if ($length >= 4 && substr($lowerValue, -4) === 'ices') {
            return substr($value, 0, -4) . self::maintainCase($value, 'ex');
        }
        
        // Words ending in -ies
        if ($length >= 3 && substr($lowerValue, -3) === 'ies') {
            return substr($value, 0, -3) . self::maintainCase($value, 'y');
        }
        
        // Words ending in -ves
        if ($length >= 3 && substr($lowerValue, -3) === 'ves') {
            return substr($value, 0, -3) . self::maintainCase($value, 'f');
        }
        
        // Words ending in -es
        if ($length >= 2 && substr($lowerValue, -2) === 'es') {
            // Check for specific patterns
            if ($length >= 3 && 
                ($lowerValue[$length - 3] === 's' || 
                 $lowerValue[$length - 3] === 'x' || 
                 $lowerValue[$length - 3] === 'z')) {
                return substr($value, 0, -2);
            }
            
            if ($length >= 4) {
                $lastThree = substr($lowerValue, -4, 2);
                if ($lastThree === 'sh' || $lastThree === 'ch') {
                    return substr($value, 0, -2);
                }
                
                // Words ending in -es from -o
                if ($lowerValue[$length - 3] === 'o') {
                    return substr($value, 0, -2);
                }
            }
        }
        
        // Words ending in -s
        if ($length >= 1 && $lowerValue[$length - 1] === 's') {
            if ($length < 2 || 
                (substr($lowerValue, -2) !== 'ss' && 
                 substr($lowerValue, -2) !== 'us' && 
                 substr($lowerValue, -2) !== 'is')) {
                return substr($value, 0, -1);
            }
        }
        
        return $value;
    }

    /**
     * Check if a word is plural
     */
    public static function isPlural(string $value): bool
    {
        $lowerValue = strtolower($value);
        self::initLookups();
        
        // Check irregular plurals first (fast lookup)
        if (isset(self::$irregularPluralsReversed[$lowerValue])) {
            return true;
        }
        
        $singular = self::pluralToSingular($value);
        return strtolower($singular) !== $lowerValue;
    }

    /**
     * Check if a word is singular
     */
    public static function isSingular(string $value): bool
    {
        return !self::isPlural($value);
    }

    /**
     * Get plural form based on count
     */
    public static function pluralize(string $singular, int $count = 2): string
    {
        return $count == 1 ? $singular : self::singularToPlural($singular, $count);
    }

    /**
     * Format string with count
     */
    public static function countFormat(int $count, string $singular, ?string $plural = null): string
    {
        return $count == 1 ? 
            "{$count} {$singular}" : 
            "{$count} " . ($plural ?? self::singularToPlural($singular));
    }

    /**
     * Convert to title case
     */
    public static function title(string $value, ?array $smallWords = null): string
    {
        $defaultSmallWords = ['a', 'an', 'the', 'and', 'but', 'or', 'for', 'nor', 'on', 'at', 'to', 'by', 'in', 'of'];
        $smallWords = $smallWords ?? $defaultSmallWords;
        
        $words = explode(' ', $value);
        $count = count($words);
        $result = [];
        
        foreach ($words as $i => $word) {
            if ($i === 0 || $i === $count - 1) {
                $result[] = ucfirst($word);
            } else {
                $lowerWord = strtolower($word);
                $result[] = in_array($lowerWord, $smallWords, true) ? $lowerWord : ucfirst($word);
            }
        }
        
        return implode(' ', $result);
    }

    /**
     * Convert to PascalCase
     */
    public static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    /**
     * Convert to camelCase
     */
    public static function camel(string $value): string
    {
        if (isset(self::$camelCache[$value])) {
            return self::$camelCache[$value];
        }
        
        $result = lcfirst(self::studly($value));
        self::$camelCache[$value] = $result;
        
        return $result;
    }

    /**
     * Convert to words
     */
    public static function words(string $value): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $value));
    }

    /**
     * Maintain case pattern
     */
    private static function maintainCase(string $original, string $newEnding): string
    {
        // Check if all uppercase
        if (ctype_upper($original)) {
            return strtoupper($newEnding);
        }
        
        // Check if first letter is uppercase and rest is lowercase
        if ($original === ucfirst(strtolower($original))) {
            return ucfirst($newEnding);
        }
        
        return $newEnding;
    }

    /**
     * Generate possessive form
     */
    public static function possessive(string $value): string
    {
        $lastChar = strtolower($value)[strlen($value) - 1] ?? '';
        return ($lastChar === 's' || $lastChar === 'z' || $lastChar === 'x') ? 
            $value . "'" : 
            $value . "'s";
    }

    /**
     * Truncate string to word boundary
     */
    public static function truncateWords(string $value, int $limit = 100, string $end = '...'): string
    {
        $words = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        
        if (count($words) <= $limit) {
            return $value;
        }
        
        return implode(' ', array_slice($words, 0, $limit)) . $end;
    }

    /**
     * Generate random string
     */
    public static function random(int $length = 16, string $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'): string
    {
        $result = '';
        $max = strlen($characters) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[random_int(0, $max)];
        }
        
        return $result;
    }

    /**
     * Generate slug
     */
    public static function slug(string $value, string $separator = '-'): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/u', $separator, $value);
        $value = trim($value, $separator);
        return preg_replace('/' . preg_quote($separator, '/') . '+/u', $separator, $value);
    }

    /**
     * Mask string
     */
    public static function mask(string $value, int $visibleStart = 4, int $visibleEnd = 4, string $maskChar = '*'): string
    {
        $length = strlen($value);
        
        if ($length <= $visibleStart + $visibleEnd) {
            return $value;
        }
        
        return substr($value, 0, $visibleStart) . 
               str_repeat($maskChar, $length - $visibleStart - $visibleEnd) . 
               substr($value, -$visibleEnd);
    }

}
