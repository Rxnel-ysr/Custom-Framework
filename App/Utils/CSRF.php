<?php
namespace App\utils\Guard;

class CSRF
{
    /**
     * Generate pair of Token and Key
     *
     * @return array `['token' => 'token', 'key' => 'key']`
     */
    public static function generateCSRF(int $unixTime)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.use_strict_mode', 1);
            session_start();
        }

        $uniqueIdentifier = str_rand(16);

        $_SESSION['csrf_secret_' . $uniqueIdentifier] ??= str_rand(32, '_cr');

        $form_key = str_rand(32, 'rf_' . $uniqueIdentifier);
        $token = hash_hmac('sha256', $form_key, $_SESSION['csrf_secret_' . $uniqueIdentifier]);

        $_SESSION['csrf_tokens'][$form_key] = [
            'token' => $token,
            'expires' => time() + $unixTime
        ];

        if (!isset($_COOKIE['CSRF-TOKEN-' . $form_key])) {
            self::setSecureCookie('CSRF-TOKEN-' . $form_key, $token, $unixTime);
            error_log('Created cookie: CSRF-TOKEN-' . $form_key);
        }

        return ['key' => $form_key, 'token' => $token];
    }

    /**
     * Validate CSRF token from request
     *
     * @return true if matched otherwise showing `Error page`
     */
    public static function validateCSRF()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $key = $_POST['csrf_key'] ?? null;
        $token = $_POST['csrf_'] ?? null;
        $cookieToken = $_COOKIE['CSRF-TOKEN-' . $key] ?? null;

        if (!$key || !$token || !isset($_SESSION['csrf_tokens'][$key])) {
            self::handleFailure(HTTP_FORBIDDEN, 'Who are you?');
        }

        $csrfData = $_SESSION['csrf_tokens'][$key];

        if (time() > $csrfData['expires']) {
            // Just to make sure
            self::obliterate();

            // unset($_SESSION['csrf_tokens'][$key]);
            // self::expireCookie($key);

            self::handleFailure(HTTP_REQUEST_TIMEOUT, 'Request timeout, please reload the page', true);
        }

        if (!hash_equals($csrfData['token'], $token)) {
            self::obliterate();
            self::handleFailure(HTTP_FORBIDDEN, 'Who are you?');
        }

        if (!$cookieToken || !hash_equals($csrfData['token'], $cookieToken)) {
            self::obliterate();
            self::handleFailure(HTTP_FORBIDDEN, 'Invalid CSRF cookie.');
        }

        // unset($_SESSION['csrf_tokens'][$key]);
        self::obliterate();
        return true;
    }

    /**
     * Immediately expire given cookie by name
     *
     * @param string $key Name of cookie
     * @return void
     */
    public static function expireCookie($key)
    {
        self::setSecureCookie($key, '', -3600);
    }

    private static function obliterate()
    {
        foreach ($_SESSION['csrf_tokens'] as $key => $_) {
            unset($_SESSION['csrf_tokens'][$key]);
            self::expireCookie('CSRF-TOKEN-' . $key);
            error_log('Deleted used CSRF token and cookie: ' . $key);
        }
    }

    private static function setSecureCookie(string $name, string $value, int $expires)
    {
        setcookie($name, $value, [
            'expires' => time() + $expires,
            'httponly' => true,
            'secure' => true,
            'samesite' => 'Strict'
        ]);
    }

    /**
     * Wrapper for `showErrorPage()`
     */
    private static function handleFailure(int $httpCode, string $message, string $trace = '', bool $reload = false)
    {
        showErrorPage(
            $httpCode,
            'CSRF Validation failed',
            $message,
            'Validation failed',
            $trace,
            null,
            false,
            $reload,
            $reload ? getReferer() : null,
            $reload ? 'Retry' : null
        );
        exit;
    }
}
