<?php

namespace App\Foundation\Http;

use Utils;

class StaticFile
{
    private static string $baseDir;
    private static ?string $cacheDir;

    // public function __construct(string $baseDir, ?string $cacheDir = null)
    // {
    //     $this->baseDir = rtrim($baseDir, '/') . '/';
    //     $this->cacheDir = $cacheDir ? rtrim($cacheDir, '/') . '/' : null;
    // }

    final static function serve(string $root, string $requestUri, ?string $cache_dir = null): bool
    {
        $file = $root . $requestUri;
        self::$baseDir = $root;
        self::$cacheDir = $cache_dir;

        if (!is_file($file)) {
            return false;
        }

        if (!file_exists($file)) {
            http_response_code(404);
            return false;
        }


        // Security check - ensure file is within public directory
        $publicDir = realpath(self::$baseDir . 'public') . DIRECTORY_SEPARATOR;
        $realFile = realpath($file);

        if (strpos($realFile, $publicDir) !== 0 && $requestUri !== '/favicon.ico') {
            Utils::log("ALERT - Unauthorized attempt to access '{$requestUri}' outside public directory.");
            http_response_code(403);
            return false;
        }

        // Check allowed file types
        $prohibitedTypes = explode(',', getenv('GET_DENIED') ?: 'php,htaccess,env');
        $allowedTypes = explode(',', getenv('GET_ALLOWED') ?: 'css,js,html,ico,png,jpg,jpeg,gif,svg');
        $strictMode = filter_var(getenv('GET_STRICT'), FILTER_VALIDATE_BOOLEAN);

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (in_array($ext, $prohibitedTypes) || ($strictMode && !in_array($ext, $allowedTypes))) {
            http_response_code(403);
            return false;
        }

        self::serveMinifiedFile($file);
        return true;
    }

    public static function minifyContent(string $content): string
    {
        // Basic minification that's safe for CSS/HTML
        $content = preg_replace('/[ \t]+$/m', '', $content);
        $content = preg_replace('/\s+/', ' ', $content);
        return trim($content);
    }

    private static function serveMinifiedFile(string $file): void
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        $contentTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'html' => 'text/html',
            'ico' => 'image/x-icon',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
        ];

        header('Content-Type: ' . ($contentTypes[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=3600');

        // For non-minifiable files, serve directly
        if (!in_array($ext, ['css', 'js', 'html']) || self::$cacheDir === null) {
            readfile($file);
            exit;
        }

        $cacheFile = self::$cacheDir . '/minified/' . md5($file) . ".$ext";
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        // echo $cacheFile;
        if (file_exists($cacheFile) && filemtime($cacheFile) >= filemtime($file)) {
            readfile($cacheFile);
            exit;
        }

        $content = file_get_contents($file);
        $minifiedContent = self::minifyContent($content);

        if (file_put_contents($cacheFile, $minifiedContent) !== false) {
            echo $minifiedContent;
        } else {
            // Fallback to original file if cache fails
            readfile($file);
        }

        exit;
    }
}

// namespace App\Foundation\Http;

// use Utils;

// class StaticFile
// {
//     public static string $base_dir;
//     public static ?string $cache_dir;
//     /**
//      * Deciding to serve file statically or let app router to decide
//      */
//     final static function serve(string $base_dir, string $requestUri, ?string $cache_dir = null): bool
//     {
//         $file = $base_dir . $requestUri;
//         self::$base_dir = $base_dir;
//         self::$cache_dir = $cache_dir;

//         if (is_file($file)) {
//             if ($requestUri !== '/favicon.ico' && !fnmatch('public/*', ltrim($requestUri, '/'))) {
//                 Utils::log("ALERT - Unauthorized attempt to access '{$requestUri}' outside public directory.");
//                 http_response_code(403);
//                 return false;
//             }

//             [$prohibitedTypes, $allowedTypes] = array_map(fn($v) => explode(',', getenv($v)), ['GET_DENIED', 'GET_ALLOWED']);
//             [$ext, $strictMode] = [strtolower(pathinfo($file, PATHINFO_EXTENSION)), filter_var(getenv('GET_STRICT'), FILTER_VALIDATE_BOOLEAN)];

//             if (in_array($ext, $prohibitedTypes) || (!in_array($ext, $allowedTypes) && $strictMode)) {
//                 http_response_code(403);
//                 return false;
//             }

//             self::serveMinifiedFile($requestUri);
//             return true;
//         }

//         return false;
//     }

//     public static function minifyContent($content)
//     {
//         $content = preg_replace('/[ \t]+$/m', '', $content);
//         $content = preg_replace('/\s+/', ' ', $content);
//         return trim($content);
//     }


//     private static function serveMinifiedFile($file)
//     {
//         $ext = pathinfo($file, PATHINFO_EXTENSION);

//         if (!file_exists($file) || !is_file($file)) {
//             http_response_code(404);
//             exit('File not found.');
//         }

//         $contentTypes = [
//             'css' => 'text/css',
//             'js' => 'application/javascript',
//             'html' => 'text/html',
//         ];

//         header('Content-Type: ' . $contentTypes[$ext]);
//         header('Cache-Control: public, max-age=3600');

//         $cacheFile = self::$cache_dir . '/cache/minified/' . md5($file) . ".$ext";

//         if (file_exists($cacheFile) && filemtime($cacheFile) >= filemtime($file)) {
//             readfile($cacheFile);
//             exit;
//         }

//         $content = file_get_contents($file);
//         $minifiedContent = minifyContent($content);
//         file_put_contents($cacheFile, $minifiedContent);

//         echo $minifiedContent;
//         exit;
//     }
// }


// // TODO: Make this work
// // final class StaticFile
// // {
// //     private const PUBLIC_DIR = 'public/';
// //     private const CACHE_DIR = 'storage/cache/minified/';
// //     private const ALLOWED_TYPES = ['css', 'js', 'ico', 'png', 'jpg', 'webp', 'svg'];
// //     private const DENIED_TYPES = ['php', 'htaccess', 'env'];

// //     /**
// //      * Serve static files with security checks
// //      */
// //     public static function serve(string $baseDir, string $requestUri): bool
// //     {
// //         $filePath = realpath($baseDir . self::PUBLIC_DIR . ltrim($requestUri, '/'));

// //         // Security checks
// //         if (!$filePath || !str_starts_with($filePath, $baseDir . self::PUBLIC_DIR)) {
// //             return false; // Path traversal attempt
// //         }

// //         if (!is_file($filePath)) {
// //             return false;
// //         }

// //         $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

// //         // Type validation
// //         if (
// //             in_array($ext, self::DENIED_TYPES, true) ||
// //             !in_array($ext, self::ALLOWED_TYPES, true)
// //         ) {
// //             http_response_code(403);
// //             return false;
// //         }

// //         // Serve file
// //         self::outputFile($filePath, $ext);
// //         return true;
// //     }

// //     private static function outputFile(string $filePath, string $ext): never
// //     {
// //         $contentTypes = [
// //             'css'  => 'text/css',
// //             'js'   => 'application/javascript',
// //             'ico'  => 'image/x-icon',
// //             'png'  => 'image/png',
// //             'jpg'  => 'image/jpeg',
// //             'webp' => 'image/webp',
// //             'svg'  => 'image/svg+xml'
// //         ];

// //         header('Content-Type: ' . ($contentTypes[$ext] ?? 'application/octet-stream'));
// //         header('Cache-Control: public, max-age=31536000, immutable');
// //         header('Content-Length: ' . filesize($filePath));

// //         readfile($filePath);
// //         exit;
// //     }
// // }
