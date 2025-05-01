<?php

namespace App\Foundation\Http;

use App\Debug\Debugger;
use Utils;

class StaticFile
{
    /**
     * Deciding to serve file statically or let app router to decide
     */
    public static function serve(string $base_dir, string $requestUri): bool
    {
        $file = $base_dir . $requestUri;

        if (is_file($file)) {
            if ($requestUri !== '/favicon.ico' && !fnmatch('public/*', ltrim($requestUri, '/'))) {
                Utils::log("ALERT - Unauthorized attempt to access '{$requestUri}' outside public directory.");
                http_response_code(403);
                return false;
            }
            
            [$prohibitedTypes, $allowedTypes] = array_map(fn($v) => explode(',', getenv($v)), ['GET_DENIED', 'GET_ALLOWED']);
            [$ext, $strictMode] = [strtolower(pathinfo($file, PATHINFO_EXTENSION)), filter_var(getenv('GET_STRICT'), FILTER_VALIDATE_BOOLEAN)];
            
            if (in_array($ext, $prohibitedTypes) || (!in_array($ext, $allowedTypes) && $strictMode)) {
                http_response_code(403);
                return false;
            }

            return true;
        }

        return false;
    }
}
