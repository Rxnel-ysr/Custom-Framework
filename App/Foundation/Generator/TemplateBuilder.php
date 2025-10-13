<?php

namespace App\Foundation\Generator;

use Exception;

class TemplateBuilderException extends Exception {}

class TemplateBuilder
{
    private string $filepath;
    private array $rules;
    private string $varToReplace;
    private string $result;
    private string $placeholder;

    public function __construct(string $filepath, string $placeholder = '{{$i}}', string $varToReplace = '$i')
    {
        if (!is_file($filepath)) {
            throw new TemplateBuilderException("Template not found: {$filepath}");
        }

        $this->filepath = $filepath;
        $this->placeholder = $placeholder;
        $this->varToReplace = $varToReplace;
        $this->rules = [];
        $this->result = '';
    }

    public function genPlaceholder(array $keys)
    {
        return array_map(fn($item) => str_replace($this->varToReplace, $item, $this->placeholder), $keys);
    }

    public function placeholders(): array
    {
        return $this->genPlaceholder(array_keys($this->rules));
    }


    /**
     * Define key value pair rules
     * ___ 
     * in template if there is {{name}}, set name => someValue
     *
     * @param array<string, string> $rules
     * @return self
     */
    public function rules(array $rules): self
    {
        $this->rules = $rules;
        return $this;
    }

    /**
     * parse the template based on rules
     *
     * @return self
     */
    public function parse(): self
    {
        $string = file_get_contents($this->filepath);

        $parsed = str_replace(
            $this->genPlaceholder(array_keys($this->rules)),
            array_values($this->rules),
            $string
        );

        $this->result = $parsed;
        return $this;
    }

    /**
     * Save the result to a file
     *
     * @param string $filepath
     * @return integer|false
     */
    public function save(string $filepath): int|false
    {
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents($filepath, $this->result);
    }

    /**
     * Get result as string
     *
     * @return string
     */
    public function getResult(): string
    {
        return $this->result;
    }
}
