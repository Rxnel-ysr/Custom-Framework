<?php

declare(strict_types=1);

namespace App\Foundation\System;

/**
 * CMD
 *
 * Auto-detects the host environment - OS family, CPU architecture, word size,
 * PHP runtime/SAPI, available process-spawning functions, pcntl/posix support,
 * CPU count, container status, privilege level, and cron / Task Scheduler
 * availability - and exposes a small, unified API for running shell commands,
 * including truly non-blocking (detached, "fire and forget") background
 * commands, across Windows, Linux, macOS and BSD without the caller needing
 * to know which OS it's actually running on.
 */
class CMD
{
    private string $os;
    private string $architecture;
    private int $bits;
    private string $phpBinary;
    private string $sapi;
    private bool $isCli;
    private array $capabilities;
    private ?int $cpuCount = null;
    private ?bool $isContainer = null;

    public function __construct()
    {
        $this->os = self::getOS();
        $this->architecture = self::getArchitecture();
        $this->bits = PHP_INT_SIZE * 8;
        $this->sapi = PHP_SAPI;
        $this->isCli = PHP_SAPI === 'cli';
        $this->capabilities = self::detectCapabilities();
        $this->phpBinary = $this->resolvePhpBinary();
    }

    /* -----------------------------------------------------------------
     * OS / architecture detection
     * --------------------------------------------------------------- */

