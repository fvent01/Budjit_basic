<?php
// core/plugins/PluginLoader.php

class PluginLoader
{
    private static array $hooks    = [];
    private static array $loaded   = [];
    private static array $manifest = [];

    // ── Boot: load enabled plugins ────────────────────────────

    public static function boot(Router $router): void
    {
        self::$manifest = require ROOT_PATH . '/config/plugins.php';

        // Merge DB state over config defaults
        try {
            $db   = Database::getInstance();
            $rows = $db->query("SELECT slug, is_enabled FROM plugins")->fetchAll();
            foreach ($rows as $row) {
                if (isset(self::$manifest[$row['slug']])) {
                    self::$manifest[$row['slug']]['enabled'] = (bool)$row['is_enabled'];
                }
            }
        } catch (Exception $e) {
            // DB not ready yet — use config defaults
        }

        // Auto-detect plugins present in /plugins that aren't in manifest
        foreach (glob(PLUGIN_PATH . '/*/plugin.php') as $file) {
            $slug = basename(dirname($file));
            if (!isset(self::$manifest[$slug])) {
                self::$manifest[$slug] = ['name' => $slug, 'enabled' => true, 'auto_detected' => true];
            }
        }

        // Load enabled plugins
        foreach (self::$manifest as $slug => $meta) {
            if (!empty($meta['enabled'])) {
                self::loadPlugin($slug, $router);
            }
        }
    }

    private static function loadPlugin(string $slug, Router $router): void
    {
        $file = PLUGIN_PATH . '/' . $slug . '/plugin.php';
        if (file_exists($file) && !isset(self::$loaded[$slug])) {
            require_once $file;
            self::$loaded[$slug] = true;
        }
    }

    // ── Hook system ───────────────────────────────────────────

    public static function register(string $hook, callable $callback): void
    {
        self::$hooks[$hook][] = $callback;
    }

    public static function fire(string $hook, mixed $payload = null): mixed
    {
        if (empty(self::$hooks[$hook])) return $payload;
        foreach (self::$hooks[$hook] as $cb) {
            $result = $cb($payload);
            if ($result !== null) $payload = $result;
        }
        return $payload;
    }

    /** Collect all outputs from a hook (e.g. dashboard widgets) */
    public static function collect(string $hook, mixed $context = null): array
    {
        $results = [];
        foreach (self::$hooks[$hook] ?? [] as $cb) {
            $out = $cb($context);
            if ($out !== null) $results[] = $out;
        }
        return $results;
    }

    public static function hasHook(string $hook): bool
    {
        return !empty(self::$hooks[$hook]);
    }

    // ── Plugin management ─────────────────────────────────────

    public static function getAll(): array { return self::$manifest; }

    public static function isEnabled(string $slug): bool
    {
        return !empty(self::$manifest[$slug]['enabled']);
    }

    public static function enable(string $slug): void
    {
        self::syncToDb($slug, true);
    }

    public static function disable(string $slug): void
    {
        self::syncToDb($slug, false);
    }

    private static function syncToDb(string $slug, bool $enabled): void
    {
        $db = Database::getInstance();
        $db->prepare(
            "INSERT INTO plugins (slug, name, version, is_enabled)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)"
        )->execute([
            $slug,
            self::$manifest[$slug]['name']    ?? $slug,
            self::$manifest[$slug]['version'] ?? '1.0.0',
            (int)$enabled,
        ]);
        self::$manifest[$slug]['enabled'] = $enabled;
    }
}
