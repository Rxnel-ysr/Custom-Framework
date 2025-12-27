<?php

declare(strict_types=1);

namespace Experimental\App\Foundation\CLI;

use App\Foundation\CLI\Argv;
use App\Foundation\Manager\InstanceManager;
use Throwable;
use Closure;
use Exception;

// /**
//  * Experimental command
//  * 
//  * @depends App\Foundation\Traits\Macroable
//  */
// class Command
// {

//     protected static Argv $argv;
//     private static array $command = [];
//     private static array $aliases = [];

//     /**
//      * Register new command
//      *
//      * @param string $trigger
//      * @param string|callable(Argv $args) $command
//      * @return CommandBuilder
//      */
//     public static function register(string $trigger, string|callable|array $command): CommandBuilder
//     {
//         $entry = [
//             'dependencies' => [],
//             'command'      => $command,
//             'params'       => [],
//             'help'         => '',
//         ];

//         self::$command[$trigger][] = $entry;
//         $order = count(self::$command[$trigger]) - 1;

//         return new CommandBuilder($trigger, $order);
//     }

//     public static function invoke(string $trigger, array $params)
//     {
//         $command = self::$aliases[$trigger] ?? self::$command[$trigger][0] ?? null;
//         if (is_null($command)) {
//             echo "Command not found: {$trigger}\n\nAvailable commands:\n";
//             return self::showHelp(false);
//         }
//         /** @var array{dependencies:array<string,array|Closure|object>, command:string|Closure} $command */

//         foreach ($command['dependencies'] as $alias => $dependency) {
//             if (is_string($dependency) && strpos($dependency, '.php') === false) {
//                 if (!is_numeric($alias)) {
//                     class_alias($dependency, $alias);
//                 }
//             } elseif (is_array($dependency)) {
//                 $instance = is_object($dependency[0]) ? $dependency[0] : new $dependency[0];
//                 $instance->$dependency[1];
//             } else if (is_callable($dependency)) {
//                 call_user_func($dependency);
//             } else {
//                 require_once $dependency;
//             }
//         }

//         foreach ($command['params'] as $param) {
//             if ($isTypeDefined = strpos($param, ':') !== false) {
//                 [$param, $type] = explode(':', $param, 2);
//             }

//             if (isset($params[$param])) {
//                 $params[$param] = $isTypeDefined ? self::cast($params[$param], $type, null) : $params[$param];
//             }
//         }

//         if (is_array($command['command']) && count($command['command']) === 2) {
//             $instance = new $command['command'][0];
//             $command['command'] = [$instance, $command['command'][1]];
//         }

//         return callFuncWithParams($command['command'], $params, true, true);
//     }

//     public static function update(string $trigger, int $order, array $updates): void
//     {
//         self::$command[$trigger][$order] = array_merge(
//             self::$command[$trigger][$order],
//             $updates
//         );
//     }

//     public static function addAlias(string $alias, string $trigger, int $order): void
//     {
//         self::$aliases[$alias] = &self::$command[$trigger][$order];
//     }


//     /**
//      * Execute a registered command.
//      *
//      * @param string $trigger The command trigger to execute.
//      * @return mixed The result of the command execution.
//      */
//     public static function execute(?string $trigger)
//     {
//         try {
//             if (is_null($trigger)) {
//                 return self::showHelp();
//             }
//             $command = self::$aliases[$trigger] ?? self::$command[$trigger][0] ?? null;
//             if (is_null($command)) {
//                 echo "Command not found: {$trigger}\n\nAvailable commands:\n";
//                 return self::showHelp(false);
//             }
//             /** @var array{dependencies:array<string,array|Closure|object>, command:string|Closure} $command */

//             foreach ($command['dependencies'] as $alias => $dependency) {
//                 if (is_string($dependency) && strpos($dependency, '.php') === false) {
//                     if (!is_numeric($alias)) {
//                         class_alias($dependency, $alias);
//                     }
//                 } elseif (is_array($dependency)) {
//                     $instance = is_object($dependency[0]) ? $dependency[0] : new $dependency[0];
//                     $instance->$dependency[1];
//                 } else if (is_callable($dependency)) {
//                     call_user_func($dependency);
//                 } else {
//                     require_once $dependency;
//                 }
//             }

