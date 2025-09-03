<?php

namespace App\Foundation\Authentication;

(session_status() !== PHP_SESSION_ACTIVE) && session_start();

class Auth
{
    protected string $type = 'web';
    protected static ?self $instance = null;
    protected array $guards = [];
    protected ?string $currentGuard = null;

    private function __construct()
    {
        // Private constructor to enforce singleton pattern
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']) || isset($_SESSION['auth_user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? $_SESSION['auth_user'] ?? null;
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function attempt(array $credentials): bool
    {
        // Implementation would depend on your user provider
        // This is just a basic example
        $user = self::validateCredentials($credentials);

        if ($user) {
            $_SESSION['user'] = $user;
            return true;
        }

        return false;
    }

    public static function login(array $user): void
    {
        $_SESSION['user'] = $user;
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
        session_destroy();
    }

    public function guard(?string $guardName = null): self
    {
        if ($guardName !== null) {
            $this->currentGuard = $guardName;
            if (!isset($this->guards[$guardName])) {
                $this->guards[$guardName] = [];
            }
        }
        return $this;
    }

    public function getCurrentGuard(): ?string
    {
        return $this->currentGuard;
    }

    protected static function validateCredentials(array $credentials): ?array
    {
        // This would typically query your user database/provider
        // Mock implementation:
        if ($credentials['email'] === 'user@example.com' && $credentials['password'] === 'password') {
            return [
                'id' => 1,
                'email' => 'user@example.com',
                'name' => 'Test User'
            ];
        }
        return null;
    }

    public static function viaRemember(): bool
    {
        // Check if logged in via remember me cookie
        return isset($_COOKIE['remember_token']) && self::check();
    }

    public static function validate(array $credentials): bool
    {
        return self::validateCredentials($credentials) !== null;
    }
}
