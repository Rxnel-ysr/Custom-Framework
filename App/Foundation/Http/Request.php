<?php

namespace App\Foundation\Http;

class Request
{
    /**
     * Retrieve an input value from $_REQUEST.
     *
     * @param string $key The key to retrieve.
     * @param mixed $default The default value if key is not found.
     * @return mixed The request input value or default.
     */
    public static function input($key, $default = null)
    {
        return $_REQUEST[$key] ?? $default;
    }

    /**
     * Retrieve all request inputs except the CSRF key.
     *
     * @return array The filtered request array.
     */
    public static function all()
    {
        return array_filter($_REQUEST, fn($key) => $key !== 'csrf_key', ARRAY_FILTER_USE_KEY);
    }

    /**
     * Extracts the Bearer token from the Authorization header.
     *
     * @return string|null The Bearer token if found, otherwise null.
     */
    public static function getBearerToken(): ?string
    {
        $headers = getallheaders();

        if (isset($headers['Authorization']) && preg_match('/Bearer\s+(.+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check if a request key exists.
     *
     * @param string $key The key to check.
     * @return bool True if the key exists, false otherwise.
     */
    public static function has($key)
    {
        return isset($_REQUEST[$key]);
    }

    /**
     * Retrieve a query string parameter from $_GET.
     *
     * @param string $key The key to retrieve.
     * @param mixed $default The default value if key is not found.
     * @return mixed The query parameter value or default.
     */
    public static function query($key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Retrieve a value from $_POST.
     *
     * @param string $key The key to retrieve.
     * @param mixed $default The default value if key is not found.
     * @return mixed The post parameter value or default.
     */
    public static function post($key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Get the request method (GET, POST, etc.).
     *
     * @return string The HTTP request method.
     */
    public static function method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public static function capture(): self
    {
        return new self;
    }

    public static function url()
    {
        return strtok($_SERVER['REQUEST_URI'], '?');
    }

    public static function urlQuery()
    {
        return $_SERVER['QUERY_STRING'];
    }

    /**
     * Check if the request method matches a given method.
     *
     * @param string $method The method to check (e.g., GET, POST).
     * @return bool True if it matches, false otherwise.
     */
    public static function isMethod($method)
    {
        return strtoupper($method) === $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Retrieve an uploaded file from $_FILES.
     *
     * @param string $key The file input name
     * @param string|null $option Consist of tmp, name, size, type, error
     * @return ($option is null ? array{tmp_name: string, name: string, size: int, type: string, error: int} : string|int|null) The uploaded file data or null if not found
     * @phpstan-return ($option is null ? array : string|int|null)
     * @psalm-return ($option is null ? array : string|int|null)
     */
    public static function file(string $key, ?string $option = null): mixed
    {
        $options = [
            'tmp' => 'tmp_name',
            'name' => 'name',
            'size' => 'size',
            'type' => 'type',
            'error' => 'error'
        ];
        return (!$option ? $_FILES[$key] : $_FILES[$key][$options[$option]]) ?? null;
    }

    /**
     * Retrieve a specific header value.
     *
     * @param string $key The header key to retrieve.
     * @return string|null The header value or null if not found.
     */
    public static function header($key)
    {
        $headers = getallheaders();
        return $headers[$key] ?? null;
    }

    /**
     * Validate request data against a set of rules.
     *
     * @param array $rules The validation rules (e.g., ['email' => 'required|email|max:255']).
     * @return bool|void Returns true if validation passes, otherwise halts execution with JSON errors.
     */
    public static function validate(array $rules)
    {
        $errors = [];

        foreach ($rules as $key => $rule) {
            $value = self::input($key);
            foreach (explode('|', $rule) as $r) {
                if ($r === 'required' && empty($value)) {
                    $errors[$key][] = "The $key field is required.";
                }
                if ($r === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$key][] = "The $key must be a valid email.";
                }
                if (str_starts_with($r, 'max:') && strlen($value) > explode(':', $r)[1]) {
                    $errors[$key][] = "The $key may not be greater than " . explode(':', $r)[1] . " characters.";
                }
            }
        }

        if (!empty($errors)) {
            die(json_encode(['errors' => $errors], JSON_PRETTY_PRINT));
        }
        return true;
    }

    /**
     * Retrieve JSON request body as an array.
     *
     * @return array The parsed JSON body or an empty array if invalid.
     */
    public static function json(): array
    {
        $rawInput = file_get_contents("php://input");
        $decoded = json_decode($rawInput, true);
        return is_array($decoded) ? $decoded : [];
    }
}


// class Request
// {
//     public static function input($key, $default = null)
//     {
//         return $_REQUEST[$key] ?? $default;
//     }

//     public static function all()
//     {
//         return array_filter($_REQUEST, fn($key) => $key !== 'csrf_key', ARRAY_FILTER_USE_KEY);
//     }

//     /**
//      * Extracts the Bearer token from the Authorization header.
//      *
//      * @return string|null The Bearer token if found, otherwise null.
//      */
//     public static function getBearerToken(): ?string
//     {
//         $headers = getallheaders();

//         if (isset($headers['Authorization']) && preg_match('/Bearer\s+(.+)/', $headers['Authorization'], $matches)) {
//             return $matches[1];
//         }

//         return null;
//     }

//     public static function has($key)
//     {
//         return isset($_REQUEST[$key]);
//     }

//     public static function query($key, $default = null)
//     {
//         return $_GET[$key] ?? $default;
//     }

//     public static function post($key, $default = null)
//     {
//         return $_POST[$key] ?? $default;
//     }

//     public static function method()
//     {
//         return $_SERVER['REQUEST_METHOD'];
//     }

//     public static function isMethod($method)
//     {
//         return strtoupper($method) === $_SERVER['REQUEST_METHOD'];
//     }

//     public static function file($key)
//     {
//         return $_FILES[$key] ?? null;
//     }

//     public static function header($key)
//     {
//         $headers = getallheaders();
//         return $headers[$key] ?? null;
//     }

//     public static function validate(array $rules)
//     {
//         $errors = [];

//         foreach ($rules as $key => $rule) {
//             $value = self::input($key);
//             foreach (explode('|', $rule) as $r) {
//                 if ($r === 'required' && empty($value)) {
//                     $errors[$key][] = "The $key field is required.";
//                 }
//                 if ($r === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
//                     $errors[$key][] = "The $key must be a valid email.";
//                 }
//                 if (str_starts_with($r, 'max:') && strlen($value) > explode(':', $r)[1]) {
//                     $errors[$key][] = "The $key may not be greater than " . explode(':', $r)[1] . " characters.";
//                 }
//             }
//         }

//         if (!empty($errors)) {
//             die(json_encode(['errors' => $errors], JSON_PRETTY_PRINT));
//         }
//         return true;
//     }
// }
