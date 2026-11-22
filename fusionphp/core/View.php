<?php

namespace Fusion\Core;

class View
{
    private string $layout = 'main';
    private array $sections = [];
    private static ?View $current = null;

    public function __construct(
        private string $template,
        private array $data = []
    ) {
    }

    public function setLayout(string $layout): void
    {
        $this->layout = $layout;
    }

    public function section(string $name): void
    {
        $this->sections[$name] = null;
        ob_start();
    }

    public function end(): void
    {
        $last = array_key_last($this->sections);
        $this->sections[$last] = ob_get_clean();
    }

    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function partial(string $component, array $data = []): void
    {
        $file = BASE_PATH . "/app/components/{$component}.php";

        if (!file_exists($file)) {
            throw new \RuntimeException("Component not found: {$component}");
        }

        $view = $this;
        $local = array_merge($this->data, $data);
        extract($local, EXTR_SKIP);
        require $file;
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    }

    // ── Current instance tracker ──────────────────────────────

    public static function getCurrent(): self
    {
        if (self::$current === null) {
            throw new \RuntimeException('No view is currently rendering');
        }
        return self::$current;
    }

    // ── Render ────────────────────────────────────────────────

    public function render(): void
    {
        $previous = self::$current;
        self::$current = $this;
        try {
            $this->renderLayout($this->renderTemplate());
        } finally {
            self::$current = $previous;
        }
    }

    private function renderTemplate(): string
    {
        if (!file_exists($this->template)) {
            throw new \RuntimeException("View not found: {$this->template}");
        }

        $local = $this->data;
        extract($local, EXTR_SKIP);

        ob_start();
        try {
            require $this->template;
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    private function renderLayout(string $content): void
    {
        $layoutFile = BASE_PATH . "/app/layouts/{$this->layout}.php";

        if (!file_exists($layoutFile)) {
            throw new \RuntimeException("Layout not found: {$this->layout}");
        }

        $local = $this->data;
        extract($local, EXTR_SKIP);

        ob_start();
        try {
            require $layoutFile;
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        echo Debugger::decorate($output);
    }
}