//             // if (is_callable($command['command'])) {
//             //     $params = [];
//             //     foreach ($command['params'] as $no => $param) {
//             //         $params[$param] = self::parameter($no + 2);
//             //     }

//             //     $bag = new ParamBag($params);
//             //     $callable = $command['command']->bindTo($bag, ParamBag::class);

//             //     $callable();

//             // }

//             $params = [];
//             $unusedPositionals = [];

//             foreach ($command['params'] as $param) {
//                 if ($isTypeDefined = strpos($param, ':') !== false) {
//                     [$param, $type] = explode(':', $param, 2);
//                 }

//                 $value = self::$argv->option($param, null);
//                 if ($value !== null) {
//                     $params[$param] = $isTypeDefined ? self::cast($value, $type, null) : $value;
//                 } else {
//                     $unusedPositionals[] = $param;
//                 }
//             }

//             // fill unused params with remaining positionals
//             foreach ($unusedPositionals as $param) {
//                 $pos = self::$argv->getNextPositional();
//                 if ($pos !== null) {
//                     $params[$param] = $pos;
//                 }
//             }

//             if (is_array($command['command']) && count($command['command']) === 2) {
//                 $instance = new $command['command'][0];
//                 $command['command'] = [$instance, $command['command'][1]];
//             }

//             if (is_callable($command['command'])) {
//                 return callFuncWithParams($command['command'], $params, true, true);
//             } elseif (is_string($command['command'])) {
//                 return shell_exec($command['command']);
//             }
//         } catch (Throwable $e) {
//             echo "Error running command: {$e->getMessage()}";
//             return 1;
//         }
//     }


//     /**
//      * Standby and execute a default or help command.
//      *
//      * @return mixed
//      */
//     public static function standBy(Argv $argv)
//     {
//         self::$argv = $argv;
//         InstanceManager::setInstance(Argv::class, $argv);
//         return self::execute($argv->shiftPositionals());
//     }

//     /**
//      * Display help information for all registered commands.
//      *
//      * @return void
//      */
//     public static function showHelp(bool $withIntro = true)
//     {
//         if ($withIntro) {
//             echo "Built-in command handler for this custom framework\n\nCommands:\n";
//         }

//         if (empty(self::$command) && empty(self::$aliases)) {
//             echo "No commands registered yet.\n";
//             return 0;
//         }

//         // Get max width for formatting
//         $allCommands = array_keys(self::$command);
//         $allAliases = array_keys(self::$aliases);
//         $maxLength = max(array_map('strlen', array_merge($allCommands, $allAliases))) + 2;

//         $displayed = [];

//         // Show all commands (handling multiple per trigger)
//         foreach (self::$command as $trigger => $commands) {
//             foreach ($commands as $index => $command) {
//                 $label = $index === 0 ? $trigger : "{$trigger} ({$index})"; // Differentiate multiple commands
//                 printf("  %-{$maxLength}s - %s\n", $label, $command['help'] ?: 'No description');
//             }
//             $displayed[$trigger] = true;
//         }

//         // Show aliases if any
//         if (!empty(self::$aliases)) {
//             echo "\nAliases:\n";
//             foreach (self::$aliases as $alias => $command) {
//                 $original = null;

//                 // Search for the original command (accounting for multiple)
//                 foreach (self::$command as $trigger => $commands) {
//                     foreach ($commands as $index => $cmd) {
//                         if ($cmd === $command) {
//                             $original = $index === 0 ? $trigger : "{$trigger} ({$index})";
//                             break 2;
//                         }
//                     }
//                 }

//                 printf("  %-{$maxLength}s → %s\n", $alias, $original ?: 'Unknown');
//             }
//         }

//         return 0;
//     }

