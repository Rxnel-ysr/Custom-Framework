<?php
namespace App\CLI;

use App\Debug\Debugger;
use App\Utils\Manager\ClassManager;

require_once './App/Core/definitions.php';
require_once UTILS_PATH . 'ClassManager.php';
require_once UTILS_PATH . 'Helpers.php';
require_once UTILS_PATH . 'Utility.php';
require_once UTILS_PATH . 'Debug.php';

Debugger::init(false, 0);

class Command
{
    private static array $command = [];

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
    public static function register(string $trigger, string|callable $command, array $params = [], array $dependencies = [], string $help_message = '', array $export_var = [])
    {
        self::$command[$trigger] = [
            'dependencies' => $dependencies,
            'command' => $command,
            'params' => $params,
            'help' => $help_message,
            'vars' => compacts(...$export_var)
        ];
    }

    /**
     * Execute a registered command.
     *
     * @param string $trigger The command trigger to execute.
     * @return mixed The result of the command execution.
     */
    public static function execute(string $trigger)
    {
        try {
            if (!isset(self::$command[$trigger])) {
                self::showHelp();
            }

            foreach (self::$command[$trigger]['dependencies'] as $alias => $dependency) {
                if (strpos($dependency, '.php') === false) {
                    require_once ClassManager::getClassFile($dependency);

                    if (!is_numeric($alias)) {
                        class_alias($dependency, $alias);
                    }
                } else {
                    require_once $dependency;
                }
            }

            extract(self::$command[$trigger]['vars']);

            $command = self::$command[$trigger]['command'];

            if (is_callable($command)) {
                return callFuncWithParams($command, self::$command[$trigger]['params']);
            }

            if (is_string($command)) {
                return eval($command);
            }
        } catch (\Throwable $e) {
            Debugger::dumpErr($e);
        }
    }

    /**
     * Standby and execute a default or help command.
     *
     * @return void
     */
    public static function standBy()
    {
        self::execute(self::parameter(1,'','help'));
    }

    /**
     * Show the help information for all registered commands.
     *
     * @return void
     */
    public static function showHelp()
    {
        echo "Built-in command handler for this custom framework\nCommands:\n";
        if (empty(self::$command)) {
            echo 'No commands yet' . PHP_EOL;
        } else {
            foreach (self::$command as $trigger => $command) {
                echo $trigger . ' - ' . $command['help'] . PHP_EOL;
            }
        }
        exit;
    }

    /**
     * Return PHP CLI parameter at a given index.
     *
     * @param int $n The parameter index.
     * @param string $prompt A prompt to display if the parameter is not provided.
     * @param mixed $default The default value to return if the parameter is not provided.
     * @return mixed The value of the CLI parameter or the default value.
     */
    public static function parameter(int $n, string $prompt, mixed $default = '')
    {
        global $argv;
        return $argv[$n] ?? (empty($default) ? trim(readline($prompt)) : $default);
    }
}
