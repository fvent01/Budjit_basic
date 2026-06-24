<?php
// core/router/Controller.php

abstract class Controller
{
    private static ?string $appName = null;

    // ── Helpers ──────────────────────────────────────────────
    
    /**
     * Get app name from settings (or fallback to constant).
     */
    public static function appName(): string
    {
        if (self::$appName === null) {
            // Try to fetch from settings table
            if (class_exists('SettingsModel')) {
                try {
                    $settings = new SettingsModel();
                    self::$appName = $settings->get('app_name', APP_NAME);
                } catch (Exception $e) {
                    self::$appName = APP_NAME;
                }
            } else {
                self::$appName = APP_NAME;
            }
        }
        return self::$appName;
    }

    // ── View rendering ────────────────────────────────────────

    /**
     * Render a view inside the main layout.
     *
     * @param string $view     Dot-notation path: 'dashboard.index' → views/dashboard/index.php
     * @param array  $data     Variables made available inside the view
     * @param string $layout   Layout file name (without .php)
     */
    protected function view(string $view, array $data = [], string $layout = 'main'): void
    {
        // Make data keys available as variables in the view
        extract($data, EXTR_SKIP);

        $viewFile   = APP_PATH . '/views/' . str_replace('.', '/', $view) . '.php';
        $layoutFile = APP_PATH . '/views/layouts/' . $layout . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(500);
            die("View not found: {$viewFile}");
        }

        // Buffer the inner view
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Render into layout
        if (file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    /**
     * Render a bare view (no layout) — useful for partials and AJAX.
     */
    protected function partial(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $viewFile = APP_PATH . '/views/' . str_replace('.', '/', $view) . '.php';
        if (file_exists($viewFile)) require $viewFile;
    }

    // ── Redirects ─────────────────────────────────────────────

    protected function redirect(string $path): void
    {
        header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
        exit;
    }

    protected function back(): void
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? BASE_URL;
        header('Location: ' . $ref);
        exit;
    }

    // ── JSON responses (for AJAX / API endpoints) ─────────────

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ── Input helpers ─────────────────────────────────────────

    protected function input(string $key, mixed $default = null): mixed
    {
        $val = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($val) ? trim($val) : $val;
    }

    protected function sanitize(string $val): string
    {
        return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
    }

    // ── Flash messages ────────────────────────────────────────

    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    protected function flashSuccess(string $message): void { $this->flash('success', $message); }
    protected function flashError(string $message): void   { $this->flash('error',   $message); }
    protected function flashInfo(string $message): void    { $this->flash('info',    $message); }

    public static function getFlash(): array
    {
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $messages;
    }
}
