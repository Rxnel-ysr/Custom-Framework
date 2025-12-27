<?php

declare(strict_types=1);

namespace App\Foundation\CLI;

use App\Foundation\Traits\Macroable;
use ArrayIterator;
use Closure;
use Exception;
use IteratorAggregate;
use Traversable;

class ArgvException extends Exception {}

class Argv implements IteratorAggregate
{
    use Macroable;

    private array $raw = [];
    private array $positionals = [];
    private array $options = [];
    private array $flags = [];
    private int $positionalIndex = 0;

    private ?array $allowedOptions;
    private ?array $allowedOptionsShortHand;
    private ?array $allowedFlagsShortHand;
    private ?array $allowedFlags;
    private ?Closure $onUnknown;
    private ?Closure $condition;
    private bool $strict = false;

    /**
     * @param array|null   $args         argv input
     * @param array<string> $allowedOptions list of allowed long options (--foo)
     * @param array<string> $allowedFlags list of allowed flags (-f)
     * @param Closure|null $onUnknown Closure to be executed when there is unknown parameter, closure: function(string $param, string $type): void
     * @param (Closure():bool)|null $condition Closure to be executed to check params based on criteria defined in closure, closure: function(self $argv): mixed
     */
    public function __construct(
        ?array $args = null,
        ?array $allowedOptions = null,
        ?array $allowedFlags = null,
        bool $strict = false,
        ?Closure $onUnknown = null,
        ?Closure $condition = null
    ) {
        $this->raw = $args !== null ? array_values($args) : (array)array_slice($GLOBALS['argv'] ?? [], 1);

        // Initialize arrays
        $this->allowedOptions = [];
        $this->allowedOptionsShortHand = [];
        $this->allowedFlags = [];
        $this->allowedFlagsShortHand = [];
        $this->strict = $strict;

        // Build whitelist maps for options
        if ($allowedOptions) {
            foreach ($allowedOptions as $opt) {
                if (is_array($opt)) {
                    $this->allowedOptions[$opt[0]] = true;
                    $this->allowedOptionsShortHand[$opt[1]] = $opt[0];
                } else {
                    $this->allowedOptions[$opt] = true;
                }
            }
        }

        // Build whitelist maps for flags
        if ($allowedFlags) {
            foreach ($allowedFlags as $opt) {
                if (is_array($opt)) {
                    $this->allowedFlags[$opt[0]] = true;
                    $this->allowedFlagsShortHand[$opt[1]] = $opt[0];
                } else {
                    $this->allowedFlags[$opt] = true;
                }
            }
        }

        $this->onUnknown      = $onUnknown;
        $this->condition      = $condition;

        $this->parse();
    }

