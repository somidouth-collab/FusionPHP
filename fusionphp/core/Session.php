<?php

namespace Fusion\Core;

class Session
{
    private static bool $started = false;

    public static function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        session_name(config('session.name', 'fusion_session'));

        session_set_cookie_params([
            'lifetime' => config('session.lifetime', 0),
            'path' => '/',
            'secure' => config('app.url', '') !== '' && str_starts_with(config('app.url'), 'https'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        self::$started = true;
    }

    // ── Read ──────────────────────────────────────────────────

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function all(): array
    {
        return $_SESSION ?? [];
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    // ── Write ─────────────────────────────────────────────────

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }

    // ── Auth shortcuts ────────────────────────────────────────

    public static function login(array $user): void
    {
        // Regenerate ID on privilege change — prevents session fixation
        session_regenerate_id(true);
        Csrf::reset();
        $_SESSION['_user'] = $user;
    }

    public static function logout(): void
    {
        session_regenerate_id(true);
        Csrf::reset();
        unset($_SESSION['_user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['_user'] ?? null;
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['_user']);
    }

    // ── Lifecycle ─────────────────────────────────────────────

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $p['path'],
                $p['domain'],
                $p['secure'],
                $p['httponly']
            );
        }
        session_destroy();
        self::$started = false;
    }
}