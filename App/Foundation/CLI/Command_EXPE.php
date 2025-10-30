<?php

declare(strict_types=1);

namespace Experimental\App\Foundation\CLI;

use App\Foundation\CLI\Argv;
use App\Foundation\Manager\InstanceManager;
use App\Foundation\Traits\Macroable;
use Throwable;
use Closure;

/**
 * Experimental command
 * 
 * @depends App\Foundation\Traits\Macroable
 */
class Command
{
    use Macroable;

    protected static Argv $argv;
    private static array $command = [];
    private static array $aliases = [];

    /**
     * Register new command
     *
     * @param string $trigger
     * @param string|callable(Argv $args) $command
     * @return CommandBuilder
     */
    public static function register(string $trigger, string|callable|array $command): CommandBuilder
    {
        $entry = [
            'dependencies' => [],
            'command'      => $command,
            'params'       => [],
            'help'         => '',
        ];

        self::$command[$trigger][] = $entry;
        $order = count(self::$command[$trigger]) - 1;

        return new CommandBuilder($trigger, $order);
    }

    public static function invoke(string $trigger, array $params)
    {
        $command = self::$aliases[$trigger] ?? self::$command[$trigger][0] ?? null;
        if (is_null($command)) {
            echo "Command not found: {$trigger}\n\nAvailable commands:\n";
            return self::showHelp(false);
        }
        /** @var array{dependencies:array<string,array|Closure|object>, command:string|Closure} $command */

        foreach ($command['dependencies'] as $alias => $dependency) {
            if (is_string($dependency) && strpos($dependency, '.php') === false) {
                if (!is_numeric($alias)) {
                    class_alias($dependency, $alias);
                }
            } elseif (is_array($dependency)) {
                $instance = is_object($dependency[0]) ? $dependency[0] : new $dependency[0];
                $instance->$dependency[1];
            } else if (is_callable($dependency)) {
                call_user_func($dependency);
            } else {
                require_once $dependency;
            }
        }

        foreach ($command['params'] as $param) {
            if ($isTypeDefined = strpos($param, ':') !== false) {
                [$param, $type] = explode(':', $param, 2);
            }

            if (isset($params[$param])) {
                $params[$param] = $isTypeDefined ? self::cast($params[$param], $type, null) : $params[$param];
            }
        }

        if (is_array($command['command']) && count($command['command']) === 2) {
            $instance = new $command['command'][0];
            $command['command'] = [$instance, $command['command'][1]];
        }

        return callFuncWithParams($command['command'], $params, true, true);
    }

    public static function update(string $trigger, int $order, array $updates): void
    {
        self::$command[$trigger][$order] = array_merge(
            self::$command[$trigger][$order],
            $updates
        );
    }

    public static function addAlias(string $alias, string $trigger, int $order): void
    {
        self::$aliases[$alias] = &self::$command[$trigger][$order];
    }


    /**
     * Execute a registered command.
     *
     * @param string $trigger The command trigger to execute.
     * @return mixed The result of the command execution.
     */
    public static function execute(?string $trigger)
    {
        try {
            if (is_null($trigger)) {
                return self::showHelp();
            }
            $command = self::$aliases[$trigger] ?? self::$command[$trigger][0] ?? null;
            if (is_null($command)) {
                echo "Command not found: {$trigger}\n\nAvailable commands:\n";
                return self::showHelp(false);
            }
            /** @var array{dependencies:array<string,array|Closure|object>, command:string|Closure} $command */

            foreach ($command['dependencies'] as $alias => $dependency) {
                if (is_string($dependency) && strpos($dependency, '.php') === false) {
                    if (!is_numeric($alias)) {
                        class_alias($dependency, $alias);
                    }
                } elseif (is_array($dependency)) {
                    $instance = is_object($dependency[0]) ? $dependency[0] : new $dependency[0];
                    $instance->$dependency[1];
                } else if (is_callable($dependency)) {
                    call_user_func($dependency);
                } else {
                    require_once $dependency;
                }
            }

            // if (is_callable($command['command'])) {
            //     $params = [];
            //     foreach ($command['params'] as $no => $param) {
            //         $params[$param] = self::parameter($no + 2);
            //     }

            //     $bag = new ParamBag($params);
            //     $callable = $command['command']->bindTo($bag, ParamBag::class);

            //     $callable();

            // }

            $params = [];
            $unusedPositionals = [];

            foreach ($command['params'] as $param) {
                if ($isTypeDefined = strpos($param, ':') !== false) {
                    [$param, $type] = explode(':', $param, 2);
                }

                $value = self::$argv->option($param, null);
                if ($value !== null) {
                    $params[$param] = $isTypeDefined ? self::cast($value, $type, null) : $value;
                } else {
                    $unusedPositionals[] = $param;
                }
            }

            // fill unused params with remaining positionals
            foreach ($unusedPositionals as $param) {
                $pos = self::$argv->getNextPositional();
                if ($pos !== null) {
                    $params[$param] = $pos;
                }
            }

            if (is_array($command['command']) && count($command['command']) === 2) {
                $instance = new $command['command'][0];
                $command['command'] = [$instance, $command['command'][1]];
            }

            if (is_callable($command['command'])) {
                return callFuncWithParams($command['command'], $params, true, true);
            } elseif (is_string($command['command'])) {
                return shell_exec($command['command']);
            }
        } catch (Throwable $e) {
            echo "Error running command: {$e->getMessage()}";
            return 1;
        }
    }


