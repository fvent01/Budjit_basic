<?php
// core/router/Router.php

class Router
{
    private array $routes = [];

    // ── Route registration ────────────────────────────────────

    public function get(string $path, string $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, string $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function any(string $path, string $handler): void
    {
        $this->routes['GET'][$path]  = $handler;
        $this->routes['POST'][$path] = $handler;
    }

    // ── Dispatch ──────────────────────────────────────────────

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = $this->normalizeUri();

        // Try exact match first
        if (isset($this->routes[$method][$uri])) {
            $this->call($this->routes[$method][$uri], []);
            return;
        }

        // Try parameterized routes  e.g. /budgets/{id}
        foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
            $regex  = preg_replace('/\{([a-z_]+)\}/', '([^/]+)', $pattern);
            $regex  = '#^' . $regex . '$#';
            if (preg_match($regex, $uri, $matches)) {
                array_shift($matches);
                $this->call($handler, $matches);
                return;
            }
        }

        // 404
        http_response_code(404);
        $this->call('ErrorController@notFound', []);
    }

    private function normalizeUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        // Strip the public/ sub-path if present (XAMPP sub-directory installs)
        $base = parse_url(BASE_URL, PHP_URL_PATH);
        if ($base && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        return '/' . trim($uri, '/');
    }

    private function call(string $handler, array $params): void
    {
        [$class, $method] = explode('@', $handler);

        $file = APP_PATH . '/controllers/' . $class . '.php';
        if (!file_exists($file)) {
            http_response_code(500);
            die("Controller file not found: {$class}.php");
        }

        require_once $file;

        if (!class_exists($class)) {
            http_response_code(500);
            die("Controller class not found: {$class}");
        }

        $controller = new $class();

        if (!method_exists($controller, $method)) {
            http_response_code(500);
            die("Method not found: {$class}@{$method}");
        }

        call_user_func_array([$controller, $method], $params);
    }
}
