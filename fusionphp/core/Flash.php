<?php

namespace Fusion\Core;

class Flash
{
    private const CURRENT = '_flash_current';
    private const NEXT = '_flash_next';

    // Called once per request in AppMiddleware::before()
    // Promotes _next into _current, clears _next
    public static function age(): void
    {
        $_SESSION[self::CURRENT] = $_SESSION[self::NEXT] ?? [];
        $_SESSION[self::NEXT] = [];
    }

    // ── Write (available NEXT request) ────────────────────────

    public static function set(string $key, string $message): void
    {
        $_SESSION[self::NEXT][$key] = $message;
    }

    // Convenience type shortcuts
    public static function success(string $message): void
    {
        self::set('success', $message);
    }

    public static function error(string $message): void
    {
        self::set('error', $message);
    }

    public static function info(string $message): void
    {
        self::set('info', $message);
    }

    public static function warning(string $message): void
    {
        self::set('warning', $message);
    }

    // ── Read (current request, consumed once) ─────────────────

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[self::CURRENT][$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[self::CURRENT][$key]);
    }

    public static function all(): array
    {
        return $_SESSION[self::CURRENT] ?? [];
    }
}