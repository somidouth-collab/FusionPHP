<?php

namespace Fusion\Core;

abstract class Middleware
{
    protected Request $request;
    protected array $protectedRoutes = [];
    protected array $guestRoutes = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->protectedRoutes = $this->protected();
        $this->guestRoutes = $this->guest();
    }

    /**
     * Define protected routes (require authentication)
     * Override in child class
     */
    protected function protected(): array
    {
        return [];
    }

    /**
     * Define guest routes (accessible only when not logged in)
     * Override in child class
     */
    protected function guest(): array
    {
        return [];
    }

    /**
     * Runs before controller — return false to block request
     * Override in child class for custom logic
     */
    public function before(): bool
    {
        return true;
    }

    /**
     * Runs after controller, before response is sent
     * Override in child class for custom logic
     */
    public function after(): void
    {
    }

    /**
     * Handle route protection and guest access
     * This is the main method that child classes should call
     */
    protected function handleRouteAccess(): bool
    {
        $currentPath = $this->request->uri();

        // Check protected routes
        if ($this->isProtectedRoute($currentPath)) {
            if (!$this->isAuthenticated()) {
                $this->handleUnauthorized();
                return false;
            }

            // Optional: Check additional permissions
            if (!$this->checkPermissions($currentPath)) {
                $this->handleForbidden();
                return false;
            }
        }

        // Check guest routes
        if ($this->isGuestRoute($currentPath)) {
            if ($this->isAuthenticated()) {
                $this->handleAlreadyLoggedIn();
                return false;
            }
        }

        return true;
    }

    /**
     * Check if current path is a protected route
     */
    protected function isProtectedRoute(string $path): bool
    {
        return $this->matchesPattern($path, $this->protectedRoutes);
    }

    /**
     * Check if current path is a guest route
     */
    protected function isGuestRoute(string $path): bool
    {
        return $this->matchesPattern($path, $this->guestRoutes);
    }

    /**
     * Check if user is authenticated
     * Override this in child class if needed
     */
    protected function isAuthenticated(): bool
    {
        return Session::isLoggedIn();
    }

    /**
     * Check additional permissions for protected routes
     * Override this in child class for role-based access
     */
    protected function checkPermissions(string $path): bool
    {
        return true;
    }

    /**
     * Handle unauthorized access (not logged in)
     * Override this in child class for custom behavior
     */
    protected function handleUnauthorized(): void
    {
        if ($this->request->isApi()) {
            throw new HttpException(401);
        }
        Flash::warning('Please log in to continue');
        Session::set('intended_url', $this->request->uri());
        $this->redirect($this->request->url('/auth/login'));
    }

    /**
     * Handle forbidden access (logged in but insufficient permissions)
     * Override this in child class for custom behavior
     */
    protected function handleForbidden(): void
    {
        if ($this->request->isApi()) {
            throw new HttpException(403);
        }
        Flash::error('You do not have permission to access this page');
        $this->redirect($this->request->url('/'));
    }

    /**
     * Handle already logged in access to guest routes
     * Override this in child class for custom behavior
     */
    protected function handleAlreadyLoggedIn(): void
    {
        if ($this->request->isApi()) {
            throw new HttpException(403);
        }
        Flash::info('You are already logged in');
        $this->redirect($this->request->url('/dashboard'));
    }

    /**
     * Check if path matches any pattern in the list
     * Supports wildcard (*) and exact matches
     */
    protected function matchesPattern(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->patternMatches($path, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a single pattern matches the path
     */
    protected function patternMatches(string $path, string $pattern): bool
    {
        $pattern = Request::normalizePath($pattern);
        if (str_ends_with($pattern, '/*')) {
            $base = substr($pattern, 0, -2);
            return $path === $base || str_starts_with($path, $base . '/');
        }

        $segments = explode('/', $pattern);
        foreach ($segments as &$segment) {
            $segment = preg_match('/^\{[a-zA-Z_]+\}$/D', $segment)
                ? '[^/]+'
                : preg_quote($segment, '#');
        }

        return preg_match('#^' . implode('/', $segments) . '$#D', $path) === 1;
    }

    /**
     * Redirect to a URL
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        header("Location: {$url}", true, $statusCode);
        throw new ResponseComplete();
    }

    /**
     * Get the current route alias (if available)
     */
    protected function getCurrentRouteAlias(): ?string
    {
        $path = $this->request->uri();
        return $path === '/' ? '/' : trim($path, '/');
    }
}