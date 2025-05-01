<?php

declare(strict_types=1);

namespace App\Foundation\Guard;

class Validator
{
    private array $fail_messages = [];
    private $data = [];
    private $rules = [];

    public function make(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;

        foreach ($this->rules as $field => $ruleSet) {
            $this->check($field, $this->data[$field] ?? null, $ruleSet);
        }

        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->fail_messages);
    }

    public function errors(): array
    {
        return $this->fail_messages;
    }

    private function check(string $field, mixed $data, string|array $rules)
    {
        $rules = is_array($rules) ? $rules : (strpos($rules, '|') !== false ? explode('|', $rules) : [$rules]);

        foreach ($rules as $rule) {
            $params = [];

            if (strpos($rule, ':') !== false) {
                [$rule, $param] = explode(':', $rule, 2);
                $params = [$param];
            }

            $func = match ($rule) {
                'required' => 'isNull',
                'min' => 'minChar',
                'max' => 'maxChar',
                'numeric' => 'isNumeric',
                'string' => 'isString',
                'array' => 'isArray',
                default => null,
            };

            if ($func !== null && method_exists(self::class, $func)) {
                call_user_func([self::class, $func], ...array_merge([$field, $data], $params));
            }
        }
    }

    private function minChar($field, $data, $minLength)
    {
        if (is_numeric($data)) {
            if ($data < $minLength) {
                $this->fail_messages[$field][] = "The $field must be at least $minLength.";
            }
        } elseif (is_string($data)) {
            if (mb_strlen($data) < $minLength) {
                $this->fail_messages[$field][] = "The $field must be at least $minLength characters.";
            }
        }
    }
    private function maxChar($field, $data, $maxLength)
    {
        if (is_numeric($data)) {
            if ($data > $maxLength) {
                $this->fail_messages[$field][] = "The $field must not exceed $maxLength.";
            }
        } elseif (is_string($data)) {
            if (mb_strlen($data) > $maxLength) {
                $this->fail_messages[$field][] = "The $field  must not exceed $maxLength characters.";
            }
        }
    }

    private function isNull($field, $data)
    {
        if (is_null($data) || (is_string($data) && trim($data) === '')) {
            $this->fail_messages[$field][] = "The $field is required.";
        }
    }

    private function isNumeric($field, $data)
    {
        if (!is_numeric($data)) {
            $this->fail_messages[$field][] = "The $field must be a number.";
        }
    }

    private function isString($field, $data)
    {
        if (!is_string($data)) {
            $this->fail_messages[$field][] = "The $field must be a string.";
        }
    }

    private function isArray($field, $data)
    {
        if (!is_array($data)) {
            $this->fail_messages[$field][] = "The $field must be an array.";
        }
    }
}
