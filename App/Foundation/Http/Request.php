<?php

namespace App\Foundation\Http;

use App\Foundation\Guard\Traits\ApiToken;
use App\Foundation\Database\Model;
use App\Support\Facades\Route;
use DateTime;
use DateTimeZone;
use RuntimeException;

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
     * @var ?string $origin
     */
    protected ?string $origin;

    protected ?Model $user = null;
    protected ?ApiToken $apiToken = null;

    /**
     * @var array Validation errors keyed by field name
     */
    protected array $errors = [];

    /**
     * @var array Data that has passed validation
     */
    protected array $validatedData = [];

    /**
     * @var array The active validation rules
     */
    protected array $rules = [];

    /**
     * @var array Custom validation error messages
     */
    protected array $customMessages = [];

    /**
     * @var array Custom attribute display names
     */
    protected array $customAttributes = [];

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
        $this->headers =  function_exists('getallheaders') ? array_change_key_case(\getallheaders()) : self::fallbackHeaders();
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->fullUri = $_SERVER['REQUEST_URI'] ?? '';
        $this->uri = strstr($this->fullUri, '?', true) ?: $this->fullUri;
        $this->queryString = $_SERVER['QUERY_STRING'] ?? '';
        $this->origin = $this->headers['HTTP_ORIGIN'] ?? $this->headers['origin'] ?? null;
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
    private static function fallbackHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }

        foreach (['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'] as $key) {
            if (isset($_SERVER[$key])) {
                $headers[strtolower(str_replace('_', '-', $key))] = $_SERVER[$key];
            }
        }

        return $headers;
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

    // ============================================
    // VALIDATION ENGINE
    // ============================================

    /**
     * Validate request data against a set of rules.
     *
     * @param array $rules The validation rules (e.g., ['email' => 'required|email|max:255']).
     * @return array The validated data.
     */
    public function validate(array $rules, array $messages = [], array $attributes = []): array
    {
        return $this->validateForm($rules, $messages, $attributes);
    }

    /**
     * Validate the request against given rules
     */
    public function validateForm(array $rules, array $messages = [], array $attributes = []): array
    {
        $this->rules = $rules;
        $this->customMessages = $messages;
        $this->customAttributes = $attributes;

        foreach ($this->rules as $field => $ruleSet) {
            $value = $this->method() == 'POST' ? $this->post($field, $this->query($field, null))  : $this->input($field);
            $this->validateField($field, $value, $ruleSet);
        }

        if ($this->fails()) {
            $this->handleValidationFailure();
        }

        return $this->validatedData;
    }

    /**
     * Check if validation fails
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if validation passes
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Get validation errors
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function ungroupedErrors(): array
    {
        $flat = [];

        foreach ($this->errors() as $messages) {
            foreach ($messages as $message) {
                $flat[] = $message;
            }
        }

        return $flat;
    }

    /**
     * Get first error for a field
     */
    public function error(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Get validated data
     */
    public function validated(): array
    {
        return $this->validatedData;
    }

    /**
     * Validate a single field
     */
    private function validateField(string $field, mixed $value, string|array $rules): void
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        $isRequired = in_array('required', $rules);

        // Skip validation if field is not required and empty
        if (!$isRequired && $this->isEmpty($value)) {
            return;
        }

        foreach ($rules as $rule) {
            $this->applyRule($field, $value, $rule);
        }

        // If field passed all rules, add to validated data
        if (!isset($this->errors[$field])) {
            $this->validatedData[$field] = $value;
        }
    }

    /**
     * Apply a single validation rule
     */
    private function applyRule(string $field, mixed $value, string $rule): void
    {
        $parameters = [];

        if (str_contains($rule, ':')) {
            [$ruleName, $paramString] = explode(':', $rule, 2);
            $parameters = explode(',', $paramString);
        } else {
            $ruleName = $rule;
        }

        $methodName = 'validate' . ucfirst($ruleName);

        if (method_exists($this, $methodName)) {
            $this->$methodName($field, $value, ...$parameters);
        } else {
            throw new RuntimeException("Validation rule '$ruleName' is not supported.");
        }
    }

    /**
     * Check if value is empty
     */
    private function isEmpty(mixed $value): bool
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        if (is_array($value) && empty($value)) {
            return true;
        }

        return false;
    }

    /**
     * Get field display name
     */
    private function getFieldName(string $field): string
    {
        return $this->customAttributes[$field] ?? str_replace('_', ' ', $field);
    }

    /**
     * Add error message
     */
    private function addError(string $field, string $criteria, string $message): void
    {
        if (isset($this->customMessages[$field . '.' . $criteria])) {
            $message = $this->customMessages[$field . '.' . $criteria];
        } elseif (isset($this->customMessages[$field])) {
            $message = $this->customMessages[$field];
        }

        $fieldName = $this->getFieldName($field);
        $message = str_replace(':attribute', $fieldName, $message);

        $this->errors[$field][] = $message;
    }

    /**
     * Handle validation failure
     */
    protected function handleValidationFailure(): void
    {
        // You can customize this behavior
        // For example, throw an exception, redirect back with errors, etc.
        throw new RuntimeException('Validation failed: ' . json_encode($this->errors));

        // Or for web applications, you might want to:
        // session()->flash('errors', $this->errors);
        // header('Location: ' . $_SERVER['HTTP_REFERER']);
        // exit;
    }

    // ============================================
    // VALIDATION RULES
    // ============================================

    private function validateRequired(string $field, mixed $value): void
    {
        if ($this->isEmpty($value)) {
            $this->addError($field, 'required', "The :attribute field is required.");
        }
    }

    private function validateString(string $field, mixed $value): void
    {
        if (!$this->isEmpty($value) && !is_string($value)) {
            $this->addError($field, 'string', "The :attribute must be a string.");
        }
    }

    private function validateNumeric(string $field, mixed $value): void
    {
        if (!$this->isEmpty($value) && !is_numeric($value)) {
            $this->addError($field, 'numeric', "The :attribute must be a number.");
        }
    }

    private function validateInteger(string $field, mixed $value): void
    {
        if (!$this->isEmpty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->addError($field, 'integer', "The :attribute must be an integer.");
        }
    }

    private function validateEmail(string $field, mixed $value): void
    {
        if (!$this->isEmpty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email', "The :attribute must be a valid email address.");
        }
    }

    private function validateMin(string $field, mixed $value, string $min): void
    {
        if ($this->isEmpty($value)) return;

        if (is_numeric($value)) {
            if ($value < (int)$min) {
                $this->addError($field, 'min', "The :attribute must be at least $min.");
            }
        } elseif (is_string($value)) {
            if (mb_strlen($value) < (int)$min) {
                $this->addError($field, 'min', "The :attribute must be at least $min characters.");
            }
        } elseif (is_array($value)) {
            if (count($value) < (int)$min) {
                $this->addError($field, 'min', "The :attribute must have at least $min items.");
            }
        }
    }

    private function validateMax(string $field, mixed $value, string $max): void
    {
        if ($this->isEmpty($value)) return;

        if (is_numeric($value)) {
            if ($value > (int)$max) {
                $this->addError($field, 'max', "The :attribute must not exceed $max.");
            }
        } elseif (is_string($value)) {
            if (mb_strlen($value) > (int)$max) {
                $this->addError($field, 'max', "The :attribute must not exceed $max characters.");
            }
        } elseif (is_array($value)) {
            if (count($value) > (int)$max) {
                $this->addError($field, 'max', "The :attribute must not have more than $max items.");
            }
        }
    }

    private function validateBetween(string $field, mixed $value, string $min, string $max): void
    {
        if ($this->isEmpty($value)) return;

        if (is_numeric($value)) {
            if ($value < (int)$min || $value > (int)$max) {
                $this->addError($field, 'between', "The :attribute must be between $min and $max.");
            }
        } elseif (is_string($value)) {
            $length = mb_strlen($value);
            if ($length < (int)$min || $length > (int)$max) {
                $this->addError($field, 'between', "The :attribute must be between $min and $max characters.");
            }
        }
    }

    private function validateIn(string $field, mixed $value, ...$allowed): void
    {
        if ($this->isEmpty($value)) return;

        if (!in_array($value, $allowed)) {
            $allowedValues = implode(', ', $allowed);
            $this->addError($field, 'in', "The :attribute must be one of: $allowedValues.");
        }
    }

    private function validateNotIn(string $field, mixed $value, ...$disallowed): void
    {
        if ($this->isEmpty($value)) return;

        if (in_array($value, $disallowed)) {
            $disallowedValues = implode(', ', $disallowed);
            $this->addError($field, 'notIn', "The :attribute must not be one of: $disallowedValues.");
        }
    }

    private function validateBoolean(string $field, mixed $value): void
    {
        if (!$this->isEmpty($value) && !in_array($value, [true, false, 0, 1, '0', '1'], true)) {
            $this->addError($field, 'boolean', "The :attribute field must be true or false.");
        }
    }

    private function validateArray(string $field, mixed $value): void
    {
        if (!$this->isEmpty($value) && !is_array($value)) {
            $this->addError($field, 'array', "The :attribute must be an array.");
        }
    }

    private function validateUrl(string $field, mixed $value): void
    {
        if (!$this->isEmpty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'url', "The :attribute must be a valid URL.");
        }
    }

    private function validateDate(string $field, mixed $value): void
    {
        if ($this->isEmpty($value)) return;

        if (!strtotime($value)) {
            $this->addError($field, 'date', "The :attribute must be a valid date.");
        }
    }

    private function validateRegex(string $field, mixed $value, string $pattern): void
    {
        if ($this->isEmpty($value)) return;

        if (!preg_match($pattern, $value)) {
            $this->addError($field, 'regex', "The :attribute format is invalid.");
        }
    }

    private function validateSame(string $field, mixed $value, string $otherField): void
    {
        if ($this->isEmpty($value)) return;

        $otherValue = $this->input($otherField);
        if ($value !== $otherValue) {
            $otherName = $this->getFieldName($otherField);
            $this->addError($field, 'same', "The :attribute must match $otherName.");
        }
    }

    private function validateDifferent(string $field, mixed $value, string $otherField): void
    {
        if ($this->isEmpty($value)) return;

        $otherValue = $this->input($otherField);
        if ($value === $otherValue) {
            $otherName = $this->getFieldName($otherField);
            $this->addError($field, 'different', "The :attribute must be different from $otherName.");
        }
    }

    private function validateAccepted(string $field, mixed $value): void
    {
        $accepted = ['yes', 'on', '1', 1, true];

        if (!in_array($value, $accepted, true)) {
            $this->addError($field, 'accepted', "The :attribute must be accepted.");
        }
    }

    private function validateConfirmed(string $field, mixed $value): void
    {
        $confirmationField = $field . '_confirmation';
        $confirmationValue = $this->input($confirmationField);

        if ($value !== $confirmationValue) {
            $this->addError($field, 'confirmed', "The :attribute confirmation does not match.");
        }
    }
}
