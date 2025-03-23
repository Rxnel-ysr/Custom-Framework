<?php

use App\Utils\Env;

ob_start();
require_once './App/Core/definitions.php';
require_once UTILS_PATH . 'Utility.php';
require_once UTILS_PATH . 'Env.php';
Env::load(ROOT . 'config/.env');

/**
 * Serve the get request, publicly
 * 
 * Note: I've tried to place it some where to make my code looks cleaner but it doesn't work as expected, I'll figured a way later
 * 
 * Update, now I found a way
 * Update, nope, I'll ignore it, probably
 *
 * */
$file = ROOT . $requestUri;

// Check if it's a direct file access
if (file_exists($file) && is_file($file)) {

    if (!fnmatch('public/*', ltrim($requestUri, '/')) && $requestUri !== '/favicon.ico') {
        Utils::log("ALERT - Unauthorized attempt to access '{$requestUri}' outside public directory.");
        showErrorPage(HTTP_FORBIDDEN, 'Access Denied', ' ', 'Unauthorized Access');
        return true;
    }

    [$prohibitedTypes, $allowedTypes] = array_map(fn($v) => explode(',', getenv($v)), ['GET_DENIED', 'GET_ALLOWED']);
    [$isProhibited, $isAllowed, $strictMode, $fileName] = [in_array($ext = strtolower(pathinfo($file, PATHINFO_EXTENSION)), $prohibitedTypes), in_array($ext, $allowedTypes), filter_var(getenv('GET_STRICT'), FILTER_VALIDATE_BOOLEAN), basename($file)];

    if ($isProhibited || (!$isAllowed && $strictMode)) {
        showErrorPage(HTTP_FORBIDDEN, 'Access Denied', ' ', 'Unauthorized Access');
        return true;
    }

    if (preg_match('/\.(css|js|html)$/', $fileName)) {
        error_log('Hitted minified');
        serveMinifiedFile($requestUri);
    }

    return false;
}

require_once CORE . 'bootstrap.php';
