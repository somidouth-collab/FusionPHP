<?php

namespace Fusion\Core;

abstract class Controller
{
    protected Request $request;
    private array $sharedData = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    // Override to set base URL for this controller
    // HomeController   → ''
    // PluginController → 'plugin'
    // AdminController  → 'admin'
    protected function base(): string
    {
        return '';
    }

    protected function views(): array
    {
        return [];
    }

    /** Return API aliases mapped to HTTP methods and public handlers. */
    protected function api(): array
    {
        return [];
    }

    public function boot(): void
    {
    }

    // Router calls this to get full alias → config map
    public function getRoutes(): array
    {
        $routes = [];
        $base = trim($this->base(), '/');

        $mode = config('app.mode', 'both');
        $views = $mode === 'api' ? [] : $this->views();
        foreach ($views as $template => $config) {
            foreach ($config['aliases'] as $alias) {

                // Build full URL from base + alias
                $fullAlias = $this->buildAlias($base, $alias);

                if (Request::isApiPath('/' . ltrim($fullAlias, '/'))) {
                    throw new \LogicException("Web alias uses the reserved API prefix: {$fullAlias}");
                }
                if (isset($routes[$fullAlias])) {
                    throw new \LogicException("Duplicate route alias: {$fullAlias}");
                }
                $routes[$fullAlias] = [
                    'api' => false,
                    'template' => $template,
                    'handlers' => $config['handlers'] ?? [],
                    'methods' => $config['methods'] ?? ['GET'],
                    'data' => $config['data'] ?? [],
                ];
            }
        }

        foreach ($mode === 'web' ? [] : $this->api() as $alias => $methods) {
            $fullAlias = $this->buildAlias(config('api.prefix', '/api') . '/' . $base, (string) $alias);
            if (isset($routes[$fullAlias]) || $methods === []) {
                throw new \LogicException("Invalid or duplicate API route: {$fullAlias}");
            }
            $handlers = array_change_key_case($methods, CASE_UPPER);
            $routes[$fullAlias] = [
                'api' => true,
                'template' => null,
                'methods' => array_keys($handlers),
                'handlers' => $handlers,
                'data' => [],
            ];
        }

        return $routes;
    }

    private function buildAlias(string $base, string $alias): string
    {
        $path = trim(Request::normalizePath($base . '/' . $alias), '/');
        return $path === '' ? '/' : $path;
    }

    public function share(array $data): void
    {
        $this->sharedData = array_merge($this->sharedData, $data);
    }

    public function view(string $template, array $data = []): void
    {
        $parts = explode('\\', static::class);
        $module = strtolower($parts[1]);
        if (!preg_match('/\A[a-zA-Z0-9_-]+\z/', $template)) {
            throw new \InvalidArgumentException('Invalid page template name');
        }
        $folder = BASE_PATH . "/app/{$module}";
        $merged = array_merge($this->sharedData, $data);

        if (is_file("{$folder}/{$template}.fpf")) {
            echo Debugger::decorate(Template::render("{$folder}/{$template}.fpf", $merged));
            return;
        }

        (new View("{$folder}/views/{$template}.php", $merged))->render();
    }

    // ─────────────────────────────────────────────────────────
    // REDIRECT METHODS
    // ─────────────────────────────────────────────────────────

    /**
     * Redirect to a URL
     *
     * @param string $url The URL to redirect to
     * @param int $statusCode HTTP status code (302, 301, etc.)
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        header("Location: {$url}", true, $statusCode);
        throw new ResponseComplete();
    }

    /**
     * Redirect back to the previous page
     */
    protected function back(): void
    {
        $referer = $this->previousPath();
        $this->redirect($referer);
    }

    private function previousPath(): string
    {
        $referer = $this->request->referer();
        $path = parse_url($referer, PHP_URL_PATH);
        $query = parse_url($referer, PHP_URL_QUERY);
        if (!is_string($path) || preg_match('~[\x00-\x20\x7f]|\\\\~', $path)) {
            return $this->request->url();
        }
        return '/' . ltrim($path, '/') . (is_string($query) ? '?' . $query : '');
    }

    /** Redirect with a flash message for the next request. */
    protected function redirectWith(string $url, string $message, string $type = 'success'): void
    {
        Flash::set($type, $message);
        $this->redirect($url);
    }

    /**
     * Redirect back with an error message
     */
    protected function backWithError(string $message): void
    {
        $this->redirectWith($this->previousPath(), $message, 'error');
    }

    /**
     * Redirect back with a success message
     */
    protected function backWithSuccess(string $message): void
    {
        $this->redirectWith($this->previousPath(), $message, 'success');
    }

    /**
     * Redirect to a route within the same module
     *
     * @param string $alias The route alias (e.g., 'login', 'profile')
     * @param array $params Optional query parameters
     */
    protected function toRoute(string $alias, array $params = []): void
    {
        $base = trim($this->base(), '/');
        $url = $this->request->url($this->buildAlias($base, $alias));

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $this->redirect($url);
    }