    public static function getOS(): string
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => 'Windows',
            'Linux'   => 'Linux',
            'Darwin'  => 'macOS',
            'BSD'     => 'BSD',
            'Solaris' => 'Solaris',
            default   => PHP_OS_FAMILY !== '' ? PHP_OS_FAMILY : 'Unknown',
        };
    }

    public static function getArchitecture(): string
    {
        $machine = strtolower(php_uname('m'));

        return match (true) {
            in_array($machine, ['x86_64', 'amd64', 'ia64'], true) => 'x86_64',
            in_array($machine, ['i386', 'i486', 'i586', 'i686', 'x86'], true) => 'x86',
            in_array($machine, ['arm64', 'aarch64'], true) => 'arm64',
            str_starts_with($machine, 'arm') => 'arm',
            str_contains($machine, 'ppc64') => 'ppc64',
            str_contains($machine, 'ppc') => 'ppc',
            str_contains($machine, 's390') => 's390x',
            default => $machine !== '' ? $machine : 'unknown',
        };
    }

    /* -----------------------------------------------------------------
     * Capability detection
     * --------------------------------------------------------------- */

    private static function detectCapabilities(): array
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        $has = static fn(string $fn): bool => function_exists($fn) && !in_array($fn, $disabled, true);

        return [
            'proc_open'  => $has('proc_open'),
            'exec'       => $has('exec'),
            'shell_exec' => $has('shell_exec'),
            'popen'      => $has('popen'),
            'pcntl'      => extension_loaded('pcntl') && $has('pcntl_fork') && $has('pcntl_alarm'),
            'posix'      => extension_loaded('posix'),
            'sockets'    => extension_loaded('sockets'),
        ];
    }

    public function can(string $capability): bool
    {
        return $this->capabilities[$capability] ?? false;
    }

    public function capabilities(): array
    {
        return $this->capabilities;
    }

    public function hasAnyProcessSpawner(): bool
    {
        return $this->can('proc_open') || $this->can('exec') || $this->can('shell_exec') || $this->can('popen');
    }

    /* -----------------------------------------------------------------
     * OS convenience checks
     * --------------------------------------------------------------- */

    public function isWindows(): bool
    {
        return $this->os === 'Windows';
    }
    public function isLinux(): bool
    {
        return $this->os === 'Linux';
    }
    public function isMac(): bool
    {
        return $this->os === 'macOS';
    }
    public function isBSD(): bool
    {
        return $this->os === 'BSD';
    }
    public function isUnixLike(): bool
    {
        return !$this->isWindows();
    }

    /* -----------------------------------------------------------------
     * PHP binary resolution
     * --------------------------------------------------------------- */

    private function resolvePhpBinary(): string
    {
        if (PHP_BINARY !== '' && is_file(PHP_BINARY)) {
            return PHP_BINARY;
        }

        return $this->commandExists('php') ? 'php' : '';
    }

    /* -----------------------------------------------------------------
     * Command discovery
     * --------------------------------------------------------------- */

    public function commandExists(string $binary): bool
    {
        if (!$this->hasAnyProcessSpawner()) {
            return false;
        }

        $probe = $this->isWindows()
            ? "where {$binary} 2>NUL"
            : 'command -v ' . escapeshellarg($binary) . ' 2>/dev/null';

        $output = $this->quietShellExec($probe);

        return $output !== null && trim($output) !== '';
    }

    private function quietShellExec(string $command): ?string
    {
        if ($this->can('shell_exec')) {
            return shell_exec($command);
        }

        if ($this->can('exec')) {
            @exec($command, $lines, $code);
            return $code === 0 ? implode("\n", $lines) : null;
        }

        return null;
    }

    /* -----------------------------------------------------------------
     * CPU / environment extras ("and etc")
     * --------------------------------------------------------------- */

    public function cpuCount(): int
    {
        if ($this->cpuCount !== null) {
            return $this->cpuCount;
        }

        $count = 1;

        try {
            if ($this->isWindows()) {
                $env = getenv('NUMBER_OF_PROCESSORS');
                if ($env !== false && (int) $env > 0) {
                    $count = (int) $env;
                }
            } elseif ($this->isMac() || $this->isBSD()) {
                $out = $this->quietShellExec('sysctl -n hw.ncpu 2>/dev/null');
                if ($out !== null && (int) trim($out) > 0) {
                    $count = (int) trim($out);
                }
            } else { // Linux and friends
                if (is_readable('/proc/cpuinfo')) {
                    $cpuinfo = (string) @file_get_contents('/proc/cpuinfo');
                    $found = substr_count($cpuinfo, "processor\t:");
                    $count = $found > 0 ? $found : $count;
                } else {
                    $out = $this->quietShellExec('nproc 2>/dev/null');
                    if ($out !== null && (int) trim($out) > 0) {
                        $count = (int) trim($out);
                    }
                }
            }
        } catch (\Throwable) {
            $count = 1;
        }

        return $this->cpuCount = max(1, $count);
    }

    public function isContainer(): bool
    {
        if ($this->isContainer !== null) {
            return $this->isContainer;
        }

        if ($this->isWindows()) {
            return $this->isContainer = false;
        }

        return $this->isContainer =
            file_exists('/.dockerenv')
            || (is_readable('/proc/1/cgroup') && str_contains((string) @file_get_contents('/proc/1/cgroup'), 'docker'))
            || getenv('container') !== false;
    }

    public function isPrivileged(): bool
    {
        if ($this->isWindows()) {
            $out = $this->quietShellExec('net session >NUL 2>&1 && echo 1 || echo 0');
            return trim((string) $out) === '1';
        }

        if ($this->can('posix') && function_exists('posix_getuid')) {
            return posix_getuid() === 0;
        }

        return trim((string) $this->quietShellExec('id -u 2>/dev/null')) === '0';
    }

    /* -----------------------------------------------------------------
     * Cron / scheduler availability
     * --------------------------------------------------------------- */

    public function hasCron(): bool
    {
        return $this->isUnixLike() && $this->commandExists('crontab');
    }

    public function hasTaskScheduler(): bool
    {
        return $this->isWindows() && $this->commandExists('schtasks');
    }

    public function hasScheduler(): bool
    {
        return $this->hasCron() || $this->hasTaskScheduler();
    }

    /* -----------------------------------------------------------------
     * Process execution
     * --------------------------------------------------------------- */

    /**
     * Run a command and block until it finishes.
     *
     * @param string $command fully-built shell command; caller is responsible for escaping arguments
     * @return array{code:int,stdout:string,stderr:string}
     */
    public function run(string $command, ?string $cwd = null): array
    {
        if ($this->can('proc_open')) {
            $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $process = proc_open($command, $descriptors, $pipes, $cwd ?? getcwd() ?: null);

            if (is_resource($process)) {
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $code = proc_close($process);

                return ['code' => $code, 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
            }
        }

        if ($this->can('exec')) {
            @exec($command . ' 2>&1', $out, $code);
            return ['code' => $code, 'stdout' => implode("\n", $out), 'stderr' => ''];
        }

        return ['code' => 1, 'stdout' => '', 'stderr' => 'No process-spawning function available (proc_open/exec disabled).'];
    }

    /**
     * Launch a command in the background - detached and non-blocking - and return
     * immediately without waiting for it to finish. Returns a best-effort PID
     * (0 when the OS/strategy used doesn't expose one, e.g. Windows `start`).
     */
    public function runInBackground(string $command, ?string $logFile = null): int
    {
        $logFile ??= $this->isWindows() ? 'NUL' : '/dev/null';

        if ($this->isWindows()) {
            $wrapped = sprintf('start "" /B %s', $command);

            if ($this->can('proc_open')) {
                $descriptors = [0 => ['pipe', 'r'], 1 => ['file', $logFile, 'a'], 2 => ['file', $logFile, 'a']];
                $process = proc_open($wrapped, $descriptors, $pipes);
                if (is_resource($process)) {
                    fclose($pipes[0]);
                    // "start" itself returns as soon as the child is launched,
                    // so closing here does not block on the detached process.
                    proc_close($process);
                }
                return 0;
            }

            if ($this->can('exec')) {
                @exec($wrapped);
            } elseif ($this->can('shell_exec')) {
                shell_exec($wrapped);
            }

            return 0;
        }

        // Unix-like: setsid detaches from the controlling terminal/session so the
        // child survives the parent process exiting (important when the caller is
        // a short-lived PHP-FPM / CLI request); nohup additionally ignores SIGHUP.
        $prefix = $this->commandExists('setsid') ? 'setsid ' : '';
        $wrapped = sprintf('%snohup %s > %s 2>&1 & echo $!', $prefix, $command, $logFile);

        $pid = $this->quietShellExec($wrapped);

        return $pid !== null && trim($pid) !== '' ? (int) trim($pid) : 0;
    }

    /* -----------------------------------------------------------------
     * Full environment report
     * --------------------------------------------------------------- */

    public function detect(): array
    {
        return [
            'os'                 => $this->os,
            'os_version'         => php_uname('r'),
            'architecture'       => $this->architecture,
            'bits'               => $this->bits,
            'hostname'           => php_uname('n'),
            'uname'              => php_uname(),
            'php_version'        => PHP_VERSION,
            'php_binary'         => $this->phpBinary,
            'sapi'               => $this->sapi,
            'is_cli'             => $this->isCli,
            'cpu_count'          => $this->cpuCount(),
            'is_container'       => $this->isContainer(),
            'is_privileged'      => $this->isPrivileged(),
            'capabilities'       => $this->capabilities,
            'has_cron'           => $this->hasCron(),
            'has_task_scheduler' => $this->hasTaskScheduler(),
            'temp_dir'           => sys_get_temp_dir(),
        ];
    }

    /* -----------------------------------------------------------------
     * Getters
     * --------------------------------------------------------------- */

    public function getOsName(): string
    {
        return $this->os;
    }
    public function getArch(): string
    {
        return $this->architecture;
    }
    public function getBits(): int
    {
        return $this->bits;
    }
    public function getPhpBinaryPath(): string
    {
        return $this->phpBinary;
    }
    public function getSapi(): string
    {
        return $this->sapi;
    }
}
