<?php

namespace Fusion\Core;

class Router
{
    private array $urlMap = [];
    private bool $dev;
    private string $cacheFile;
    private Request $request;

    public function __construct(bool $dev = true)
    {
        $this->dev = $dev;
        $this->request = Request::getInstance();
        ErrorHandler::setJson($this->request->isApi() || config('app.mode') === 'api');
        $this->cacheFile = BASE_PATH . '/storage/cache/urlmap.php';
        $this->loadUrlMap();
    }

    // ── URL Map ───────────────────────────────────────────────

    private function loadUrlMap(): void
    {
        $controllers = [];
        foreach (glob(BASE_PATH . '/app/*/', GLOB_ONLYDIR) ?: [] as $directory) {
            $module = ucfirst(basename($directory));
            $file = $directory . $module . 'Controller.php';
            if (is_file($file)) {
                $controllers["App\\{$module}\\{$module}Controller"] = $file;
            }
        }

        if ($this->dev) {
            $this->buildUrlMap($controllers);
            return;
        }

        $sources = [__FILE__, __DIR__ . '/Controller.php', ...array_values($controllers)];
        $fingerprint = ['mode' => config('app.mode'), 'prefix' => config('api.prefix')];
        foreach ($sources as $file) {
            $fingerprint[$file] = hash_file('sha256', $file);
        }
        $version = hash('sha256', serialize($fingerprint));
        $cached = PhpCache::read($this->cacheFile);
        if (($cached['version'] ?? null) === $version && is_array($cached['routes'] ?? null)) {
            $this->urlMap = $cached['routes'];
            return;
        }

        $this->buildUrlMap($controllers);
        PhpCache::write($this->cacheFile, ['version' => $version, 'routes' => $this->urlMap]);
    }

    private function buildUrlMap(array $controllers): void
    {
        foreach ($controllers as $controllerClass => $file) {
            if (!is_subclass_of($controllerClass, Controller::class)) {
                throw new \LogicException("Expected a page controller in {$file}");
            }
            foreach ((new $controllerClass($this->request))->getRoutes() as $alias => $config) {
                if (isset($this->urlMap[$alias])) {
                    throw new \LogicException("Duplicate route alias: {$alias}");
                }
                $this->urlMap[$alias] = ['controller' => $controllerClass] + $config;
            }
        }
    }

    public static function clearCache(): void
    {
        $file = BASE_PATH . '/storage/cache/urlmap.php';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    // ── Dispatch ──────────────────────────────────────────────

    public function dispatch(): void
    {
        // Debug page — intercept before normal routing
        if (config('app.mode') !== 'api' && Debugger::isEnabled() && $this->resolveAlias() === '_debug') {
            Debugger::renderPage();
            return;
        }

        $alias = $this->resolveAlias();
        $route = $this->urlMap[$alias] ?? null;

        if (!$route) {
            ErrorHandler::abort(404);
            return;
        }

        $module = $this->extractModule($route['controller']);

        $allowed = array_map('strtoupper', $route['methods']);
        if (!in_array($this->request->method(), $allowed, true)) {
            header('Allow: ' . implode(', ', $allowed));
            ErrorHandler::abort(405);
            return;
        }

        $entered = [];
        $allowedByMiddleware = true;
        try {
            foreach ($this->buildMiddlewareStack($module) as $class) {
                $middleware = new $class($this->request);
                $entered[] = $middleware;
                if (!$middleware->before()) {
                    $allowedByMiddleware = false;
                    break;
                }
            }
            if ($allowedByMiddleware) {
                $this->runController($route);
            }
        } catch (ResponseComplete) {
            // A response helper finished the response early.
        } finally {
            foreach (array_reverse($entered) as $middleware) {
                $middleware->after();
            }
        }

        if (!$allowedByMiddleware) {
            ErrorHandler::abort(403);
        }
    }

    // ── URL Parsing ───────────────────────────────────────────

    private function resolveAlias(): string
    {
        $path = $this->request->uri();
        return $path === '/' ? '/' : trim($path, '/');
    }

    // ── Controller Dispatch ───────────────────────────────────

    private function runController(array $route): void
    {
        $method = $this->request->method();

        $controller = new $route['controller']($this->request);
        $controller->boot();

        // GET + contact → getContact()
        // POST + contact → postContact()
        $explicit = $route['handlers'][$method] ?? null;
        $handler = $explicit ?? strtolower($method) . ucfirst($route['template']);

        if (is_callable([$controller, $handler])) {
            $controller->$handler();
        } elseif ($explicit !== null || $route['api']) {
            throw new \LogicException("Route handler is unavailable: {$handler}");
        } else {
            $controller->view($route['template'], $route['data']);
        }

    }

    // ── Middleware ────────────────────────────────────────────

    private function buildMiddlewareStack(string $module): array
    {
        $stack = [];

        if (class_exists('App\\AppMiddleware')) {
            $stack[] = 'App\\AppMiddleware';
        }

        $moduleMiddleware = "App\\{$module}\\{$module}Middleware";
        $middlewareFile = BASE_PATH . '/app/' . strtolower($module) . "/{$module}Middleware.php";
        if (is_file($middlewareFile) && class_exists($moduleMiddleware)) {
            $stack[] = $moduleMiddleware;
        }

        return $stack;
    }

    // ── Helpers ───────────────────────────────────────────────

    private function extractModule(string $controllerClass): string
    {
        return explode('\\', $controllerClass)[1];
    }

    // ── Additional Router Features ────────────────────────────

    /**
     * Get current route information
     */
    public function getCurrentRoute(): ?array
    {
        $alias = $this->resolveAlias();
        return $this->urlMap[$alias] ?? null;
    }

    /**
     * Check if route exists
     */
    public function hasRoute(string $alias): bool
    {
        return isset($this->urlMap[trim(Request::normalizePath($alias), '/') ?: '/']);
    }

    /**
     * Generate URL for a named route
     */
    public function route(string $alias, array $params = []): ?string
    {
        if (!$this->hasRoute($alias)) {
            return null;
        }

        $url = $this->request->url($alias);

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->urlMap;
    }
}