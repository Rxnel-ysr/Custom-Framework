<?php

use App\Debug\Debugger;
use App\utils\Guard\CSRF;
use App\Utils\Http\Response;
use App\Utils\Manager\InstanceManager;

$logFile = ROOT . '/storage/logs/server.log';
$resources = ROOT . '/resources/';
$views = ROOT . '/resources/views';
$ErrorPage = CORE . '/error.php';
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

class Unix
{
    public static function days(int|float $days): int|float
    {
        return $days * 86400;
    }

    public static function hours(int|float $hours): int|float
    {
        return $hours * 3600;
    }

    public static function minutes(int|float $minutes): int|float
    {
        return $minutes * 60;
    }
}

class Utils
{
    /**
     * Sanitizes input data by converting special characters to HTML entities.
     *
     * @param string $data The input data to sanitize.
     * @return string The sanitized string.
     */
    public static function sanitize(string $data): string
    {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Safely retrieves the basename of a given path.
     *
     * @param string $data The path to retrieve the basename from.
     * @param string $suffix Optional. A suffix to be removed from the basename.
     * @return string The basename of the path.
     * @throws Exception If the provided path is not a valid string or does not contain a filename.
     */
    public static function getBaseName(
        string $data,
        string $suffix = ''
    ): string {
        if (!is_string($data) || empty($data)) {
            throw new Exception(
                'The provided file does not contain a valid filename.'
            );
        }

        $baseName = basename($data, $suffix);

        if ($baseName === '') {
            throw new Exception('Invalid filename provided.');
        }

        return $baseName;
    }

    /**
     * Refresh current page
     *
     * @param int $delay Delay for refresh
     * @return void
     */
    public static function refresh(int $delay)
    {
        header('refresh: ' . $delay);
        exit();
    }

    /**
     * Logs a message to the log file.
     *
     * @param string $message The message to log.
     * @return void
     */
    public static function log(string $message, bool $logUser = true): void
    {
        global $logFile;
        $user = $logUser ? self::getUserInfo() : '[User info deactivated]';

        file_put_contents(
            $logFile,
            '[' . date('Y-m-d H:i:s') . '] { ' . $message . ' - ' . $user . " }\n",
            FILE_APPEND
        );
    }

    /**
     * Collects user information and returns it as a string.
     *
     * @return string The collected user information.
     */
    public static function getUserInfo(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        $referrer = isset($_SERVER['HTTP_REFERER'])
            ? $_SERVER['HTTP_REFERER']
            : 'No referrer';

        $protocol =
            !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
            ? 'https'
            : 'http';

        $currentUrl =
            $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        $acceptedLanguages = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'N\A';

        $requestMethod = $_SERVER['REQUEST_METHOD'];

        $requestTime = $_SERVER['REQUEST_TIME'];

        return "User Info: 'IP: $ip, User-Agent: $userAgent, Referrer: $referrer, URL: $currentUrl, Accepted Languages: $acceptedLanguages, Request Method: $requestMethod, Request Time: $requestTime'";
    }
}

function response($code = 200): Response
{
    $instance = InstanceManager::getInstance(Response::class);
    $instance->status($code);
    return $instance;
}

function config(string $path)
{
    return require $path;
}

/**
 * Reads a file and returns its content as a string, excluding specified line ranges.
 *
 * @param string $filename The path to the file.
 * @param int $flags Flags for file reading (e.g., FILE_SKIP_EMPTY_LINES, FILE_IGNORE_NEW_LINES).
 * @param array<int, int>[] $ignoreRanges List of line ranges to ignore:
 *        - [start, end] to ignore a range of lines (inclusive).
 *        - [line] to ignore a single specific line.
 *
 * @throws Exception If the file does not exist.
 *
 * @return string The file content with ignored lines removed.
 */
function getContent(string $filename, int $flags, array ...$ignoreRanges): string
{
    if (!file_exists($filename)) {
        throw new Exception("File not found: $filename");
    }

    $lines = file($filename, $flags);
    $filteredLines = [];

    foreach ($lines as $i => $line) {
        $lineNum = $i + 1;

        $skip = false;
        foreach ($ignoreRanges as $range) {
            if (isset($range[1])) {
                if ($lineNum >= $range[0] && $lineNum <= $range[1]) {
                    $skip = true;
                    break;
                }
            } elseif ($lineNum === $range[0]) {
                $skip = true;
                break;
            }
        }

        if (!$skip) {
            $filteredLines[] = $line;
        }
    }

    return implode("\n", $filteredLines);
}

/**
 * Simple thing to add to your form to be protected from csrf, use with `<?= ?>`
 *
 * @param float|int $unixTime time for token to be expired in Unix
 * @return htmlElement
 */
function csrf($unixTime)
{
    $csrf = CSRF::generateCSRF($unixTime);

    $token = htmlspecialchars($csrf['token'], ENT_QUOTES, 'UTF-8');
    $key = htmlspecialchars($csrf['key'], ENT_QUOTES, 'UTF-8');

    return "
    <input type='hidden' name='csrf_' value='$token'>
    <input type='hidden' name='csrf_key' value='$key'>
    ";
}

function clearUrl($url)
{
    return preg_replace('~(?<!:)//+~', '/', $url);
}

function cleanPath($path)
{
    return preg_replace('~//+~', '/', $path);
}

function str_rand($length = 16, $prefix = '')
{
    return $prefix . bin2hex(random_bytes(intdiv($length, 2) + ($length % 2)));
}

function printAsJson($data, $additionalOption = 0)
{
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode($data, $additionalOption);
    exit;
}

function env($name, $default)
{
    $result = getenv($name);
    return $result != false ? $result : $default;
}

/**
 * A minimalist file-loader with file validation
 */
function load(array|string $paths, array|string $excepts = [], array|string $only = []): void
{
    $allFiles = [];

    foreach ((array) $paths as $path) {
        $realPath = realpath($path);
        if (!$realPath || !is_readable($realPath))
            continue;
        // && pathinfo($realPath, PATHINFO_EXTENSION) === 'php'
        if (is_file($realPath)) {
            $allFiles[$realPath] = true;
        } elseif (is_dir($realPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($realPath, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS)
            );

            foreach ($iterator as $file) {
                $filePath = $file->getRealPath();
                if ($file->isFile()) {
                    // && pathinfo($filePath, PATHINFO_EXTENSION) === 'php'
                    $allFiles[$filePath] = true;
                }
            }
        }
    }

    $excepts = array_flip(array_filter(array_map('realpath', (array) $excepts)));
    $only = $only ? array_flip(array_filter(array_map('realpath', (array) $only))) : null;

    $filteredFiles = array_diff_key($allFiles, $excepts);
    if ($only)
        $filteredFiles = array_intersect_key($filteredFiles, $only);

    foreach (array_keys($filteredFiles) as $file) {
        try {
            // $content = trim(file_get_contents($file));
            // if ($content === '' || $content === "<?php\n"){ error_log('Auto-loader: skipped file '. $file);continue;}

            require_once $file;
        } catch (Throwable $e) {
            Debugger::dumpErr($e);
            showErrorPage(HTTP_SERVER_ERROR, 'Auto-loader: ' . $e->getMessage(), Debugger::trimPath($file) . ' on line ' . $e->getLine());
        }
    }

    error_log('File-loader: Loaded all files successfully.');
}

function timeExecution(callable $func, &$result = null): float
{
    $start = hrtime(true);
    $result = $func();
    return (hrtime(true) - $start) / 1.0e6;
}

function dd(...$args)
{
    echo '<style>
            body { background: #111; color: #0f0; font-family: monospace; padding: 10px; }
            .dump-container { background: #222; padding: 10px; border-radius: 5px; margin: 10px 0; }
            .dump-header { color: #f00; font-weight: bold; margin-bottom: 5px; cursor: pointer; }
            .dump-content { white-space: pre-wrap; font-size: 14px; display: none; padding: 5px; border-left: 2px solid #f00; }
        </style>';

    echo "<script>
            function toggleDump(id) {
                var el = document.getElementById(id);
                el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
            }
        </script>";

    foreach ($args as $index => $arg) {
        $dumpId = 'dump_' . uniqid();
        echo "<div class='dump-container'>";
        echo "<div class='dump-header' onclick='toggleDump(\"$dumpId\")'> Dump #$index (click to expand)</div>";
        echo "<div class='dump-content' id='$dumpId'><pre>";

        // Convert objects before exporting
        // if (is_object($arg)) {
        //     $arg = convert_object($arg);
        // }

        echo htmlspecialchars(var_export($arg, true));

        echo '</pre></div></div>';
    }

    exit;
}

function convert_object($obj)
{
    if (!is_object($obj))
        return var_export($obj);

    $reflection = new ReflectionClass($obj);
    $properties = [];

    foreach ($reflection->getProperties() as $prop) {
        $prop->setAccessible(true);
        $properties[$prop->getName()] = $prop->getValue($obj);
    }

    return [
        '__Class' => $reflection->getName(),
        '__Properties' => $properties,
        '__Methods' => array_map(fn($m) => $m->getName(), $reflection->getMethods()),
    ];
}

function minifyContent($content)
{
    $content = preg_replace('/[ \t]+$/m', '', $content);
    $content = preg_replace('/\s+/', ' ', $content);
    return trim($content);
}

function serveMinifiedFile($requestUri)
{
    $file = ROOT . $requestUri;
    $ext = pathinfo($file, PATHINFO_EXTENSION);

    if (!file_exists($file) || !is_file($file)) {
        http_response_code(404);
        exit('File not found!');
    }

    $contentTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'html' => 'text/html',
    ];

    header('Content-Type: ' . $contentTypes[$ext]);
    header('Cache-Control: public, max-age=3600');

    $cacheFile = STORAGE_PATH . 'cache/minified/' . md5($requestUri) . ".$ext";

    if (file_exists($cacheFile) && filemtime($cacheFile) >= filemtime($file)) {
        readfile($cacheFile);
        exit;
    }

    $content = file_get_contents($file);
    $minifiedContent = minifyContent($content);
    file_put_contents($cacheFile, $minifiedContent);

    echo $minifiedContent;
    exit;
}

function showErrorPage(
    int $errorCode,
    string $customMessage = '',
    string $customSubMessage = '',
    string $customTitleName = '',
    string $trace = '',
    Throwable|Exception|null $e = null,
    bool $add_new_class = false,
    bool $returnButton = false,
    string|null $urlForButton = null,
    string|null $btnTextContent = null
): void {
    global $ErrorPage;

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

    $returnButton = $returnButton;
    $url = $urlForButton;
    $btnTextContent;
    $add_new_class;
    $trace;
    $e;

    if (ob_get_length()) {
        ob_end_clean();  // Use ob_end_clean() instead of ob_clean() to discard and close the buffer
    }
    require_once $ErrorPage;
    if (ob_get_length()) {
        ob_end_flush();  // Flush output safely
    }
    exit();
}
