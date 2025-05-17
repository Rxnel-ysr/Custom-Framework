<?php

declare(strict_types=1);

namespace App\Foundation\CLI;

use App\EXPE\Foundation\Manager\ClassManager;

// require_once './App/Core/definitions.php';
// require_once FOUNDATION . 'Manager/ClassManager_EXPE.php';
// require_once FOUNDATION . 'Helpers/Helpers.php';
// require_once FOUNDATION . 'Helpers/Utility.php';
// require_once FOUNDATION . 'Debug/Debug.php';

class Command
{
    private static array $command = [];
    private static array $aliases = [];
    // private static array $last_error = [];

    /**
     * Register a new command.
     *
     * @param string $trigger The command trigger (alias).
     * @param string|callable $command The command to execute, either as a string (code) or callable.
     * @param array $params Parameters for the command.
     * @param array $dependencies Dependencies for the command.
     * @param string $help_message Help message for the command.
     * @param array $export_var Variables to export for the command.
     * @return void
     */
    public static function register(string $trigger, string|callable $command, string $alias = '', string $help_message = '', array $params = [], array $dependencies = [])
    {
        self::$command[$trigger][] = [
            'dependencies' => $dependencies,
            'command' => $command,
            'params' => $params,
            'help' => $help_message,
        ];

        if (!empty($alias)) {
            $alias = trim($alias);
            $order = count(self::$command[$trigger]) - 1;
            $prefix = strlen($alias) > 1 ? '--' : '-';
            self::$aliases[$prefix . $alias] = self::$command[$trigger][$order];
        }
    }

    /**
     * Execute a registered command.
     *
     * @param string $trigger The command trigger to execute.
     * @return mixed The result of the command execution.
     */
    public static function execute(string $trigger)
    {
        $command = self::$aliases[$trigger] ?? self::$command[$trigger][0] ?? self::$aliases['-h'];
        // var_dump($command);
        // die;

        foreach ($command['dependencies'] as $alias => $dependency) {
            if (is_string($dependency) && strpos($dependency, '.php') === false) {
                require_once ClassManager::getClassFile($dependency);

                if (!is_numeric($alias)) {
                    class_alias($dependency, $alias);
                }
            } elseif (is_callable($dependency)) {
                call_user_func($dependency);
            } else {
                require_once $dependency;
            }
        }

        if (is_callable($command['command'])) {
            // var_dump($command);
            return callFuncWithParams($command['command'], false, true, ...$command['params']);
        }

        if (is_string($command['command'])) {
            return eval($command['command']);
        }
    }

    /**
     * Standby and execute a default or help command.
     *
     * @return void
     */
    public static function standBy()
    {
        self::execute(self::parameter(1, '', 'help'));
    }

    /**
     * Display help information for all registered commands.
     *
     * @return void
     */
    public static function showHelp()
    {
        echo "Built-in command handler for this custom framework\n\nCommands:\n";

        if (empty(self::$command) && empty(self::$aliases)) {
            echo "No commands registered yet.\n";
            exit;
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

        exit;
    }

    /**
     * Return PHP CLI parameter at a given index with optional type filtering.
     *
     * @param int $n The parameter index.
     * @param string $prompt A prompt to display if the parameter is not provided.
     * @param mixed $default The default value to return if the parameter is not provided.
     * @param string|null $type The expected type of the parameter (bool, int, float, string, array, json).
     * @return mixed The value of the CLI parameter, converted to the expected type, or the default value.
     */
    public static function parameter(int $n, string $prompt, mixed $default = '', ?string $type = null): mixed
    {
        global $argv;

        $value = $argv[$n] ?? (isset($default) && !empty($default) ? $default : trim(readline($prompt)));

        // Type filtering
        return match ($type) {
            'bool'   => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool)$default,
            'int'    => filter_var($value, FILTER_VALIDATE_INT) ?? (int)$default,
            'float'  => filter_var($value, FILTER_VALIDATE_FLOAT) ?? (float)$default,
            'array'  => is_string($value) ? explode(',', $value) : (is_array($value) ? $value : [$value]),
            'json' => json_decode($value, true) ?? (json_last_error() === JSON_ERROR_NONE ? json_decode($default, true) : $default),
            default  => $value, // Default as string
        };
    }

    // public static function setLastError(string $command_name, array $error)
    // {
    //     self::$last_error = [
    //         'command_name' => $command_name,
    //         'details' => $error
    //     ];
    // }

    // public static function getLastError()
    // {
    //     return self::$last_error;
    // }
}