    /**
     * Standby and execute a default or help command.
     *
     * @return mixed
     */
    public static function standBy(Argv $argv)
    {
        self::$argv = $argv;
        InstanceManager::setInstance(Argv::class, $argv);
        return self::execute($argv->shiftPositionals());
    }

    /**
     * Display help information for all registered commands.
     *
     * @return void
     */
    public static function showHelp(bool $withIntro = true)
    {
        if ($withIntro) {
            echo "Built-in command handler for this custom framework\n\nCommands:\n";
        }

        if (empty(self::$command) && empty(self::$aliases)) {
            echo "No commands registered yet.\n";
            return 0;
        }

        // Get max width for formatting
        $allCommands = array_keys(self::$command);
        $allAliases = array_keys(self::$aliases);
        $maxLength = max(array_map('strlen', array_merge($allCommands, $allAliases))) + 2;

        $displayed = [];

        // Show all commands (handling multiple per trigger)
        foreach (self::$command as $trigger => $commands) {
            foreach ($commands as $index => $command) {
                $label = $index === 0 ? $trigger : "{$trigger} ({$index})"; // Differentiate multiple commands
                printf("  %-{$maxLength}s - %s\n", $label, $command['help'] ?: 'No description');
            }
            $displayed[$trigger] = true;
        }

        // Show aliases if any
        if (!empty(self::$aliases)) {
            echo "\nAliases:\n";
            foreach (self::$aliases as $alias => $command) {
                $original = null;

                // Search for the original command (accounting for multiple)
                foreach (self::$command as $trigger => $commands) {
                    foreach ($commands as $index => $cmd) {
                        if ($cmd === $command) {
                            $original = $index === 0 ? $trigger : "{$trigger} ({$index})";
                            break 2;
                        }
                    }
                }

                printf("  %-{$maxLength}s → %s\n", $alias, $original ?: 'Unknown');
            }
        }

        return 0;
    }

    /**
     * Prompt
     *
     * @param string $prompt A prompt to display.
     * @param mixed $default The default value to return if the parameter is not provided.
     * @param string|null $type The expected type of the parameter (bool, int, float, string, array, json).
     * @return mixed The value of the CLI parameter, converted to the expected type, or the default value.
     */
    public static function prompt(string $prompt, mixed $default = '', ?string $type = null): mixed
    {
        $value = readline($prompt) ?: $default;

        return self::cast($value, $type, $default);
    }

    public static function cast($value, ?string $type = null, mixed $default = '')
    {
        return match ($type) {
            'bool'  => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool)$default,
            'int'   => filter_var($value, FILTER_VALIDATE_INT) ?? (int)$default,
            'float' => filter_var($value, FILTER_VALIDATE_FLOAT) ?? (float)$default,
            'array' => is_string($value) ? explode(',', $value) : (is_array($value) ? $value : [$value]),
            'json'  => json_decode($value, true) ?? (json_last_error() === JSON_ERROR_NONE ? json_decode($default, true) : $default),
            default => $value,
        };
    }
}

class ParamBag
{
    private array $params = [];
    public function __construct(array $params)
    {
        $this->params = $params;
    }
    public function __get(string $key)
    {
        return $this->params[$key] ?? null;
    }
    public function __set(string $key, $val)
    {
        $this->params[$key] = $val;
    }
}

/**
 * Fluent builder for command configuration.
 */
class CommandBuilder
{
    private string $trigger;
    private int $order;

    public function __construct(string $trigger, int $order)
    {
        $this->trigger = $trigger;
        $this->order   = $order;
    }

    public function alias(string $alias): self
    {
        Command::addAlias($alias, $this->trigger, $this->order);
        return $this;
    }

    public function help(string $text): self
    {
        Command::update($this->trigger, $this->order, ['help' => $text]);
        return $this;
    }

    public function param(array $params): self
    {
        Command::update($this->trigger, $this->order, ['params' => $params]);
        return $this;
    }

    public function dependency(string|callable|array $dep): self
    {
        $cmd = Command::$command[$this->trigger][$this->order] ?? [];
        $deps = $cmd['dependencies'] ?? [];
        $deps = is_array($dep) ? [...$deps, ...$dep] : [...$deps, $dep];
        Command::update($this->trigger, $this->order, ['dependencies' => $deps]);
        return $this;
    }
}

/**
 *  ," is not","yeah","uku"
 * 
 * 
 * 
 * 
 * 
 * 
 */
