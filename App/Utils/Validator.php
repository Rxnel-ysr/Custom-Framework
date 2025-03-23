<?php
namespace App\utils\Guard;

class Validator
{
    private array $failMessage = [];
    private $data;
    private $rules;

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
        return !empty($this->failMessage);
    }

    public function errors(): array
    {
        return $this->failMessage;
    }

    private function check($field, $data, $rules)
    {
        $rules = strpos($rules, '|') !== false ? explode('|', $rules) : [$rules];

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
                $this->failMessage[$field][] = "The $field must be at least $minLength.";
            }
        } elseif (is_string($data)) {
            if (mb_strlen($data) < $minLength) {
                $this->failMessage[$field][] = "The $field must be at least $minLength characters.";
            }
        }
    }
    private function maxChar($field, $data, $maxLength)
    {
        if (is_numeric($data)) {
            if ($data > $maxLength) {
                $this->failMessage[$field][] = "The $field must not exceed $maxLength.";
            }
        } elseif (is_string($data)) {
            if (mb_strlen($data) > $maxLength) {
                $this->failMessage[$field][] = "The $field  must not exceed $maxLength characters.";
            }
        }
    }

    private function isNull($field, $data)
    {
        if (is_null($data) || (is_string($data) && trim($data) === '')) {
            $this->failMessage[$field][] = "The $field is required.";
        }
    }

    private function isNumeric($field, $data)
    {
        if (!is_numeric($data)) {
            $this->failMessage[$field][] = "The $field must be a number.";
        }
    }

    private function isString($field, $data)
    {
        if (!is_string($data)) {
            $this->failMessage[$field][] = "The $field must be a string.";
        }
    }

    private function isArray($field, $data)
    {
        if (!is_array($data)) {
            $this->failMessage[$field][] = "The $field must be an array.";
        }
    }
}
