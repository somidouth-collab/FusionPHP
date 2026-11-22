<?php

namespace Fusion\Core;

final class Auth
{
    private const DUMMY_HASH = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    public static function attempt(string $password, ?array $account): bool
    {
        if (!Session::isStarted()) {
            throw new \LogicException('Start a session before signing in.');
        }

        $storedHash = $account['password_hash'] ?? null;
        $hash = is_string($storedHash) && $storedHash !== '' ? $storedHash : self::DUMMY_HASH;
        $id = $account['id'] ?? null;
        // Bcrypt ignores bytes beyond its 72-byte input limit.
        if ($password === '' || str_contains($password, "\0")
            || (str_starts_with($hash, '$2') && strlen($password) > 72)) {
            return false;
        }
        $matches = password_verify($password, $hash);
        if (!$matches || !is_string($storedHash) || $storedHash === ''
            || !(is_int($id) || (is_string($id) && $id !== ''))) {
            return false;
        }

        $permissions = $account['permissions'] ?? [];
        $identity = [
            'id' => $id,
            'permissions' => is_array($permissions)
                ? array_values(array_filter($permissions, 'is_string'))
                : [],
        ];
        if (is_string($account['name'] ?? null)) {
            $identity['name'] = $account['name'];
        }
        Session::login($identity);
        return true;
    }

    public static function can(string $permission): bool
    {
        $permissions = Session::user()['permissions'] ?? null;
        return $permission !== '' && is_array($permissions) && in_array($permission, $permissions, true);
    }

    public static function requirePermission(string $permission): void
    {
        if (!Session::isLoggedIn()) {
            throw new HttpException(401);
        }
        if (!self::can($permission)) {
            throw new HttpException(403);
        }
    }
}
