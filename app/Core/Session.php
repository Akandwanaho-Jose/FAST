<?php

declare(strict_types=1);

namespace FastWebsite\Core;

final class Session
{
    public function __construct(
        private readonly string $name,
        private readonly int $lifetimeMinutes
    ) {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = isset($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== ''
            && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $scriptName = str_replace(
            '\\',
            '/',
            (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')
        );
        $cookiePath = rtrim(dirname($scriptName), '/.');
        $cookiePath = $cookiePath === '' ? '/' : $cookiePath;

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_name($this->name);
        session_set_cookie_params([
            'lifetime' => $this->lifetimeMinutes * 60,
            'path' => $cookiePath,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $now = time();
        $lastActivity = $_SESSION['_last_activity'] ?? null;

        if (is_int($lastActivity)
            && $now - $lastActivity > $this->lifetimeMinutes * 60
        ) {
            $_SESSION = [];
            session_regenerate_id(true);
        }

        $_SESSION['_last_activity'] = $now;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $parameters['path'],
                    'domain' => $parameters['domain'],
                    'secure' => $parameters['secure'],
                    'httponly' => $parameters['httponly'],
                    'samesite' => $parameters['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
    }
}
