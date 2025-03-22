<?php
namespace App\CLI;

use App\Debug\Debugger;
use App\Utils\Manager\ClassManager;

require_once './App/Core/definitions.php';
require_once UTILS_PATH . 'ClassManager.php';
require_once UTILS_PATH . 'Helpers.php';
require_once UTILS_PATH . 'Utility.php';
require_once UTILS_PATH . 'Debug.php';

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
Debugger::init(false, 0);

class Command
{
    private static array $command = [];

    public static function register(string $triggers, string|callable $command, array $params = [], array $dependencies = [], string $help_message = '', array $export_var = [])
    {
        self::$command[$triggers] = [
            'dependencies' => $dependencies,
            'command' => $command,
            'params' => $params,
            'help' => $help_message,
            'vars' => compacts(...$export_var)
        ];
    }

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

    public static function standBy()
    {
        global $argv;
        self::execute($argv[1] ?? '');
    }

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
     * Return php CLI parameter on given index
     */
    public static function parameter(int $n, string $prompt, mixed $default = '')
    {
        global $argv;
        return $argv[$n] ?? (empty($default) ? trim(readline($prompt)) : $default);
    }
}
