<?php

declare(strict_types=1);

namespace App\Foundation\CLI;

use App\Foundation\Traits\Macroable;
use ArrayIterator;
use Closure;
use IteratorAggregate;
use Traversable;

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

    /**
     * @param array|null   $args         argv input
     * @param array<string> $allowedOptions list of allowed long options (--foo)
     * @param array<string> $allowedFlags list of allowed flags (-f)
     * @param Closure|null $onUnknown Closure to be executed when there is unknown parameter, closure: function(string $param, string $type): void
     * @param Closure|null $condition Closure to be executed to check params based on criteria defined in closure, closure: function(self $argv): mixed
     */
    public function __construct(
        ?array $args = null,
        ?array $allowedOptions = null,
        ?array $allowedFlags = null,
        ?Closure $onUnknown = null,
        ?Closure $condition = null
    ) {
        $this->raw = $args !== null ? array_values($args) : (array)array_slice($GLOBALS['argv'] ?? [], 1);

        // Initialize arrays
        $this->allowedOptions = [];
        $this->allowedOptionsShortHand = [];
        $this->allowedFlags = [];
        $this->allowedFlagsShortHand = [];

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
                // Handle long options (--option)
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

                // Check if option is allowed
                if (!empty($this->allowedOptions) && !isset($this->allowedOptions[$key])) {
                    $this->triggerUnknown($key, 'option');
                } else {
                    $this->options[$key] = $val;
                }
                continue;
            }

            if (str_starts_with($arg, '-')) {
                // Handle short options/flags (-f, -abc, -o value)
                $key = substr($arg, 1);

                // Check if it's a shorthand option with value (-o=value or -o value)
                if (str_contains($key, '=')) {
                    [$key, $val] = explode('=', $key, 2);

                    // Check if it's a shorthand for an option
                    if (isset($this->allowedOptionsShortHand[$key])) {
                        $long = $this->allowedOptionsShortHand[$key];
                        $this->options[$long] = $val;
                    }
                    // Check if it's a shorthand for a flag
                    elseif (isset($this->allowedFlagsShortHand[$key])) {
                        $long = $this->allowedFlagsShortHand[$key];
                        $this->flags[$long] = true;
                    }
                    // Otherwise, treat as unknown
                    elseif (!empty($this->allowedFlags) && !isset($this->allowedFlags[$key])) {
                        $this->triggerUnknown($key, 'flag');
                    } else {
                        $this->flags[$key] = true;
                    }
                    continue;
                }

                // Check if it's a single character that matches a shorthand option
                if (isset($this->allowedOptionsShortHand[$key])) {
                    $long = $this->allowedOptionsShortHand[$key];
                    // Check if next argument is a value (not another option)
                    if (isset($args[$i + 1]) && !str_starts_with($args[$i + 1], '-')) {
                        $this->options[$long] = $args[++$i];
                    } else {
                        $this->options[$long] = true;
                    }
                    continue;
                }

                // Check if it's a single character that matches a shorthand flag
                if (isset($this->allowedFlagsShortHand[$key])) {
                    $long = $this->allowedFlagsShortHand[$key];
                    $this->flags[$long] = true;
                    continue;
                }

                // Handle combined flags (-abc)
                if (strlen($key) > 1) {
                    // Check if this is actually a combined flag or a single flag with value
                    $isCombinedFlag = true;
                    foreach (str_split($key) as $ch) {
                        if (!empty($this->allowedFlags) && !isset($this->allowedFlags[$ch])) {
                            $isCombinedFlag = false;
                            break;
                        }
                    }

                    if ($isCombinedFlag) {
                        foreach (str_split($key) as $ch) {
                            $this->flags[$ch] = true;
                        }
                    } else {
                        // Treat as single flag, might have value in next arg
                        if (!empty($this->allowedFlags) && isset($this->allowedFlags[$key])) {
                            $this->flags[$key] = true;
                        } else {
                            $this->triggerUnknown($key, 'flag');
                        }
                    }
                    continue;
                }

                // Single character flag
                if (empty($this->allowedFlags) || isset($this->allowedFlags[$key])) {
                    $this->flags[$key] = true;
                } else {
                    $this->triggerUnknown($key, 'flag');
                }
                continue;
            }

            // Handle positional arguments
            $this->positionals[] = str_starts_with($arg, '\\') ? substr($arg, 1) : $arg;
        }

        $this->executeConditionIfExists();
    }

    private function executeConditionIfExists(): mixed
    {
        return isset($this->condition) ? ($this->condition)($this) : true;
    }

    private function triggerUnknown(string $param, string $type): void
    {
        if ($this->onUnknown) {
            ($this->onUnknown)($param, $type);
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

    public function option(string $name, mixed $default = null): mixed
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
