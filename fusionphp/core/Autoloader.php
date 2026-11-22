<?php
// fusionphp/core/Autoloader.php

namespace Fusion\Core;

class Autoloader
{
    private static bool $dev = true; // false in production

    private static string $cacheFile = BASE_PATH . '/storage/cache/classmap.php';

    private static array $prefixes = [
        'App\\' => BASE_PATH . '/app/',
        'Fusion\\' => BASE_PATH . '/fusionphp/',
    ];

    private static array $classMap = [];
    private static bool $scanned = false;

    public static function register(bool $dev = true): void
    {
        self::$dev = $dev;

        // Dev: always fresh — no cache
        // Prod: load from cache immediately
        if (!self::$dev) {
            self::loadClassMap();
        }

        spl_autoload_register(function (string $class): void {
            if (!preg_match('~\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z~', $class)) {
                return;
            }
            $matched = false;
            foreach (self::$prefixes as $prefix => $base) {
                if (!str_starts_with($class, $prefix)) {
                    continue;
                }
                $matched = true;
                $parts = explode('\\', substr($class, strlen($prefix)));
                $file = array_pop($parts) . '.php';
                $dir = implode('/', array_map('strtolower', $parts));
                $path = $base . ($dir === '' ? '' : $dir . '/') . $file;
                if (is_file($path)) {
                    require_once $path;
                    return;
                }
                break;
            }

            if (!$matched) {
                return;
            }

            // Fast path — already in map
            if (isset(self::$classMap[$class]) && is_file(self::$classMap[$class])) {
                require_once self::$classMap[$class];
                return;
            }

            // Scan filesystem and rebuild
            if (!self::$scanned) {
                self::buildClassMap();
            }

            if (isset(self::$classMap[$class]) && is_file(self::$classMap[$class])) {
                require_once self::$classMap[$class];
            }
        });
    }

    private static function loadClassMap(): void
    {
        if (file_exists(self::$cacheFile)) {
            self::$classMap = PhpCache::read(self::$cacheFile) ?? [];
        }
    }

    private static array $skipDirs = ['views', 'layouts', 'assets'];

    private static function buildClassMap(): void
    {
        self::$scanned = true;
        self::$classMap = [];
        foreach (self::$prefixes as $prefix => $baseDir) {
            if (!is_dir($baseDir))
                continue;

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $baseDir,
                    \RecursiveDirectoryIterator::SKIP_DOTS
                )
            );

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php')
                    continue;

                // Skip non-class directories
                $filePath = $file->getRealPath();
                $skip = false;
                foreach (self::$skipDirs as $dir) {
                    if (str_contains($filePath, DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR)) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip)
                    continue;

                $relativePath = ltrim(
                    str_replace($baseDir, '', $filePath),
                    DIRECTORY_SEPARATOR
                );

                $segments = explode(DIRECTORY_SEPARATOR, $relativePath);
                $segments = array_map('ucfirst', $segments);
                $relativePath = implode(DIRECTORY_SEPARATOR, $segments);

                $class = $prefix . str_replace(
                    [DIRECTORY_SEPARATOR, '.php'],
                    ['\\', ''],
                    $relativePath
                );

                self::$classMap[$class] = $filePath;
            }
        }

        if (!self::$dev) {
            self::saveCache();
        }
    }

    private static function saveCache(): void
    {
        PhpCache::write(self::$cacheFile, self::$classMap);
    }

    public static function clearCache(): void
    {
        if (file_exists(self::$cacheFile)) {
            unlink(self::$cacheFile);
        }
        self::$classMap = [];
        self::$scanned = false;
    }

    // Add to Autoloader class temporarily
    public static function debug(): void
    {
        self::buildClassMap();
        foreach (self::$classMap as $class => $path) {
            echo $class . ' => ' . $path . '<br>';
        }
        exit;
    }
}
