<?php

namespace App\Foundation\Http;

use App\Foundation\Guard\Traits\ApiToken;
use App\Foundation\Database\Model;
use App\Support\Facades\Route;
use DateTime;
use DateTimeZone;

class Request
{
    /**
     * @var int Time when the Request was captured
     */
    protected int $timestamp;

    /**
     * @var array The stored request data
     */
    protected array $requestData;

    /**
     * @var array The stored GET data
     */
    protected array $queryData;

    /**
     * @var array The stored POST data
     */
    protected array $postData;

    /**
     * @var array The raw data from json
     */
    public array $json;

    /**
     * @var array The stored FILES data
     */
    protected array $filesData;

    /**
     * @var array The stored headers
     */
    protected array $headers;

    /**
     * @var string The request method
     */
    protected string $method;

    /**
     * @var string The request URI
     */
    protected string $uri;

    /**
     * @var string The request URI with query string
     */
    protected string $fullUri;

    /**
     * @var string The query string
     */
    protected string $queryString;

    /**
     * @var string $origin
     */
    protected string $origin;

    protected ?Model $user = null;
    protected ?ApiToken $apiToken = null;

    /**
     * Request constructor.
     * Captures all request data at the time of instantiation.
     */
    public function __construct()
    {
        $this->timestamp = $_SERVER['REQUEST_TIME'];
        $this->requestData = $_REQUEST;
        $this->queryData = $_GET;
        $this->postData = $_POST;
        $this->filesData = $_FILES;
        $this->headers = array_change_key_case(getallheaders());
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->fullUri = $_SERVER['REQUEST_URI'] ?? '';
        $this->uri = strstr($this->fullUri, '?', true) ?: $this->fullUri;
        $this->queryString = $_SERVER['QUERY_STRING'] ?? '';
        $this->origin = $this->headers['HTTP_ORIGIN'] ?? $this->headers['referer'] ?? ((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['REMOTE_ADDR'] . ':' . $_SERVER['REMOTE_PORT']);
        $this->json = $this->getJson();
    }

    /**
     * Get the route parameter
     *
     * @param string $parameter
     * @return mixed
     */
    public function parameter(string $parameter): mixed
    {
        return Route::parameter($parameter);
    }

    public function wantsJson()
    {
        return $this->header('Accept') === 'application/json';
    }

    public function hasHeader(string $key): bool
    {
        return isset($this->headers[$key]);
    }

    public function user()
    {
        if ($this->user) {
            return $this->user;
        }
        if ($token = $this->bearer()) {
            return $this->user = $token->owner();
        }
        return null;
    }

    public function bearer()
    {
        if ($this->apiToken) {
            return $this->apiToken;
        }

        if ($token = $this->bearerToken()) {
            return $this->apiToken = ApiToken::verifyToken($token);
        }
        return null;
    }

    /**
     * Snapshot of current request
     *
     * @return (array{url: string, method: string, headers: array, request_data: array, query: array, query_string: string, post: array, files: array})
     */
    public function snapshot()
    {
        return [
            'time'          => $this->timestamp,
            'url'           => $this->url(),
            'uri'           => $this->uri,
            'method'        => $this->method,
            'headers'       => $this->headers,
            'request_data'  => $this->requestData,
            'query'         => $this->queryData,
            'query_string'  => $this->queryString,
            'post'          => $this->postData,
            'files'         => $this->filesData,
        ];
    }
    /**
     * Get time whn request was made, returned as UNIX
     *
     * @param string|null $format Format the result
     * @param string|null $timezone Timezone to choose
     * @return string|integer
     */
    public function time(?string $format = null, ?string $timezone = null): string|int
    {
        if (is_null($format)) {
            return $this->timestamp;
        }

        $dt = new DateTime("@$this->timestamp");

        if (!is_null($timezone)) {
            $dt->setTimezone(new DateTimeZone($timezone));
        }

        return $dt->format($format);
    }

    /**
     * Get origin of request
     *
     * @return string
     */
    public function origin()
    {
        return $this->origin;
    }


    /**
     * Create a new request instance.
     *
     * @return self
     */
    public static function capture(): self
    {
        return new self();
    }

    /**
     * Retrieve an input value from the request.
     *
     * @param string $key The key to retrieve.
     * @param mixed $default The default value if key is not found.
     * @return mixed The request input value or default.
     */
    public function input($key, $default = null): mixed
    {
        return $this->isJson() ? ($this->json[$key] ?? $default) : ($this->requestData[$key] ?? $default);
    }

    /**
     * Retrieve all request inputs except the CSRF key.
     *
     * @return array The filtered request array.
     */
    public function all()
    {
        return array_filter($this->isJson() ? $this->json : $this->requestData, fn($key) => $key !== 'csrf_key', ARRAY_FILTER_USE_KEY);
    }

    public function except(array $keys)
    {
        return array_filter($this->all(), fn($key) => !in_array($key, $keys), ARRAY_FILTER_USE_KEY);
    }

    public function only(array $keys)
    {
        return array_filter($this->all(), fn($key) => in_array($key, $keys), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Extracts the Bearer token from the Authorization header.
     *
     * @return string|null The Bearer token if found, otherwise null.
     */
    public function bearerToken(): ?string
    {
        if (isset($this->headers['Authorization']) && preg_match('/Bearer\s+(.+)/', $this->headers['Authorization'], $matches)) {
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
    public function has($key): bool
    {
        return $this->isJson() ? isset($this->json[$key]) : isset($this->requestData[$key]);
    }

    /**
     * Retrieve a query string parameter.
     *
     * @param string $key The key to retrieve.
     * @param mixed $default The default value if key is not found.
     * @return mixed The query parameter value or default.
     */
    public function query($key, $default = null): mixed
    {
        return $this->queryData[$key] ?? $default;
    }

    /**
     * Retrieve a value from POST data.
     *
     * @param string $key The key to retrieve.
     * @param mixed $default The default value if key is not found.
     * @return mixed The post parameter value or default.
     */
    public function post($key, $default = null): mixed
    {
        return $this->isJson() ? $this->json[$key] ?? $default : ($this->postData[$key] ?? $default);
    }

    /**
     * Get the request method (GET, POST, etc.).
     *
     * @return string The HTTP request method.
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Get the request URI with query string.
     *
     * @return string
     */
    public function fullUri(): string
    {
        return $this->fullUri;
    }

    /**
     * Get the request URI without query string.
     *
     * @return string
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * Get the server base 
     *
     * @return string
     */
    public function domain(): string
    {
        $protocol =
            !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
            ? 'https'
            : 'http';

        $host = $_SERVER['HTTP_HOST'];

        return $protocol . '://' . $host;
    }

    /**
     * Get the full request URL 
     *
     * @return string
     */
    public function url(): string
    {
        return $this->domain() . $this->fullUri;
    }

    /**
     * Get the query string.
     *
     * @return string
     */
    public function urlQuery(): string
    {
        return $this->queryString;
    }

    /**
     * Check if the request method matches a given method.
     *
     * @param string $method The method to check (e.g., GET, POST).
     * @return bool True if it matches, false otherwise.
     */
    public function isMethod($method): bool
    {
        return strtoupper($method) === $this->method;
    }

    /**
     * Retrieve an uploaded file.
     *
     * @param string $key The file input name
     * @param string|null $option Consist of tmp, name, size, type, error
     * @return mixed The uploaded file data or null if not found
     */
    public function file(string $key, ?string $option = null): mixed
    {
        $options = [
            'tmp' => 'tmp_name',
            'name' => 'name',
            'size' => 'size',
            'type' => 'type',
            'error' => 'error'
        ];
        return (!$option ? $this->filesData[$key] : $this->filesData[$key][$options[$option]]) ?? null;
    }

    /**
     * Retrieve JSON request body as an array.
     *
     * @return array The parsed JSON or default value.
     */
    public function json($key = null, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return $this->json;
        }
        return $this->json[$key] ?? $default;
    }

    /**
     * Retrieve a specific header value.
     *
     * @param string $key The header key to retrieve.
     * @return string|null The header value or null if not found.
     */
    public function header($key): ?string
    {
        return $this->headers[$key] ?? null;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Validate request data against a set of rules.
     *
     * @param array $rules The validation rules (e.g., ['email' => 'required|email|max:255']).
     * @return bool Returns true if validation passes, otherwise false.
     */
    public function validate(array $rules): bool
    {
        $errors = [];

        foreach ($rules as $key => $rule) {
            $value = $this->input($key);
            foreach (is_string($rule) ? explode('|', $rule) : $rule as $r) {
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
            return false;
            // die(json_encode(['errors' => $errors], JSON_PRETTY_PRINT));
        }
        return true;
    }

    /**
     * Parse JSON request body as an array.
     *
     * @return array The parsed JSON body or an empty array if invalid.
     */
    private function getJson(): array
    {
        $rawInput = file_get_contents("php://input");
        $decoded = json_decode($rawInput, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Determine the request are made from raw json or primitive
     *
     * @return boolean
     */
    public function isJson(): bool
    {
        return $this->header('content-type') === 'application/json';
    }

    public function __get($name): mixed
    {
        return $this->input($name);
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
