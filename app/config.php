<?php

use Fusion\Core\Environment;

require_once BASE_PATH . '/fusionphp/core/Environment.php';

function config(string $key, mixed $default = null): mixed
{
    static $settings;
    if ($settings === null) {
        $env = new Environment(BASE_PATH . '/.env');
        $sqlite = $env->string('DB_PATH', 'storage/database.sqlite');
        $prefix = $env->path('API_PREFIX', '/api');
        if ($prefix === '') {
            throw new RuntimeException('API_PREFIX requires a non-empty path');
        }

        $settings = [
            'app.name' => $env->string('APP_NAME', 'FusionPHP'),
            'app.url' => $env->string('APP_URL', 'http://localhost:8000'),
            'app.debug' => $env->boolean('FUSIONPHP_DEBUG'),
            'app.version' => $env->string('APP_VERSION', '1.0.0'),
            'app.mode' => $env->choice('APP_MODE', 'both', ['both', 'web', 'api']),
            'app.base_path' => $env->path('APP_BASE_PATH'),

            'database.driver' => $env->choice('DB_DRIVER', 'sqlite', ['sqlite', 'mysql', 'pgsql']),
            'database.host' => $env->string('DB_HOST', '127.0.0.1'),
            'database.port' => $env->integer('DB_PORT', null, 1, 65535),
            'database.name' => $env->string('DB_NAME', 'fusionphp'),
            'database.user' => $env->string('DB_USER'),
            'database.password' => $env->string('DB_PASSWORD'),
            'database.charset' => $env->string('DB_CHARSET', 'utf8mb4'),
            'database.path' => str_starts_with($sqlite, '/') || $sqlite === ':memory:' ? $sqlite : BASE_PATH . '/' . $sqlite,

            'mail.host' => $env->string('MAIL_HOST'),
            'mail.port' => $env->integer('MAIL_PORT', 587, 1, 65535),
            'mail.username' => $env->string('MAIL_USERNAME'),
            'mail.password' => $env->string('MAIL_PASSWORD'),
            'mail.from' => $env->string('MAIL_FROM', 'no-reply@fusionphp.com'),

            'api.prefix' => $prefix,
            'api.session' => $env->boolean('API_SESSION'),
            'api.key' => $env->string('API_KEY'),
            'api.base_url' => $env->string('API_BASE_URL'),

            'session.name' => $env->string('SESSION_NAME', 'fusion_session'),
            'session.lifetime' => $env->integer('SESSION_LIFETIME', 0, 0, PHP_INT_MAX),
        ];
    }

    return $settings[$key] ?? $default;
}
