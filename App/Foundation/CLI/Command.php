<?php

declare(strict_types=1);

namespace Experimental\App\Foundation\CLI;

use App\Foundation\CLI\Argv;
use App\Foundation\Exceptions\Framework\HighLevelException;
use App\Foundation\Manager\InstanceManager;
use Throwable;
use Closure;

class CommandException extends HighLevelException {}

class Command
{
    public static array $commands = [];
    protected static array $aliases = [];

    /**
     * Register new command
     *
     * @param string $trigger
     * @param array|string|callable|Closure(Argv $argv) $handler
     * @return CommandBuilder
     */
    public static function register(string $trigger, array|string|callable|Closure $handler): CommandBuilder
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
            self::showHelp();

            return 0;
        }

        $command = self::$aliases[$trigger] ?? self::$commands[$trigger][0] ?? null;
        if ($command === null) {
            echo "Unknown command: {$trigger}\n";
            self::showHelp(false);

            return 1;
        }

        // Build Argv according to command’s schema
        try {
            $cli = new Argv(
                array_slice($argv, 2),
                self::mergeOptionAndShort($command['params'], $command['short']),
                self::mergeFlagAndShort($command['flags'], $command['short']),
                $command['strict'] ?? false,
                $command['onunknown'] ?? null
            );

            return self::execute($command, $cli) ?: 0;
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

    private static function execute(array $command, Argv $argv): null|string|int
    {
        // try {
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
                $instance = InstanceManager::getInstance('app')->make($command['command'][0]);
                $command['command'] = [$instance, $command['command'][1]];
            }

            if (is_callable($command['command'])) {
                return ($command['command'])($argv);
            }

            if (is_string($command['command'])) {
                return shell_exec($command['command']);
            }

            return 1;
        // } catch (Throwable $e) {
        //     echo "Error: {$e->getMessage()}\n";
        //     return 1;
        // }
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