    // ─────────────────────────────────────────────────────────
    // JSON RESPONSE METHODS
    // ─────────────────────────────────────────────────────────

    /**
     * Return JSON response
     *
     * @param array $data
     * @param int $statusCode
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        $body = in_array($statusCode, [204, 205, 304], true) ? '' : json_encode($data, JSON_THROW_ON_ERROR);
        http_response_code($statusCode);
        if ($body !== '') {
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            echo $body;
        }
        throw new ResponseComplete();
    }

    /**
     * Return JSON error response
     */
    protected function jsonError(string $message, int $statusCode = 400, array $additional = []): void
    {
        $this->json(['error' => $message, 'status' => $statusCode] + $additional, $statusCode);
    }

    /**
     * Return JSON success response
     */
    protected function jsonSuccess(string $message, array $data = [], int $statusCode = 200): void
    {
        $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    // ─────────────────────────────────────────────────────────
    // INPUT HELPER METHODS
    // ─────────────────────────────────────────────────────────

    /**
     * Get input from POST or GET
     */
    protected function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    /**
     * Get all inputs (POST + GET)
     */
    protected function allInputs(): array
    {
        return array_merge($_GET, $_POST);
    }

    /**
     * Get only specific input fields
     */
    protected function only(array $keys): array
    {
        return array_intersect_key($this->allInputs(), array_flip($keys));
    }

    /**
     * Get all inputs except specific fields
     */
    protected function except(array $keys): array
    {
        return array_diff_key($this->allInputs(), array_flip($keys));
    }

    /**
     * Check if input exists
     */
    protected function has(string $key): bool
    {
        return isset($_POST[$key]) || isset($_GET[$key]);
    }

    // ─────────────────────────────────────────────────────────
    // VALIDATION HELPER
    // ─────────────────────────────────────────────────────────

    /**
     * Simple validation
     *
     * @param array $rules ['field' => 'required|min:3|email', ...]
     * @return array The validated data
     */
    protected function validate(array $rules): array
    {
        $errors = [];
        $data = $this->request->isApi() && $this->request->isJson() ? $this->request->json() : $this->allInputs();

        foreach ($rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;
            if ($value !== null && !is_scalar($value)) {
                $errors[$field][] = ucfirst($field) . ' must be a single value.';
                continue;
            }
            $rules = explode('|', $ruleSet);

            if (in_array('string', $rules, true) && $value !== null && !is_string($value)) {
                $errors[$field][] = ucfirst($field) . ' must be text.';
                continue;
            }
            foreach ($rules as $rule) {
                if ($rule === 'required' && empty($value) && $value !== '0') {
                    $errors[$field][] = ucfirst($field) . ' is required.';
                }

                if (str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if (strlen((string) $value) < $min) {
                        $errors[$field][] = ucfirst($field) . ' must be at least ' . $min . ' characters.';
                    }
                }

                if (str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if (strlen((string) $value) > $max) {
                        $errors[$field][] = ucfirst($field) . ' must not exceed ' . $max . ' characters.';
                    }
                }

                if ($rule === 'email' && $value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = 'Valid email is required.';
                }

                if ($rule === 'numeric' && $value !== null && $value !== '' && !is_numeric($value)) {
                    $errors[$field][] = ucfirst($field) . ' must be a number.';
                }
            }
        }

        if (!empty($errors)) {
            if ($this->request->isApi()) {
                $this->jsonError('Validation failed', 422, ['errors' => $errors]);
            }
            $_SESSION['validation_errors'] = $errors;
            $_SESSION['old_input'] = $data;
            $this->back();
        }

        return $data;
    }

    // ─────────────────────────────────────────────────────────
    // SESSION HELPERS
    // ─────────────────────────────────────────────────────────

    /**
     * Get old input value (after validation failure)
     */
    protected function old(string $key, mixed $default = null): mixed
    {
        return $_SESSION['old_input'][$key] ?? $default;
    }

    /**
     * Get validation error for a field
     */
    protected function error(string $field): ?string
    {
        return $_SESSION['validation_errors'][$field][0] ?? null;
    }

    /**
     * Check if there are validation errors
     */
    protected function hasErrors(): bool
    {
        return !empty($_SESSION['validation_errors']);
    }

    /**
     * Clear validation errors and old input
     */
    protected function clearValidation(): void
    {
        unset($_SESSION['validation_errors']);
        unset($_SESSION['old_input']);
    }

    // ─────────────────────────────────────────────────────────
    // STATUS/ABORT METHODS
    // ─────────────────────────────────────────────────────────

    /**
     * Abort with HTTP status code
     */
    protected function abort(int $code = 404, string $message = ''): void
    {
        throw new HttpException($code, $message);
    }
}
