<?php

declare(strict_types=1);

namespace App\Foundation\CLI;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * Argv parser and accessor.
 *
 * - Wraps $argv[] safely (no globals leaking).
 * - Provides type casting (bool, int, float, array, json).
 * - Supports options (--foo, -f) and positional args.
 * - Designed to be testable: can inject fake args in tests.
 */
class Argv implements IteratorAggregate
{
    private array $raw = [];
    public array $positionals = [];
    private array $options = [];
    private array $flags = [];
    private int $positionalIndex = 0;

    public function __construct(?array $args = null)
    {
        $this->raw = $args !== null ? array_values($args) : (array)array_slice($GLOBALS['argv'] ?? [], 1);
        $this->parse();
    }

    private function parse(): void
    {
        $args = $this->raw;
        $count = count($args);

        for ($i = 0; $i < $count; $i++) {
            $arg = $args[$i];

            // Long option --key=value or --key value
            if (str_starts_with($arg, '--')) {
                if (str_contains($arg, '=')) {
                    [$key, $val] = explode('=', substr($arg, 2), 2);
                    $this->options[$key] = $val;
                } elseif (isset($args[$i + 1]) && !str_starts_with($args[$i + 1], '-')) {
                    $this->options[substr($arg, 2)] = $args[$i + 1];
                    $i++;
                } else {
                    $this->options[substr($arg, 2)] = true; // flag style
                }
                continue;
            }

            // Short flags -rvf = -r -v -f
            if (str_starts_with($arg, '-') && strlen($arg) > 1) {
                foreach (str_split(substr($arg, 1)) as $ch) {
                    $this->flags[$ch] = true;
                }
                continue;
            }

            // Else positional
            $this->positionals[] = $arg;
        }
    }

    /**
     * Will mutate original positions, use with caution
     *
     * @param integer $offset
     * @return static
     */
    public function sliceMutatePositionals(int $offset = 0): static
    {
        $this->positionals = array_slice($this->positionals, $offset);
        return $this;
    }

    /**
     * Shift the first positional off and return it.
     * Mutates the internal positionals array.
     */
    public function shiftPositionals(): ?string
    {
        return array_shift($this->positionals);
    }

    public function getSlicedPositionals(int $offset = 0): array
    {
        return array_slice($this->positionals, $offset);
    }


    // === Accessors ===

    public function all(): array
    {
        return $this->raw;
    }


    public function positionals(): array
    {
        return $this->positionals;
    }

    public function getNextPositional(mixed $default = null): mixed
    {
        return $this->positionals[$this->positionalIndex++] ?? $default;
    }
    public function resetPositionalCursor(): void
    {
        $this->positionalIndex = 0;
    }

    public function peekNextPositional(mixed $default = null): mixed
    {
        return $this->positionals[$this->positionalIndex] ?? $default;
    }


    public function get(int $i, mixed $default = null): mixed
    {
        return $this->positionals[$i] ?? $default;
    }

    public function option(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    public function flag(string $name): bool
    {
        return $this->flags[$name] ?? false;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->positionals);
    }
}