//     /**
//      * Prompt
//      *
//      * @param string $prompt A prompt to display.
//      * @param mixed $default The default value to return if the parameter is not provided.
//      * @param string|null $type The expected type of the parameter (bool, int, float, string, array, json).
//      * @return mixed The value of the CLI parameter, converted to the expected type, or the default value.
//      */
//     public static function prompt(string $prompt, mixed $default = '', ?string $type = null): mixed
//     {
//         $value = readline($prompt) ?: $default;

//         return self::cast($value, $type, $default);
//     }

//     public static function cast($value, ?string $type = null, mixed $default = '')
//     {
//         return match ($type) {
//             'bool'  => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool)$default,
//             'int'   => filter_var($value, FILTER_VALIDATE_INT) ?? (int)$default,
//             'float' => filter_var($value, FILTER_VALIDATE_FLOAT) ?? (float)$default,
//             'array' => is_string($value) ? explode(',', $value) : (is_array($value) ? $value : [$value]),
//             'json'  => json_decode($value, true) ?? (json_last_error() === JSON_ERROR_NONE ? json_decode($default, true) : $default),
//             default => $value,
//         };
//     }
// }

// class ParamBag
// {
//     private array $params = [];
//     public function __construct(array $params)
//     {
//         $this->params = $params;
//     }
//     public function __get(string $key)
//     {
//         return $this->params[$key] ?? null;
//     }
//     public function __set(string $key, $val)
//     {
//         $this->params[$key] = $val;
//     }
// }

// /**
//  * Fluent builder for command configuration.
//  */
// class CommandBuilder
// {
//     private string $trigger;
//     private int $order;

//     public function __construct(string $trigger, int $order)
//     {
//         $this->trigger = $trigger;
//         $this->order   = $order;
//     }

//     public function alias(string $alias): self
//     {
//         Command::addAlias($alias, $this->trigger, $this->order);
//         return $this;
//     }

//     public function help(string $text): self
//     {
//         Command::update($this->trigger, $this->order, ['help' => $text]);
//         return $this;
//     }

//     public function param(array $params): self
//     {
//         Command::update($this->trigger, $this->order, ['params' => $params]);
//         return $this;
//     }

//     public function dependency(string|callable|array $dep): self
//     {
//         $cmd = Command::$command[$this->trigger][$this->order] ?? [];
//         $deps = $cmd['dependencies'] ?? [];
//         $deps = is_array($dep) ? [...$deps, ...$dep] : [...$deps, $dep];
//         Command::update($this->trigger, $this->order, ['dependencies' => $deps]);
//         return $this;
//     }
// }

/**
 *  ," is not","yeah","uku"
 * 
 * 
 * 
 * 
 * 
 * 
 */

// use App\Foundation\Traits\Macroable;
// use Throwable;

class CommandException extends Exception {}

class Command
{
    public static array $commands = [];
    protected static array $aliases = [];

    /**
     * Register new command
     */
    public static function register(string $trigger, array|string|callable $handler): CommandBuilder
    {
        self::$commands[$trigger][] = [
            'dependencies' => [],
            'command'      => $handler,
            'params'       => [],
            'flags'        => [],
            'short'        => [],
            'help'         => '',
        ];

        $order = count(self::$commands[$trigger]) - 1;
        return new CommandBuilder($trigger, $order);
    }

    public static function update(string $trigger, int $order, array $updates): void
    {
        self::$commands[$trigger][$order] = array_merge(
            self::$commands[$trigger][$order],
            $updates
        );
    }

    public static function addAlias(string $alias, string $trigger, int $order): void
    {
        self::$aliases[$alias] = &self::$commands[$trigger][$order];
    }

    /**
     * StandBy - entry point
     */
    public static function standBy(array $argv): mixed
    {
        $trigger = $argv[1] ?? null;
        if ($trigger === null) {
            return self::showHelp();
        }

        $command = self::$aliases[$trigger] ?? self::$commands[$trigger][0] ?? null;
        if ($command === null) {
            echo "Unknown command: {$trigger}\n";
            return self::showHelp(false);
        }

        // var_dump($command['strict']);
        // exit;
        // Build Argv according to command’s schema
        try {
            $cli = new Argv(
                array_slice($argv, 2),
                self::mergeOptionAndShort($command['params'], $command['short']),
                self::mergeFlagAndShort($command['flags'], $command['short']),
                $command['strict'] ?? false,
                $command['onunknown'] ?? null
            );

            return self::execute($command, $cli);
        } catch (Throwable $e) {
            throw new CommandException("Error running command: " . $e->getMessage(), 0, $e);
        }
    }