    private function parse(): void
    {
        $args = $this->raw;
        $count = count($args);

        for ($i = 0; $i < $count; $i++) {
            $arg = $args[$i];

            if (str_starts_with($arg, '--')) {
                // Handle long options (--option or --option=value)
                $key = null;
                $val = true;

                if (str_contains($arg, '=')) {
                    [$key, $val] = explode('=', substr($arg, 2), 2);
                } elseif (isset($args[$i + 1]) && !str_starts_with($args[$i + 1], '-')) {
                    $key = substr($arg, 2);
                    $val = $args[++$i];
                } else {
                    $key = substr($arg, 2);
                }

                // Strict: must exist in allowedOptions
                if ($this->strict && !isset($this->allowedOptions[$key])) {
                    // echo 'Should be';
                    $this->triggerUnknown($key, 'option');
                    continue;
                }

                // Non-strict but has a whitelist
                if (!$this->strict && !empty($this->allowedOptions) && !isset($this->allowedOptions[$key])) {
                    // echo 'Should be';
                    $this->triggerUnknown($key, 'option');
                    continue;
                }

                $this->options[$key] = $val;
                continue;
            }

            if (str_starts_with($arg, '-')) {
                // Handle short options/flags (-f, -abc, -o value)
                $key = substr($arg, 1);

                // Check for -o=value shorthand
                if (str_contains($key, '=')) {
                    [$key, $val] = explode('=', $key, 2);

                    if (isset($this->allowedOptionsShortHand[$key])) {
                        $long = $this->allowedOptionsShortHand[$key];
                        $this->options[$long] = $val;
                    } elseif (isset($this->allowedFlagsShortHand[$key])) {
                        $long = $this->allowedFlagsShortHand[$key];
                        $this->flags[$long] = true;
                    } else {
                        if ($this->strict) {
                            // echo 'Should be';
                            $this->triggerUnknown($key, 'flag');
                        } else {
                            $this->flags[$key] = true;
                        }
                    }
                    continue;
                }

                // Short option shorthand (-o value)
                if (isset($this->allowedOptionsShortHand[$key])) {
                    $long = $this->allowedOptionsShortHand[$key];
                    if (isset($args[$i + 1]) && !str_starts_with($args[$i + 1], '-')) {
                        $this->options[$long] = $args[++$i];
                    } else {
                        $this->options[$long] = true;
                    }
                    continue;
                }

                // Short flag shorthand (-f)
                if (isset($this->allowedFlagsShortHand[$key])) {
                    $long = $this->allowedFlagsShortHand[$key];
                    $this->flags[$long] = true;
                    continue;
                }
                // echo "\n\nHERE\n\n";

                // Combined flags (-abc)
                if (strlen($key) > 1) {
                    $isCombinedFlag = true;
                    // echo "\n\nHERE\n\n";
                    // var_dump($this->strict);
                    // exit;
                    foreach (str_split($key) as $ch) {
                        if ($this->strict && !isset($this->allowedFlags[$ch])) {
                            $isCombinedFlag = false;
                            break;
                        }
                    }

                    if ($isCombinedFlag) {
                        foreach (str_split($key) as $ch) {
                            if ($this->strict && !isset($this->allowedFlags[$ch])) {
                                // echo 'Should be';
                                $this->triggerUnknown($ch, 'flag');
                            } else {
                                $this->flags[$ch] = true;
                            }
                        }
                    } else {
                        if ($this->strict && !isset($this->allowedFlags[$key])) {
                            // echo 'Should be';
                            $this->triggerUnknown($key, 'flag');
                        } else {
                            $this->flags[$key] = true;
                        }
                    }
                    continue;
                }

                // Single flag (-f)
                if ($this->strict) {
                    if (!isset($this->allowedFlags[$key])) {
                        // echo 'Should be';
                        $this->triggerUnknown($key, 'flag');
                        continue;
                    }
                } elseif (!empty($this->allowedFlags) && !isset($this->allowedFlags[$key])) {
                    // echo 'Should be';
                    $this->triggerUnknown($key, 'flag');
                    continue;
                }

                $this->flags[$key] = true;
                continue;
            }

            // Handle positional arguments
            $this->positionals[] = str_starts_with($arg, '\\') ? substr($arg, 1) : $arg;
        }

        if (!$this->executeConditionIfExists()) {
            throw new ArgvException("Condition fail to meet requirement");
        }
    }

    private function executeConditionIfExists(): bool
    {
        return isset($this->condition) ? ($this->condition)($this) : true;
    }

    private function triggerUnknown(string $param, string $type): void
    {
        if ($this->onUnknown) {
            ($this->onUnknown)($param, $type);
        } elseif ($this->strict) {
            throw new ArgvException("Unknown param '{$param}' with type of '{$type}'");
        }
    }

    public function onUnknown(): ?Closure
    {
        return $this->onUnknown;
    }

    // === Mutators ===
    public function sliceMutatePositionals(int $offset = 0): static
    {
        $this->positionals = array_slice($this->positionals, $offset);
        return $this;
    }

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

    public function option(string $name, mixed $default = null,): mixed
    {
        return $this->options[$name] ?? $default;
    }

    public function flag(string $name): bool
    {
        return $this->flags[$name] ?? false;
    }

    public function getAll(): array
    {
        return [
            'positionals' => $this->positionals,
            'options' => $this->options,
            'flags' => $this->flags
        ];
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->positionals);
    }
}
