<?php

namespace Fusion\Core;

final class Environment
{
    private array $values = [];

    public function __construct(string $file)
    {
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $index => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!preg_match('/\A([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)\z/', $line, $parts)) {
                throw new \RuntimeException('Invalid environment setting on line ' . ($index + 1));
            }
            [, $key, $value] = $parts;
            $quote = $value[0] ?? '';
            if ($quote === '"' || $quote === "'") {
                $end = strpos($value, $quote, 1);
                if ($end === false || !preg_match('/\A\s*(?:#.*)?\z/', substr($value, $end + 1))) {
                    throw new \RuntimeException('Invalid quoted environment value on line ' . ($index + 1));
                }
                $value = substr($value, 1, $end - 1);
            } else {
                $value = trim(preg_split('/(?:^|\s)#/', $value, 2)[0]);
            }
            $this->values[$key] = $value;
        }
    }

    public function string(string $key, string $default = ''): string
    {
        $runtime = getenv($key);
        return $runtime !== false ? $runtime : ($this->values[$key] ?? $default);
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = filter_var($this->string($key, $default ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $value ?? throw new \RuntimeException("{$key} expects a boolean");
    }

    public function integer(string $key, ?int $default, int $min, int $max): ?int
    {
        $value = $this->string($key, $default === null ? '' : (string) $default);
        if ($value === '' && $default === null) {
            return null;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        return $integer !== false ? $integer : throw new \RuntimeException("{$key} expects an integer between {$min} and {$max}");
    }

    public function choice(string $key, string $default, array $choices): string
    {
        $value = strtolower($this->string($key, $default));
        return in_array($value, $choices, true) ? $value : throw new \RuntimeException("{$key} expects " . implode(', ', $choices));
    }

    public function path(string $key, string $default = ''): string
    {
        $value = trim($this->string($key, $default), '/');
        if ($value !== '' && !preg_match('~\A[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*\z~', $value)) {
            throw new \RuntimeException("{$key} expects a URL path made of letters, digits, hyphens, or underscores");
        }
        return $value === '' ? '' : '/' . $value;
    }
}
