<?php

namespace Fusion\Core;

/**
 * Compiles trusted application templates into instructions. Render data is never
 * interpreted as template syntax or PHP.
 */
final class Template
{
    private const VERSION = '2';
    private const FIELD = '[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*';
    private int $cursor = 0;

    private function __construct(private string $file, private array $tokens)
    {
    }

    public static function render(string $file, array $data): string
    {
        $program = self::load($file, false);
        if ($program['layout'] === null) {
            return self::output($program['body'], $data);
        }

        $layout = BASE_PATH . '/app/layouts/' . $program['layout'] . '.fpf';
        $slots = [];
        foreach ($program['blocks'] as $name => $body) {
            $slots[$name] = self::output($body, $data);
        }
        return self::output(self::load($layout, true)['body'], $data, $slots);
    }

    private static function load(string $file, bool $layout): array
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new \RuntimeException("Template unavailable: {$file}");
        }
        $source = file_get_contents($file);
        $version = hash('sha256', serialize([self::VERSION, $layout, $source]));
        $dir = BASE_PATH . '/storage/cache/templates';
        $cache = $dir . '/' . hash('sha256', $file) . '.php';
        if (!config('app.debug', false) && is_file($cache)) {
            $saved = PhpCache::read($cache);
            if (is_array($saved) && ($saved['version'] ?? null) === $version) {
                return $saved;
            }
        }

        $compiler = new self($file, self::tokenize($file, $source));
        $program = $compiler->compile($layout);
        $program['version'] = $version;
        if (!config('app.debug', false)) {
            PhpCache::write($cache, $program);
        }
        return $program;
    }

    private static function tokenize(string $file, string $source): array
    {
        $pattern = '~\G(?:<!--[\s\S]*?-->|<!doctype html\s*>|</?[a-z][\w-]*(?:"[^"]*"|\x27[^\x27]*\x27|[^"\x27<>])*?>|\{\{(?:"[^"]*"|\x27[^\x27]*\x27|(?!\}\})[^"\x27])*?\}\}|\{%[\s\S]*?%\}|(?:(?!<|\{\{|\{%)[\s\S])+)~i';
        $tokens = [];
        $line = 1;
        for ($offset = 0, $length = strlen($source); $offset < $length;) {
            if (preg_match($pattern, $source, $match, 0, $offset) !== 1) {
                throw new \RuntimeException("{$file}:{$line}: malformed template or HTML");
            }
            $text = $match[0];
            if ($text[0] === '<') {
                self::tag($file, $line, $text, $tokens);
            } else {
                $kind = match (substr($text, 0, 2)) {
                    '{{' => 'value',
                    '{%' => 'command',
                    default => 'text',
                };
                $tokens[] = [$kind, $kind === 'text' ? $text : trim(substr($text, 2, -2)), $line];
            }
            $offset += strlen($text);
            $line += substr_count($text, "\n");
        }
        return $tokens;
    }

    private static function tag(string $file, int $line, string $tag, array &$tokens): void
    {
        // A restricted HTML vocabulary prevents interpolation in raw-text and foreign-content contexts.
        if (preg_match('~^</?([a-z][\w-]*)~i', $tag, $match)) {
            $allowed = 'html head body title meta link main header footer nav section article aside div span p h1 h2 h3 h4 h5 h6 a ul ol li dl dt dd strong em b i small pre code blockquote br hr img form label input textarea button select option table caption thead tbody tfoot tr th td';
            $name = strtolower($match[1]);
            if (!in_array($name, explode(' ', $allowed), true)) {
                throw new \RuntimeException("{$file}:{$line}: unsupported HTML element {$name}");
            }
        }
        if (str_contains($tag, '{%')) {
            throw new \RuntimeException("{$file}:{$line}: directives belong between HTML tags");
        }
        if (!str_contains($tag, '{{')) {
            $tokens[] = ['text', $tag, $line];
            return;
        }

        $original = $tag;
        if (!preg_match('~^<[a-z][\w-]*~i', $tag, $start)) {
            throw new \RuntimeException("{$file}:{$line}: invalid attribute interpolation");
        }
        $tokens[] = ['text', $start[0], $line];
        $tag = substr($tag, strlen($start[0]));
        while (preg_match('~^\s+[a-z_:][\w:.-]*(?:\s*=\s*(?:"[^"]*"|\x27[^\x27]*\x27|[^\s"\x27=<>]+))?~i', $tag, $attr)) {
            $text = $attr[0];
            $tag = substr($tag, strlen($text));
            if (!str_contains($text, '{{')) {
                $tokens[] = ['text', $text, $line];
                continue;
            }
            if (!preg_match('~^(\s+([\w-]+)\s*=\s*)(["\x27])\{\{(.*?)\}\}\3$~sD', $text, $parts)) {
                throw new \RuntimeException("{$file}:{$line}: use a single expression in a quoted attribute");
            }
            $attribute = strtolower($parts[2]);
            $kind = match ($attribute) {
                'href', 'src', 'action', 'formaction' => 'url',
                'class', 'id', 'title', 'alt', 'name', 'value', 'placeholder', 'aria-label' => 'value',
                default => throw new \RuntimeException("{$file}:{$line}: unsupported dynamic attribute {$attribute}"),
            };
            $tokens[] = ['text', $parts[1] . $parts[3], $line];
            $tokens[] = [$kind, trim($parts[4]), $line];
            $tokens[] = ['text', $parts[3], $line];
        }
        if (!preg_match('~^\s*/?>$~D', $tag)) {
            throw new \RuntimeException("{$file}:{$line}: invalid interpolated tag {$original}");
        }
        $tokens[] = ['text', $tag, $line];
    }

    private function compile(bool $isLayout): array
    {
        $this->skipWhitespace();
        $layout = null;
        $blocks = [];
        $token = $this->tokens[$this->cursor] ?? [];
        if (($token[0] ?? null) === 'command' && preg_match('~^extends "([a-zA-Z0-9_-]+)"$~D', $token[1], $match)) {
            if ($isLayout) {
                $this->fail($token[2], 'layouts cannot extend other layouts');
            }
            $layout = $match[1];
            $this->cursor++;
            $this->skipWhitespace();
            while ($block = $this->tokens[$this->cursor] ?? null) {
                if ($block[0] !== 'command' || !preg_match('~^block ([a-zA-Z_][a-zA-Z0-9_]*)$~D', $block[1], $match) || isset($blocks[$match[1]])) {
                    $this->fail($block[2], 'expected a unique named block');
                }
                $this->cursor++;
                [$blocks[$match[1]]] = $this->sequence(['endblock'], false, 0);
                $this->skipWhitespace();
            }
        }
        [$body] = $this->sequence([], $isLayout, 0);
        return ['layout' => $layout, 'blocks' => $blocks, 'body' => $body];
    }

    private function skipWhitespace(): void
    {
        while (($token = $this->tokens[$this->cursor] ?? null) && $token[0] === 'text' && trim($token[1]) === '') {
            $this->cursor++;
        }
    }

    private function sequence(array $stops, bool $allowYield, int $depth): array
    {
        $nodes = [];
        $field = self::FIELD;
        while ($token = $this->tokens[$this->cursor++] ?? null) {
            [$kind, $text, $line] = $token;
            if ($kind === 'text') {
                $nodes[] = ['text', $text];
                continue;
            }
            if ($kind === 'value' || $kind === 'url') {
                $nodes[] = $this->expression($kind, $text, $line);
                continue;
            }
            if (in_array($text, $stops, true)) {
                return [$nodes, $text];
            }
            if ($allowYield && preg_match('~^yield ([a-zA-Z_][a-zA-Z0-9_]*)$~D', $text, $match)) {
                $nodes[] = ['yield', $match[1]];
                continue;
            }
            if ($depth >= 64) {
                $this->fail($line, 'template nesting limit exceeded');
            }
            if (preg_match('~^if (' . $field . ')$~D', $text, $match)) {
                [$yes, $stop] = $this->sequence(['else', 'endif'], $allowYield, $depth + 1);
                $no = $stop === 'else' ? $this->sequence(['endif'], $allowYield, $depth + 1)[0] : [];
                $nodes[] = ['if', explode('.', $match[1]), $yes, $no];
                continue;
            }
            if (preg_match('~^for ([a-zA-Z_][a-zA-Z0-9_]*) in (' . $field . ')$~D', $text, $match)) {
                [$body] = $this->sequence(['endfor'], $allowYield, $depth + 1);
                $nodes[] = ['for', explode('.', $match[2]), $match[1], $body];
                continue;
            }
            $this->fail($line, "unexpected directive {$text}");
        }
        if ($stops !== []) {
            $last = end($this->tokens);
            $this->fail($last[2] ?? 1, 'expected ' . implode(' or ', $stops));
        }
        return [$nodes, null];
    }

    private function expression(string $kind, string $text, int $line): array
    {
        $field = self::FIELD;
        if ($kind === 'url') {
            if (!preg_match('~^url\s+(' . $field . ')$~D', $text, $match)) {
                $this->fail($line, 'URL attributes require an expression such as {{ url link }}');
            }
            return ['url', explode('.', $match[1])];
        }
        if (preg_match('~^' . $field . '$~D', $text)) {
            return ['value', explode('.', $text)];
        }
        if (preg_match('~^(trim|length)\s+(' . $field . ')$~D', $text, $match)) {
            return ['function', explode('.', $match[2]), $match[1], null];
        }
        if (preg_match('~^(default|join)\s+(' . $field . ')\s+(?:"([^"]*)"|\x27([^\x27]*)\x27)$~D', $text, $match, PREG_UNMATCHED_AS_NULL)) {
            return ['function', explode('.', $match[2]), $match[1], $match[3] ?? $match[4]];
        }

        $this->fail($line, 'expected a data field or a supported template function with valid arguments');
    }

    private function fail(int $line, string $message): never
    {
        throw new \RuntimeException("{$this->file}:{$line}: {$message}");
    }

    private static function output(array $nodes, array $data, array $slots = []): string
    {
        $html = '';
        foreach ($nodes as $node) {
            [$kind, $key] = $node;
            if ($kind === 'text' || $kind === 'yield') {
                $html .= $kind === 'text' ? $key : ($slots[$key] ?? '');
                continue;
            }
            $value = $data;
            foreach ($key as $part) {
                $value = is_array($value) ? ($value[$part] ?? null) : null;
            }
            if ($kind === 'if') {
                $html .= self::output($value ? $node[2] : $node[3], $data, $slots);
            } elseif ($kind === 'for') {
                foreach (is_array($value) ? $value : [] as $item) {
                    $dataForItem = $data;
                    $dataForItem[$node[2]] = $item;
                    $html .= self::output($node[3], $dataForItem, $slots);
                }
            } else {
                if ($kind === 'function') {
                    $value = self::applyFunction($node[2], $value, $node[3]);
                }
                $value = is_scalar($value) ? (string) $value : '';
                if ($kind === 'url' && !self::safeUrl($value)) {
                    $value = '#';
                }
                $html .= htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            }
        }
        return $html;
    }

    private static function applyFunction(string $name, mixed $value, ?string $argument): mixed
    {
        return match ($name) {
            'default' => $value === null || $value === '' ? $argument : $value,
            'trim' => is_string($value) ? trim($value) : $value,
            'length' => is_array($value) ? count($value) : (is_string($value) ? (preg_match_all('/./us', $value) ?: 0) : 0),
            'join' => is_array($value) ? implode($argument, array_filter($value, 'is_scalar')) : '',
        };
    }

    private static function safeUrl(string $url): bool
    {
        if (preg_match('~[\x00-\x20\x7f]|\\\\~', $url) || str_starts_with($url, '//')) {
            return false;
        }
        return !preg_match('~^[^/?#]*:~', $url) || preg_match('~^https?://~i', $url) === 1;
    }
}
