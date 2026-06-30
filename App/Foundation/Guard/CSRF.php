<?php

declare(strict_types=1);

namespace App\Foundation\Guard;

use App\Foundation\Exceptions\Framework\Guard\CSRFException;

class CSRF
{

    /**
     * Simple thing to add to your form to be protected from csrf, use with `<?= ?>`
     *
     * @param float|int $unixTime time for token to be expired in Unix
     * @return htmlElement
     */
    public static function csrf($unixTime = 1746008794)
    {
        $csrf = self::generateCSRF($unixTime);

        $token = htmlspecialchars($csrf['token'], ENT_QUOTES, 'UTF-8');
        $key = htmlspecialchars($csrf['key'], ENT_QUOTES, 'UTF-8');

        return "
    <input type='hidden' name='csrf_' value='$token'>
    <input type='hidden' name='csrf_key' value='$key'>
    ";
    }

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

        if (!$key || !$token || !isset($_SESSION['csrf_tokens'][$key])) {
            http_response_code(403);
            throw new CSRFException('CSRF validation failed');
        }
        
        $csrfData = $_SESSION['csrf_tokens'][$key];

        if (time() > $csrfData['expires']) {
            self::obliterate();

            http_response_code(408);
            throw new CSRFException('CSRF validation timeout, please resend form');
        }
        
        if (!hash_equals($csrfData['token'], $token)) {
            http_response_code(403);
            self::obliterate();
            throw new CSRFException('CSRF validation failed');
        }
        
        self::obliterate();
        return true;
    }

    /**
     * Immediately expire given cookie by name
     *
     * @param string $key Name of cookie
     * @return void
     */
    public static function expireCookie(string $key)
    {
        self::setSecureCookie($key, '', -3600);
    }

    private static function obliterate()
    {
        foreach ($_SESSION['csrf_tokens'] as $key => $_) {
            unset($_SESSION['csrf_tokens'][$key]);
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
}
