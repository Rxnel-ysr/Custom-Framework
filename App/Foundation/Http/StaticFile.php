<?php

namespace App\Foundation\Http;

/**
 * @depends ../core/Database.php
 * @requires ../helpers/functions.php
 *
 * This file depends on Database and helper functions.
 */
class StaticFile
{
    private static string $baseDir;
    private static ?string $cacheDir;

    final static function serve(string $root, string $requestUri, ?string $cache_dir = null): bool
    {
        $file = $root . $requestUri;
        self::$baseDir = rtrim($root, '/') . '/';
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

        header('Content-Type: ' . ($contentTypes[$ext] ?? mime_content_type($file)));
        header('Cache-Control: public, max-age=3600');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($file)) . ' GMT');
        header('ETag: "' . md5_file($file) . '"');

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
