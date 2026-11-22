<?php

use Fusion\Core\View;

function e(mixed $value): string
{
    return View::getCurrent()->e($value);
}

function partial(string $component, array $data = []): void
{
    View::getCurrent()->partial($component, $data);
}

function section(string $name): void
{
    View::getCurrent()->section($name);
}

function end_section(): void
{
    View::getCurrent()->end();
}

function yield_section(string $name, string $default = ''): string
{
    return View::getCurrent()->yield($name, $default);
}

function layout(string $name): void
{
    View::getCurrent()->setLayout($name);
}

// Dump and continue — use anywhere in controllers or views
function debug(mixed $value, string $label = ''): void
{
    \Fusion\Core\Debugger::log($value, $label);
}

// Authentication Helpers
function isLoggedIn(): bool
{
    return \Fusion\Core\Session::isLoggedIn();
}

function getCurrentUser(): ?array
{
    return \Fusion\Core\Session::user();
}

function getCurrentUserId(): ?int
{
    $user = getCurrentUser();
    return $user['id'] ?? null;
}

function getCurrentUserName(): string
{
    $user = getCurrentUser();
    return $user['name'] ?? $user['username'] ?? 'Guest';
}

function getUserEmail(): string
{
    $user = getCurrentUser();
    return $user['email'] ?? '';
}

function getUserAvatar(): string
{
    $user = getCurrentUser();
    $name = $user['name'] ?? $user['username'] ?? 'U';

    // Get first letter or use default icon
    if (isset($user['avatar'])) {
        return '<img src="' . e($user['avatar']) . '" alt="Avatar">';
    }

    // Generate colored avatar with initial
    $colors = ['#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6'];
    $color = $colors[array_rand($colors)];

    return '<div class="avatar-initial" style="background-color: ' . $color . '">'
        . strtoupper(substr($name, 0, 1)) . '</div>';
}

function hasRole(string $role): bool
{
    $user = getCurrentUser();
    $userRole = $user['role'] ?? 'guest';

    $roles = [
        'guest' => 0,
        'user' => 1,
        'editor' => 2,
        'admin' => 3,
        'super_admin' => 4
    ];

    return isset($roles[$role]) && ($roles[$userRole] ?? 0) >= $roles[$role];
}

function isActive(string $path, string $activeClass = 'active'): string
{
    $currentPath = \Fusion\Core\Request::getInstance()->uri();
    $path = \Fusion\Core\Request::normalizePath($path);
    $active = $currentPath === $path || ($path !== '/' && str_starts_with($currentPath, $path . '/'));

    return $active ? $activeClass : '';
}

// Flash Message Helpers
function hasFlash(): bool
{
    return \Fusion\Core\Flash::all() !== [];
}

function getFlashType(): string
{
    return array_key_last(\Fusion\Core\Flash::all()) ?? 'info';
}

function getFlashMessage(): string
{
    return \Fusion\Core\Flash::get(getFlashType(), '');
}

function getFlashIcon(string $type): string
{
    return match ($type) {
        'success' => '<i class="fas fa-check-circle"></i>',
        'error' => '<i class="fas fa-times-circle"></i>',
        'warning' => '<i class="fas fa-exclamation-triangle"></i>',
        'info' => '<i class="fas fa-info-circle"></i>',
        default => '<i class="fas fa-bell"></i>'
    };
}

// Validation Helpers
function hasValidationErrors(): bool
{
    return isset($_SESSION['validation_errors']) && !empty($_SESSION['validation_errors']);
}

function getValidationErrors(): array
{
    $errors = $_SESSION['validation_errors'] ?? [];
    unset($_SESSION['validation_errors']);
    return $errors;
}

function old(string $key, mixed $default = ''): string
{
    return e($_SESSION['old_input'][$key] ?? $default);
}
