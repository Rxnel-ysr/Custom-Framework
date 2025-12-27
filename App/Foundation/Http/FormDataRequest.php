<?php

namespace App\Foundation\Http;

use RuntimeException;

abstract class FormDataRequest extends Request
{
    private array $errors = [];
    private array $validatedData = [];
    private array $rules = [];
    private array $customMessages = [];
    private array $customAttributes = [];

    public function __construct()
    {
        parent::__construct();

        if (!$this->authorize()) {
            abort(403);
        }
        $this->validateForm($this->rules(), $this->messages(), $this->attributes());
    }

    abstract function authorize(): bool;

    abstract function rules(): array;

    public function messages(): array
    {
        return [];
    }
    public function attributes(): array
    {
        return [];
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
        // dd($field, $value);

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
