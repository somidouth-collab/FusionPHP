<?php

namespace Fusion\Core;

final class PhpCache
{
    public static function read(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }
        try {
            $value = require $file;
            return is_array($value) ? $value : null;
        } catch (\ParseError | \ErrorException) {
            return null;
        }
    }

    public static function write(string $cache, array $data): void
    {
        $dir = dirname($cache);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }
        $temp = @tempnam($dir, '.fusion-');
        if ($temp === false) {
            return;
        }
        try {
            $bytes = "<?php\nreturn " . var_export($data, true) . ";\n";
            if (@file_put_contents($temp, $bytes) === strlen($bytes) && @rename($temp, $cache)) {
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($cache, true);
                }
                return;
            }
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
    }

}
