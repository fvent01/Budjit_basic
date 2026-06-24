<?php
// core/plugins/PluginLoader.php
// Discovers, loads, and manages Budjit plugins.
//
// Priority order for plugin metadata:
//   1. plugin.json in the plugin folder  (ground truth for 3rd-party plugins)
//   2. config/plugins.php                (built-in overrides / defaults)
//   3. plugins DB table                  (runtime enable/disable state)

class PluginLoader
{
    private static array $hooks    = [];
    private static array $loaded   = [];
    private static array $manifest = [];

    // ── Boot: discover + load enabled plugins ─────────────────

    /**
     * Purpose : Scan the plugins directory, merge metadata from plugin.json,
     *           config/plugins.php, and the DB, then load all enabled plugins.
     * Inputs  : $router — the application Router instance
     * Outputs : none (side-effects: routes registered, hooks populated)
     */
    public static function boot(Router $router): void
    {
        // Step 1 — Auto-detect plugins (plugin.json or plugin.php present)
        $manifest = self::discoverPlugins();

        // Step 2 — Merge config/plugins.php (can override built-in name/desc/icon/default state)
        $configFile = ROOT_PATH . '/config/plugins.php';
        if (file_exists($configFile)) {
            $configDefaults = require $configFile;
            foreach ($configDefaults as $slug => $meta) {
                if (!isset($manifest[$slug])) {
                    // Listed in config but folder not found — ghost entry (disabled)
                    $manifest[$slug] = array_merge(['enabled' => false], $meta);
                } else {
                    // Config values augment/override discovered values.
                    // 'enabled' in config is the install default; DB takes over in Step 3.
                    $manifest[$slug] = array_merge($manifest[$slug], $meta);
                }
            }
        }

        // Step 3 — DB runtime state (enables/disables individual plugins)
        try {
            $db   = Database::getInstance();
            $rows = $db->query("SELECT slug, is_enabled FROM plugins")->fetchAll();
            foreach ($rows as $row) {
                if (isset($manifest[$row['slug']])) {
                    $manifest[$row['slug']]['enabled'] = (bool) $row['is_enabled'];
                }
            }
        } catch (Exception $e) {
            // DB not ready yet (fresh install) — use discovery/config defaults
        }

        self::$manifest = $manifest;

        // Step 4 — Load enabled plugins (registers their routes + hooks)
        foreach (self::$manifest as $slug => $meta) {
            if (!empty($meta['enabled'])) {
                self::loadPlugin($slug, $router);
            }
        }
    }

    /**
     * Purpose : Scan PLUGIN_PATH for subdirectories that contain plugin.json
     *           or plugin.php and build an initial manifest.
     * Outputs : array<slug => meta>
     */
    private static function discoverPlugins(): array
    {
        $discovered = [];

        // Prefer plugin.json (richer metadata; required for 3rd-party plugins)
        foreach (glob(PLUGIN_PATH . '/*/plugin.json') as $jsonFile) {
            $slug = basename(dirname($jsonFile));
            $discovered[$slug] = self::parsePluginJson($jsonFile, $slug);
        }

        // Also detect legacy/minimal plugins that only ship plugin.php
        foreach (glob(PLUGIN_PATH . '/*/plugin.php') as $phpFile) {
            $slug = basename(dirname($phpFile));
            if (!isset($discovered[$slug])) {
                $discovered[$slug] = [
                    'name'          => $slug,
                    'version'       => '1.0.0',
                    'description'   => '',
                    'icon'          => 'ti-puzzle',
                    'color'         => '#6b7280',
                    'enabled'       => true,
                    'auto_detected' => true,
                ];
            }
        }

        return $discovered;
    }

    /**
     * Purpose : Parse a plugin.json file into a normalised meta array.
     * Inputs  : $path — absolute path to plugin.json; $slug — fallback id
     * Outputs : array of plugin metadata
     */
    public static function parsePluginJson(string $path, string $slug): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false) { return self::emptyMeta($slug); }
        $data = json_decode($raw, true);
        if (!is_array($data)) { return self::emptyMeta($slug); }
        return [
            'name'               => $data['name']               ?? $slug,
            'description'        => $data['description']        ?? '',
            'version'            => $data['version']            ?? '1.0.0',
            'author'             => $data['author']             ?? '',
            'author_email'       => $data['author_email']       ?? '',
            'homepage'           => $data['homepage']           ?? '',
            'min_budjit_version' => $data['min_budjit_version'] ?? '1.0.0',
            'icon'               => $data['icon']               ?? 'ti-puzzle',
            'color'              => $data['color']              ?? '#6b7280',
            'url'                => $data['url']                ?? $slug,
            'has_migrations'     => !empty($data['has_migrations']),
            'hooks'              => $data['hooks']              ?? [],
            'enabled'            => true,
        ];
    }

    private static function emptyMeta(string $slug): array
    {
        return ['name' => $slug, 'description' => '', 'version' => '1.0.0',
                'icon' => 'ti-puzzle', 'color' => '#6b7280', 'enabled' => true];
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
        if (empty(self::$hooks[$hook])) { return $payload; }
        foreach (self::$hooks[$hook] as $cb) {
            $result = $cb($payload);
            if ($result !== null) { $payload = $result; }
        }
        return $payload;
    }

    public static function collect(string $hook, mixed $context = null): array
    {
        $results = [];
        foreach (self::$hooks[$hook] ?? [] as $cb) {
            $out = $cb($context);
            if ($out !== null) { $results[] = $out; }
        }
        return $results;
    }

    public static function hasHook(string $hook): bool
    {
        return !empty(self::$hooks[$hook]);
    }

    // ── Plugin management ─────────────────────────────────────

    public static function getAll(): array   { return self::$manifest; }

    public static function isEnabled(string $slug): bool
    {
        return !empty(self::$manifest[$slug]['enabled']);
    }

    public static function enable(string $slug): void  { self::syncToDb($slug, true); }
    public static function disable(string $slug): void { self::syncToDb($slug, false); }

    private static function syncToDb(string $slug, bool $enabled): void
    {
        $db = Database::getInstance();
        $db->prepare(
            "INSERT INTO plugins (slug, name, version, author, homepage, is_enabled, is_third_party)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               name = VALUES(name), version = VALUES(version),
               author = VALUES(author), homepage = VALUES(homepage),
               is_enabled = VALUES(is_enabled)"
        )->execute([
            $slug,
            self::$manifest[$slug]['name']         ?? $slug,
            self::$manifest[$slug]['version']       ?? '1.0.0',
            self::$manifest[$slug]['author']        ?? '',
            self::$manifest[$slug]['homepage']      ?? '',
            (int) $enabled,
            (int) !empty(self::$manifest[$slug]['is_third_party']),
        ]);
        self::$manifest[$slug]['enabled'] = $enabled;
    }
}
