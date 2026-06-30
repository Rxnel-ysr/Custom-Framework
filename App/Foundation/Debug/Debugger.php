<?php

declare(strict_types=1);

namespace App\Debug;

use App\Support\Facades\DI;
use ErrorException;
use Throwable;

class Debugger
{
    private static bool $is_initialized = false;
    private static null|string $log_file;
    private static string $error_page;
    // private static string $log_file_json = STORAGE_PATH . '/logs/error.json';
    private static bool $web = false;
    private const ERROR_MAP = [
        E_ERROR             => 'E_ERROR',
        E_WARNING           => 'E_WARNING',
        E_PARSE             => 'E_PARSE',
        E_NOTICE            => 'E_NOTICE',
        E_CORE_ERROR        => 'E_CORE_ERROR',
        E_CORE_WARNING      => 'E_CORE_WARNING',
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_USER_NOTICE       => 'E_USER_NOTICE',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED        => 'E_DEPRECATED',
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
    ];

    public static function init(bool $isWeb, int $errorLevel, string  $error_page, bool $store_at_log = false, ?string $log_file = null)
    {
        self::$web = $isWeb;
        self::$error_page = $error_page;
        self::$log_file = $log_file;
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        // ini_set('max_execution_time',5);
        // set_time_limit(10); // Set execution time to 30 seconds
        error_reporting($errorLevel);
        ini_set('log_errors', 1);
        $store_at_log && ini_set('error_log', self::$log_file);
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
        self::$is_initialized = true;
    }

    public static function getState()
    {
        return self::$is_initialized;
    }

    public static function handleError(int $errno, string $errstr, string $errfile, int $errline)
    {
        $errorData = new ErrorException($errstr, 0, $errno, $errfile, $errline);
        self::dumpErr($errorData);
    }

    public static function handleException(Throwable $exception)
    {
        self::dumpErr($exception);
    }

    public static function handleShutdown()
    {
        $error = error_get_last();
        if ($error !== null) {
            $errorData = new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
            self::dumpErr($errorData);
        }
    }

    public static function dumpErr(Throwable $e, bool $ignore = false, bool $dump_at_terminal = false, bool $use_other_backtrace = false, ?array $backtrace = null, ?string &$message = null)
    {
        // ob_clean();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        
        $message = "\n[ERROR] " . get_class($e) . ' | Code: ' . $e->getCode()
        . "\nMessage: " . $e->getMessage()
        . "\nFile: " . $e->getFile() . '(' . $e->getLine() . ')'
            . "\nTrace:\n" . $e->getTraceAsString() . "\n";

        if ($e->getPrevious() !== null) {
            $prev = $e->getPrevious();
            $message .= "\n\nPrevious Trigger:\n" . get_class($prev) . ' | Code: ' . $prev->getCode()
            . "\nMessage: " . $prev->getMessage()
            . "\nFile: " . $prev->getFile() . ' (Line: ' . $prev->getLine() . ')'
            . "\nTrace:\n" . $prev->getTraceAsString();
        }

        $dump_at_terminal ? print_r($message) && print("\n") : error_log($message);

        if (!$ignore) {
            if (self::$web) {
                $trace = '';
                !$use_other_backtrace && $trace = $e->getTraceAsString();
                $use_other_backtrace && array_walk($backtrace, function ($arr_trace, $index) use (&$trace) {
                    if (count($arr_trace) < 2) {
                        $trace .= '#' . $index . ' [internal function]: ' . $arr_trace['function'] . '()' . PHP_EOL;
                    } else {
                        $trace .= '#' . $index . ' ' . (isset($arr_trace['file']) ? $arr_trace['file'] : 'No file in provided trace') . '(' . (isset($arr_trace['line']) ? $arr_trace['line'] : 'No line in provided trace') . '): ' . (isset($arr_trace['class']) ? $arr_trace['class'] . '::' : '') . $arr_trace['function'] . '()' . PHP_EOL;
                    }
                });
                self::showErrorPage(
                    500,
                    get_class($e) . ': ' . $e->getMessage(),
                    '<a style="color: white;" href="vscode://file/' . $e->getFile() . ':' . $e->getLine() . '" >File: ' . $e->getFile() . '(' . $e->getLine() . ')</a>',
                    '',
                    $trace
                    // getBoolEnv('AUTO_LOAD_USER_PATH_DEFINED_CLASS')
                );
            }
            exit(1);
        }
    }


    public static function showErrorPage(
        int $errorCode,
        string $customMessage = '',
        string $customSubMessage = '',
        string $customTitleName = '',
        string $trace = ''
    ): void {
        if (filter_var(http_response_code(), FILTER_VALIDATE_INT) != $errorCode && !headers_sent()) {
            http_response_code($errorCode);
        }

        $error_messages = [
            404 => 'Page Not Found',
            403 => 'Forbidden Access',
            500 => 'Internal Server Error',
        ];

        $error_sub_messages = [
            404 => 'We are not able to find what you are looking for',
            403 => 'Mind if you going back? You are not allowed to be here',
            500 => 'Sorry, looks like the server went on vacation',
        ];

        $title_name = [
            404 => 'Not found ',
            403 => 'Prohibited action',
            500 => 'Server Error',
        ];

        $error_message =
            $customMessage ?: $error_messages[$errorCode] ?? 'An error occurred';
        $error_sub_message =
            $customSubMessage ?: $error_sub_messages[$errorCode] ?? 'An error occurred';
        $title_name =
            $customTitleName ?: $title_name[$errorCode] ?? 'An error occured';
        $trace;
        $_nonce = DI::get('nonce');

        // if (ob_get_length()) {
        //     ob_end_clean();  // Use ob_end_clean() instead of ob_clean() to discard and close the buffer
        // }
        require_once self::$error_page;
        // if (ob_get_length()) {
        //     ob_end_flush();  // Flush output safely
        // }
        exit(1);
    }
}