    private static function mergeOptionAndShort(array $options, array $shorts): array|null
    {
        $mapped = [];
        foreach ($options as $opt) {
            $mapped[] = isset($shorts[$opt]) ? [$opt, $shorts[$opt]] : $opt;
        }
        return empty($mapped) ? null : $mapped;
    }

    private static function mergeFlagAndShort(array $flags, array $shorts): array|null
    {
        $mapped = [];
        foreach ($flags as $f) {
            $mapped[] = isset($shorts[$f]) ? [$f, $shorts[$f]] : $f;
        }
        return empty($mapped) ? null : $mapped;
    }

    private static function execute(array $command, Argv $argv): mixed
    {
        try {
            foreach ($command['dependencies'] as $dep) {
                if (is_callable($dep)) {
                    $dep();
                } elseif (is_string($dep) && str_ends_with($dep, '.php')) {
                    require_once $dep;
                } elseif (is_string($dep)) {
                    class_exists($dep) ?: class_alias($dep, basename(str_replace('\\', '/', $dep)));
                }
            }

            if (is_array($command['command']) && count($command['command']) === 2) {
                $instance = new $command['command'][0];
                $command['command'] = [$instance, $command['command'][1]];
            }

            if (is_callable($command['command'])) {
                return ($command['command'])($argv);
            }

            if (is_string($command['command'])) {
                return shell_exec($command['command']);
            }

            return null;
        } catch (Throwable $e) {
            echo "Error: {$e->getMessage()}\n";
            return 1;
        }
    }

    public static function showHelp(bool $withIntro = true): void
    {
        if ($withIntro) echo "CLI Command Framework\n\n";

        foreach (self::$commands as $trigger => $entries) {
            foreach ($entries as $cmd) {
                printf("  %-15s %s\n", $trigger, $cmd['help'] ?: 'No description');
            }
        }

        if (!empty(self::$aliases)) {
            echo "\nAliases:\n";
            foreach (self::$aliases as $alias => $ref) {
                echo "  {$alias} → command\n";
            }
        }
    }
}

/**
 * Builder for command metadata
 */
class CommandBuilder
{
    public function __construct(private string $trigger, private int $order) {}

    public function help(string $text): self
    {
        Command::update($this->trigger, $this->order, ['help' => $text]);
        return $this;
    }

    public function params(array $options): self
    {
        Command::update($this->trigger, $this->order, ['params' => $options]);
        return $this;
    }

    public function strict(): self
    {
        Command::update($this->trigger, $this->order, ['strict' => true]);
        return $this;
    }

    public function flags(array $flags): self
    {
        Command::update($this->trigger, $this->order, ['flags' => $flags]);
        return $this;
    }

    public function short(array $shortMap): self
    {
        Command::update($this->trigger, $this->order, ['short' => $shortMap]);
        return $this;
    }

    /**
     * Undocumented function
     *
     * @param callable(param, type) $onunknown
     * @return self
     */
    public function onUnknown(callable $onunknown): self
    {
        Command::update($this->trigger, $this->order, ['onunknown' => $onunknown]);
        return $this;
    }

    public function alias(string $alias): self
    {
        Command::addAlias($alias, $this->trigger, $this->order);
        return $this;
    }

    public function dependency(string|array|callable $dep): self
    {
        $cmd = Command::$commands[$this->trigger][$this->order];
        $deps = $cmd['dependencies'] ?? [];
        $deps = array_merge($deps, is_array($dep) ? $dep : [$dep]);
        Command::update($this->trigger, $this->order, ['dependencies' => $deps]);
        return $this;
    }
}
