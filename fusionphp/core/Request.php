<?php

namespace Fusion\Core;

class Request
{
    private static ?self $instance = null;
    private string $uri;
    private string $method;
    private ?array $jsonBody = null;
    private bool $direct = false;

    private function __construct()
    {
        $this->uri = $this->parseUri();
        $this->method = $this->resolveMethod();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── URL & Method ──────────────────────────────────────────

    public function uri(): string
    {
        return $this->uri;
    }

    public function isApi(): bool
    {
        return self::isApiPath($this->uri);
    }

    public static function isApiPath(string $path): bool
    {
        $prefix = config('api.prefix', '/api');
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    public function url(string $path = '/', bool $asset = false): string
    {
        $base = config('app.base_path', '');
        if (!$asset && $this->direct) {
            $base .= '/index.php';
        }
        return $base . self::normalizePath($path);
    }

    public function apiUrl(string $path = '/'): string
    {
        return $this->url(config('api.prefix', '/api') . '/' . ltrim($path, '/'));
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isPut(): bool
    {
        return $this->method === 'PUT';
    }

    public function isPatch(): bool
    {
        return $this->method === 'PATCH';
    }

    public function isDelete(): bool
    {
        return $this->method === 'DELETE';
    }

    public function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    // ── Input ─────────────────────────────────────────────────

    public function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        return isset($_POST[$key]) || isset($_GET[$key]);
    }

    public function hasAny(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->has($key)) {
                return true;
            }
        }
        return false;
    }

    // ── Files ─────────────────────────────────────────────────

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    public function allFiles(): array
    {
        return $_FILES;
    }

    // ── Client ────────────────────────────────────────────────

    public function ip(): string
    {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if ($ip = $_SERVER[$header] ?? null) {
                // Handle comma-separated list (X-Forwarded-For)
                if (str_contains($ip, ',')) {
                    $ips = array_map('trim', explode(',', $ip));
                    $ip = $ips[0];
                }

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public function referer(): string
    {
        return $_SERVER['HTTP_REFERER'] ?? '';
    }

    // ── JSON ──────────────────────────────────────────────────

    /**
     * Get JSON input data
     *
     * @param string|null $key The key to retrieve from JSON body
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->jsonBody === null) {
            $this->parseJsonBody();
        }

        if ($key === null) {
            return $this->jsonBody;
        }

        return $this->jsonBody[$key] ?? $default;
    }

    public function isJson(): bool
    {
        $type = strtolower(trim(explode(';', $this->header('Content-Type', ''), 2)[0]));
        return $type === 'application/json' || preg_match('~^application/[a-z0-9.!#$&^_+-]+\+json$~D', $type) === 1;
    }

    // ── Query Parameters ──────────────────────────────────────

    /**
     * Get query string parameter
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Get all query parameters
     */
    public function queryAll(): array
    {
        return $_GET;
    }

    // ── Server/Environment ────────────────────────────────────

    /**
     * Get server variable
     */
    public function server(string $key, mixed $default = null): mixed
    {
        return $_SERVER[$key] ?? $default;
    }

    /**
     * Get request header
     */
    public function header(string $key, mixed $default = null): mixed
    {
        $key = strtoupper(str_replace('-', '_', $key));
        if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
            return $_SERVER[$key] ?? $default;
        }
        return $_SERVER['HTTP_' . $key] ?? $default;
    }

    /**
     * Get all headers
     */
    public function headers(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[str_replace('_', '-', $key)] = $value;
            } elseif (str_starts_with($key, 'HTTP_')) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            }
        }
        return $headers;
    }

    // ── Session ───────────────────────────────────────────────

    /**
     * Get session value
     */
    public function session(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set session value
     */
    public function setSession(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    // ── Validation Helpers ────────────────────────────────────

    /**
     * Check if request expects JSON response
     */
    public function expectsJson(): bool
    {
        return $this->isApi() || $this->isAjax() || $this->isJson() || $this->wantsJson();
    }

    /**
     * Check if client wants JSON response
     */
    public function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json');
    }

    // ── Private Methods ───────────────────────────────────────

    private function parseUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Remove query string if present
        if (str_contains($uri, '?')) {
            $uri = strstr($uri, '?', true);
        }

        $path = self::normalizePath($uri);
        $base = config('app.base_path', '');
        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = self::normalizePath(substr($path, strlen($base)));
        }

        $entry = '/index.php';
        $this->direct = $path === $entry || str_starts_with($path, $entry . '/');
        return $this->direct ? self::normalizePath(substr($path, strlen($entry))) : $path;
    }

    public static function normalizePath(string $path): string
    {
        return '/' . trim(preg_replace('~/+~', '/', $path), '/');
    }

    private function resolveMethod(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($this->isApi()) {
            return $method;
        }

        // Handle method override via POST
        if ($method === 'POST' && is_string($_POST['_method'] ?? null)) {
            $override = strtoupper($_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'])) {
                return $override;
            }
        }

        // Handle method override via header
        if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $override = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'])) {
                return $override;
            }
        }

        return $method;
    }

    private function parseJsonBody(): void
    {
        if ($this->isJson() && in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $input = file_get_contents('php://input');
            try {
                $decoded = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                if ($this->isApi()) {
                    throw new HttpException(400, 'Malformed JSON body');
                }
                $decoded = null;
            }

            if ($this->isApi() && !is_array($decoded)) {
                throw new HttpException(400, 'The JSON body must be an object or array');
            }
            $this->jsonBody = is_array($decoded) ? $decoded : [];
        } else {
            $this->jsonBody = [];
        }
    }

    // ── Magic Methods ─────────────────────────────────────────

    /**
     * Get input value using property access (e.g., $request->name)
     */
    public function __get(string $key): mixed
    {
        return $this->input($key);
    }

    /**
     * Check if input exists using property access
     */
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * Prevent cloning of singleton
     */
    private function __clone(): void
    {
    }

    /**
     * Prevent unserializing of singleton
     */
    public function __wakeup(): void
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
