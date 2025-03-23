<?php

namespace App\Debug;

use Throwable;

class Debugger
{
    private static $logFile = __DIR__ . '/storage/logs/debug.log';
    private static bool $web = false;

    public static function init(bool $isWeb, int $errorLevel)
    {
        self::$web = $isWeb;
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        error_reporting($errorLevel);
        // error_reporting(E_ALL);
        // ini_set('display_errors', 1);
        ini_set('log_errors', 1);
        ini_set('error_log', self::$logFile);

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError($errno, $errstr, $errfile, $errline)
    {
        $trimmedFile = self::trimPath($errfile);  // Trim long paths
        $message = "File: $trimmedFile\n";
        $message .= "Line: $errline\n";

        self::$web ? showErrorPage(500, 'Error: ' . $errstr, $message) : '';
    }

    /**
     * Trim file path for better readability
     */
    public static function trimPath($path)
    {
        $basePath = $_SERVER['DOCUMENT_ROOT'];
        if (strpos($path, $basePath) === 0) {
            return '...' . substr($path, strlen($basePath));
        }
        return $path;
    }

    public static function handleException($exception)
    {
        $errstr = $exception->getMessage();
        $errfile = $exception->getFile();
        $errline = $exception->getLine();

        $trimmedFile = self::trimPath($errfile);  // Trim long paths
        $message = "File: $trimmedFile\n";
        $message .= "Line: $errline\n";

        self::$web ? showErrorPage(500, 'Exception: ' . $errstr, $message) : '';
    }

    public static function handleShutdown()
    {
        $error = error_get_last();
        if ($error !== null) {
            if (!headers_sent()) {
                http_response_code(500);
            }
            $errType = $error['type'];
            $errstr = $error['message'];
            $errfile = $error['file'];
            $errline = $error['line'];

            $trimmedFile = self::trimPath($errfile);
            $message = "[$errType] File: $trimmedFile\n";
            $message .= "Line: $errline\n";
            $message .= "Message: $errstr\n";

            error_log('[SHUTDOWN] ' . $message);  // Log it
            self::$web ? showErrorPage(500, 'Fatal Error', $message) : '';

            exit(1);
        }
    }

    // private static function log($message)
    // {
    //     file_put_contents(self::$logFile, date("[Y-m-d H:i:s] ") . $message . PHP_EOL, FILE_APPEND);
    // }

    public static function dumpTrace($trace)
    {
        error_log('[BACKTRACE] ' . print_r($trace, true));
    }

    public static function dumpErr(Throwable $e, bool $ignore = false)
    {
        if ($e->getPrevious() === null) {
            error_log("\n[ERROR] " . get_class($e) . ' | Code: ' . $e->getCode()
                . "\nMessage: " . $e->getMessage()
                . "\nFile: " . $e->getFile() . ' (Line: ' . $e->getLine() . ')'
                . "\nTrace:\n" . $e->getTraceAsString() . "\n");
        } else {
            error_log("\n[ERROR] " . get_class($e) . ' | Code: ' . $e->getCode()
                . "\nMessage: " . $e->getMessage()
                . "\nFile: " . $e->getFile() . ' (Line: ' . $e->getLine() . ')'
                . "\nTrace:\n" . $e->getTraceAsString()
                . "\n\nPrevious Trigger:\n" . 'Code: ' . $e->getPrevious()->getCode()
                . "\nMessage: " . $e->getPrevious()->getMessage()
                . "\nFile: " . $e->getPrevious()->getFile() . ' (Line: ' . $e->getPrevious()->getLine() . ')'
                . "\nTrace:\n" . $e->getPrevious()->getTraceAsString());
        }

        if (!$ignore) {
            $errclass = get_class($e);
            $errstr = $e->getMessage();
            $errfile = $e->getFile();
            $errline = $e->getLine();

            $trimmedFile = self::trimPath($errfile);  // Trim long paths
            $message = "File: $trimmedFile\n";
            $message .= "Line: $errline\n";

            // showErrorPage(200);
            if (self::$web === true) {
                showErrorPage(500, $errclass . ': ' . $errstr, $message, '', $e->getTraceAsString(), $e, $e->getCode() === 404);
            } else {
                die();
            }
        }
    }
}
