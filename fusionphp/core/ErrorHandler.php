<?php

namespace Fusion\Core;

class ErrorHandler
{
    private static bool $dev = true;
    private static bool $json = false;
    private static string $logFile = BASE_PATH . '/storage/logs/error.log';

    private static array $httpMessages = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        415 => 'Unsupported Media Type',
        419 => 'Session Expired',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        503 => 'Service Unavailable',
    ];

    // ── Register ──────────────────────────────────────────────

    public static function register(bool $dev = true): void
    {
        self::$dev = $dev;

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);

        ini_set('display_errors', '0');
        error_reporting(E_ALL);
    }

    // ── HTTP Abort — called from Router and Controller ────────

    public static function setDebug(bool $debug): void
    {
        self::$dev = $debug;
    }

    public static function setJson(bool $json): void
    {
        self::$json = $json;
    }

    public static function abort(int $code, string $message = ''): never
    {
        http_response_code($code);

        if ($message === '' || $code >= 500) {
            $message = self::$httpMessages[$code] ?? 'Unknown Error';
        }

        // Log 5xx only — 4xx are normal user behaviour
        if ($code >= 500) {
            self::logHttp($code, $message);
        }

        if (self::$json) {
            self::renderJson($code, $message);
        } elseif (self::$dev) {
            self::renderHttpDev($code, $message);
        } else {
            self::renderHttpProd($code, $message);
        }

        exit;
    }

    // ── PHP Error / Exception Handlers ───────────────────────

    public static function handleError(
        int $level,
        string $message,
        string $file = '',
        int $line = 0
    ): bool {
        if (!(error_reporting() & $level)) {
            return false;
        }
        throw new \ErrorException($message, 0, $level, $file, $line);
    }

    public static function handleException(\Throwable $e): void
    {
        if ($e instanceof HttpException) {
            self::abort($e->status, $e->getMessage());
        }

        self::log($e);
        http_response_code(500);

        if (self::$json) {
            self::renderJson(500, self::$httpMessages[500]);
        } elseif (self::$dev) {
            self::renderDev($e);
        } else {
            self::renderHttpProd(500, self::$httpMessages[500]);
        }

        exit;
    }

    private static function renderJson(int $code, string $message): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode(['error' => $message, 'status' => $code], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::handleException(new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    }

    // ── HTTP Dev output — shows code + context ────────────────

    private static function renderHttpDev(int $code, string $message): void
    {
        // Walk the call stack to find who called abort()
        $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $trace   = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $caller  = self::resolveCaller($trace);
        $file    = htmlspecialchars($caller['file'] ?? 'unknown');
        $line    = $caller['line'] ?? 0;
        $snippet = $caller['file'] ? self::getCodeSnippet($caller['file'], $line) : '';
        $traceHtml = self::formatTrace($trace);

        echo <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>{$code} {$message} — FusionPHP</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: monospace; background: #0f0f0f; color: #e0e0e0; padding: 2rem; }
                .badge { display: inline-block; padding: 2px 10px; border-radius: 4px; font-size: 12px; margin-bottom: 1rem; }
                .badge-404 { background: #e67e22; color: #fff; }
                .badge-403 { background: #8e44ad; color: #fff; }
                .badge-401 { background: #2980b9; color: #fff; }
                .badge-5xx { background: #c0392b; color: #fff; }
                h1 { font-size: 1.4rem; margin-bottom: 0.5rem; color: #ff6b6b; }
                .meta { color: #888; font-size: 13px; margin-bottom: 1.5rem; }
                .meta span { color: #aaa; }
                .section { margin-bottom: 1.5rem; }
                .section h2 { font-size: 12px; text-transform: uppercase; color: #555; letter-spacing: 1px; margin-bottom: 0.5rem; }
                .code { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 6px; padding: 1rem; overflow-x: auto; }
                .code table { border-collapse: collapse; width: 100%; }
                .code td { padding: 2px 12px; font-size: 13px; line-height: 1.6; white-space: pre; }
                .code td:first-child { color: #555; text-align: right; user-select: none; border-right: 1px solid #2a2a2a; padding-right: 16px; min-width: 48px; }
                .highlight td { background: #2a1a1a; }
                .highlight td:last-child { color: #ff6b6b; }
                .trace { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 6px; padding: 1rem; font-size: 12px; line-height: 1.8; color: #aaa; overflow-x: auto; white-space: pre; }
                .frame { padding: 4px 0; border-bottom: 1px solid #1e1e1e; }
                .frame:last-child { border: none; }
                .frame-file { color: #569cd6; }
                .frame-fn { color: #dcdcaa; }
                .frame-line { color: #b5cea8; }
            </style>
        </head>
        <body>
            <div class="badge badge-{$code}">{$code}</div>
            <h1>{$message}</h1>
            <div class="meta">
                Triggered in <span>{$file}</span> on line <span>{$line}</span>
            </div>

            <div class="section">
                <h2>Where abort() was called</h2>
                <div class="code">{$snippet}</div>
            </div>

            <div class="section">
                <h2>Call stack</h2>
                <div class="trace">{$traceHtml}</div>
            </div>
        </body>
        </html>
        HTML;
    }

    // ── HTTP Prod output — clean, no details ──────────────────

    private static function renderHttpProd(int $code, string $message): void
    {
        $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // Try custom view: app/layouts/errors/404.php
        $errorView = BASE_PATH . "/app/layouts/errors/{$code}.php";

        if (file_exists($errorView)) {
            require $errorView;
            return;
        }

        // Generic fallback
        $bg = match(true) {
            $code >= 500 => '#c0392b',
            $code === 404 => '#e67e22',
            $code === 403 => '#8e44ad',
            default       => '#2c3e50',
        };

        echo <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>{$code} — {$message}</title>
            <style>
                body { font-family: sans-serif; display: flex; align-items: center;
                       justify-content: center; height: 100vh; margin: 0; background: #f5f5f5; }
                .box { text-align: center; }
                .code { font-size: 5rem; font-weight: 700; color: {$bg}; }
                p { color: #666; margin-top: 0.5rem; font-size: 1.1rem; }
                a { color: {$bg}; text-decoration: none; margin-top: 1.5rem; display: inline-block; }
            </style>
        </head>
        <body>
            <div class="box">
                <div class="code">{$code}</div>
                <p>{$message}</p>
                <a href="/">← Go home</a>
            </div>
        </body>
        </html>
        HTML;
    }

    // ── Dev exception output ──────────────────────────────────

    private static function renderDev(\Throwable $e): void
    {
        $type    = get_class($e);
        $message = htmlspecialchars($e->getMessage());
        $file    = htmlspecialchars($e->getFile());
        $line    = $e->getLine();
        $trace   = htmlspecialchars($e->getTraceAsString());
        $code    = self::getCodeSnippet($e->getFile(), $e->getLine());

        echo <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Error — FusionPHP</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: monospace; background: #0f0f0f; color: #e0e0e0; padding: 2rem; }
                .badge { display: inline-block; background: #c0392b; color: #fff; padding: 2px 10px; border-radius: 4px; font-size: 12px; margin-bottom: 1rem; }
                h1 { font-size: 1.4rem; color: #ff6b6b; margin-bottom: 0.5rem; }
                .meta { color: #888; font-size: 13px; margin-bottom: 1.5rem; }
                .meta span { color: #aaa; }
                .section { margin-bottom: 1.5rem; }
                .section h2 { font-size: 12px; text-transform: uppercase; color: #555; letter-spacing: 1px; margin-bottom: 0.5rem; }
                .code { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 6px; padding: 1rem; overflow-x: auto; }
                .code table { border-collapse: collapse; width: 100%; }
                .code td { padding: 2px 12px; font-size: 13px; line-height: 1.6; white-space: pre; }
                .code td:first-child { color: #555; text-align: right; user-select: none; border-right: 1px solid #2a2a2a; padding-right: 16px; min-width: 48px; }
                .highlight td { background: #2a1a1a; }
                .highlight td:last-child { color: #ff6b6b; }
                .trace { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 6px; padding: 1rem; font-size: 12px; line-height: 1.8; color: #aaa; overflow-x: auto; white-space: pre; }
            </style>
        </head>
        <body>
            <div class="badge">EXCEPTION</div>
            <h1>{$message}</h1>
            <div class="meta">
                <span>{$type}</span> &nbsp;·&nbsp; {$file} &nbsp;·&nbsp; line {$line}
            </div>
            <div class="section">
                <h2>Source</h2>
                <div class="code">{$code}</div>
            </div>
            <div class="section">
                <h2>Stack Trace</h2>
                <div class="trace">{$trace}</div>
            </div>
        </body>
        </html>
        HTML;
    }

    // ── Helpers ───────────────────────────────────────────────

    // Find the frame that actually called abort() — skip ErrorHandler itself
    private static function resolveCaller(array $trace): array
    {
        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';
            if ($file && !str_contains($file, 'ErrorHandler.php')) {
                return $frame;
            }
        }
        return [];
    }

    private static function formatTrace(array $trace): string
    {
        $output = '';
        foreach ($trace as $i => $frame) {
            $file  = htmlspecialchars($frame['file'] ?? 'internal');
            $line  = $frame['line'] ?? '';
            $fn    = htmlspecialchars(($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''));
            $output .= "<div class='frame'>"
                . "<span class='frame-fn'>#{$i} {$fn}()</span> "
                . "<span class='frame-file'>{$file}</span>"
                . ($line ? ":<span class='frame-line'>{$line}</span>" : '')
                . "</div>";
        }
        return $output;
    }

    private static function getCodeSnippet(string $file, int $errorLine, int $padding = 7): string
    {
        if (!file_exists($file)) return '';

        $lines  = file($file);
        $start  = max(0, $errorLine - $padding - 1);
        $end    = min(count($lines), $errorLine + $padding);
        $output = '<table>';

        for ($i = $start; $i < $end; $i++) {
            $lineNum   = $i + 1;
            $content   = htmlspecialchars($lines[$i]);
            $highlight = $lineNum === $errorLine ? ' class="highlight"' : '';
            $output   .= "<tr{$highlight}><td>{$lineNum}</td><td>{$content}</td></tr>";
        }

        return $output . '</table>';
    }

    private static function log(\Throwable $e): void
    {
        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $entry = sprintf(
            "[%s] %s: %s in %s on line %d\nTrace:\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString(),
            str_repeat('-', 80)
        );

        @file_put_contents(self::$logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    private static function logHttp(int $code, string $message): void
    {
        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $entry = sprintf(
            "[%s] HTTP %d: %s — %s\n%s\n",
            date('Y-m-d H:i:s'),
            $code,
            $message,
            $_SERVER['REQUEST_URI'] ?? '',
            str_repeat('-', 80)
        );

        @file_put_contents(self::$logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}