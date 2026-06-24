# Budjit Plugin Development Guide

This document describes how to build, package, and distribute plugins for Budjit v1.

---

## Overview

Plugins are self-contained PHP modules that extend Budjit with new pages, nav items, dashboard
widgets, and other behaviour. Each plugin lives in its own subdirectory under `plugins/` and is
activated by the admin from **Settings → Plugins**.

---

## Directory Structure

```
plugins/
  my-plugin/
    plugin.json       ← Required. Machine-readable manifest.
    plugin.php        ← Required. Bootstraps routes and hook registrations.
    views/            ← Optional. PHP view templates.
      index.php
    migrations/       ← Optional. Ordered .sql files run on install.
      001_create_table.sql
    assets/           ← Optional. CSS/JS/images served by your views.
```

---

## plugin.json

Every plugin must include a `plugin.json` at its root. This is the ground-truth manifest used
by the installer, the Plugin Manager, and the developer marketplace.

### Schema

```json
{
    "id":                   "my-plugin",
    "name":                 "My Plugin",
    "version":              "1.2.0",
    "description":          "A short description shown in the Plugin Manager.",
    "author":               "Your Name",
    "author_email":         "you@example.com",
    "homepage":             "https://example.com/my-plugin",
    "min_budjit_version":   "1.0.0",
    "icon":                 "ti-puzzle",
    "color":                "#7F77DD",
    "url":                  "my-plugin",
    "has_migrations":       true,
    "hooks":                ["nav_items", "dashboard_widgets"]
}
```

### Field Reference

| Field | Required | Description |
|---|---|---|
| `id` | ✅ | Unique slug. Lowercase letters, digits, hyphens only. |
| `name` | ✅ | Display name shown in the Plugin Manager. |
| `version` | ✅ | Semver string (e.g. `"1.0.0"`). |
| `description` | — | Short description (1–2 sentences). |
| `author` | — | Author or company name. |
| `author_email` | — | Contact email. |
| `homepage` | — | URL to plugin homepage or docs. |
| `min_budjit_version` | — | Minimum Budjit version required. |
| `icon` | — | [Tabler Icon](https://tabler.io/icons) class name (e.g. `"ti-chart-bar"`). |
| `color` | — | Hex colour for the plugin icon background. |
| `url` | — | URL slug (appended to `BASE_URL/`) used for the "Open" link. |
| `has_migrations` | — | Set `true` if your plugin includes `.sql` migration files. |
| `hooks` | — | Array of hook names your plugin registers (informational). |

---

## plugin.php

This file is included by `PluginLoader` when the plugin is enabled. It has access to the
`$router` variable and the full Budjit bootstrap. Use it to register routes and hooks.

### Example

```php
<?php
// plugins/my-plugin/plugin.php
defined('BUDJIT') or die;

// Register routes
$router->get( '/my-plugin',         'MyPluginController@index');
$router->post('/my-plugin/save',    'MyPluginController@save');

// Add a nav item
PluginLoader::register('nav_items', function(array $items): array {
    $items[] = [
        'label' => 'My Plugin',
        'url'   => BASE_URL . '/my-plugin',
        'icon'  => 'ti-puzzle',
        'match' => 'my-plugin',   // highlights nav item when URL contains this string
    ];
    return $items;
});

// Contribute a dashboard widget
PluginLoader::register('dashboard_widgets', function(): string {
    ob_start();
    require PLUGIN_PATH . '/my-plugin/views/widget.php';
    return ob_get_clean();
});
```

---

## Available Hooks

Hooks let your plugin extend existing Budjit pages without modifying core files.

### `nav_items`

Fired when building the sidebar navigation. Receive the current items array, add your item,
and return the modified array.

```php
PluginLoader::register('nav_items', function(array $items): array {
    $items[] = [
        'label' => 'My Feature',
        'url'   => BASE_URL . '/my-feature',
        'icon'  => 'ti-star',
        'match' => 'my-feature',
    ];
    return $items;
});
```

### `dashboard_widgets`

Contribute an HTML string to the dashboard widget grid. Return the rendered HTML.

```php
PluginLoader::register('dashboard_widgets', function(): string {
    ob_start();
    // render your widget here
    return ob_get_clean();
});
```

### `before_expense_save` / `after_expense_save`

Fired before/after an expense is persisted. Receive the expense data array.

```php
PluginLoader::register('after_expense_save', function(array $expense): void {
    // e.g. send a webhook
});
```

### Firing Your Own Hooks

You can define custom hooks in your plugin and let other plugins extend it:

```php
// In your controller or view:
PluginLoader::fire('my_plugin.after_sync', ['count' => $n]);

// Collect multiple contributions (e.g. extra table rows):
$extraRows = PluginLoader::collect('my_plugin.extra_rows', $context);
```

---

## Database Migrations

If your plugin needs its own tables, place numbered `.sql` files in `plugins/my-plugin/migrations/`.
Set `"has_migrations": true` in `plugin.json`.

Migrations are run **once** on install (or when triggered from `update.php`). Their filenames are
tracked in the `migrations` table so they are never re-executed.

### Naming Convention

```
001_create_my_table.sql
002_add_index.sql
003_seed_data.sql
```

Files are executed in alphabetical order. Use zero-padded numbers to control ordering.

### Example Migration

```sql
-- plugins/my-plugin/migrations/001_create_my_table.sql
CREATE TABLE IF NOT EXISTS my_plugin_data (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    value      VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mpd_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Safety Rules

- Always use `CREATE TABLE IF NOT EXISTS`
- Always use `ADD COLUMN IF NOT EXISTS` for `ALTER TABLE`
- Make migrations **idempotent** (safe to run twice without error)
- Never `DROP` production tables without a confirmed rollback path

---

## Controllers and Models

Place your PHP classes in the plugin folder. The Budjit autoloader will find them if named
correctly:

```
plugins/my-plugin/MyPluginController.php   → class MyPluginController extends Controller
plugins/my-plugin/MyPluginModel.php        → class MyPluginModel extends Model
```

> The autoloader searches `app/controllers/`, `app/models/`, and `core/plugins/` by default.
> For plugin classes, add the plugin directory to the autoloader in `plugin.php`:

```php
// At the top of plugin.php:
spl_autoload_register(function(string $class): void {
    $file = PLUGIN_PATH . '/my-plugin/' . $class . '.php';
    if (file_exists($file)) require_once $file;
});
```

---

## Views

Views are plain PHP files. Render them with `ob_start()` in controllers, or use the base
`Controller::view()` method if your controller extends `Controller`.

```php
// In your controller:
$this->view('my-plugin.index', compact('data'));
// Looks for: app/views/my-plugin/index.php
// OR load directly in plugin.php for simple cases:
require PLUGIN_PATH . '/my-plugin/views/index.php';
```

---

## Packaging for Distribution

Package your plugin as a `.zip` file with the plugin folder at the root:

```
my-plugin.zip
  my-plugin/
    plugin.json
    plugin.php
    views/
    migrations/
```

### Requirements

- `plugin.json` must be present and valid
- `plugin.php` must be present
- The zip root must contain exactly **one** folder (the plugin folder)
- No path traversal (`..`) in file paths
- No executable files (`.exe`, `.sh`, etc.) — these will be rejected

---

## Installing a Plugin

1. Go to **Settings → Plugins** in the Budjit admin panel.
2. Click **Install Plugin**.
3. Upload your `.zip` file (max 10 MB).
4. The installer will validate the manifest, extract the files, run migrations, and register
   the plugin in the database.

To update a plugin, upload a new zip with the same `id` — the existing files will be backed up
before replacement.

---

## Uninstalling a Plugin

Only **third-party plugins** (installed via zip upload) can be uninstalled from the UI. The plugin
folder is backed up to `storage/backups/` before deletion. Built-in Budjit plugins can only be
disabled via the toggle.

---

## Security Guidelines

- Never trust user input — validate and sanitize everything.
- Use the database through the `Database::getInstance()` PDO instance with **prepared statements**.
- Never expose internal paths or error messages to end users.
- Store sensitive data (tokens, keys) encrypted using `PLAID_ENCRYPTION_KEY`.
- Respect Budjit's CSRF protection: always include `<?= Auth::csrfField() ?>` in your forms.
- Call `Auth::requireLogin()` or `Auth::requireAdmin()` at the start of every controller method
  that requires authentication.

---

## Useful Constants

| Constant | Value |
|---|---|
| `BASE_URL` | App base URL (no trailing slash) |
| `ROOT_PATH` | Absolute path to project root |
| `APP_PATH` | `ROOT_PATH/app` |
| `PLUGIN_PATH` | `ROOT_PATH/plugins` |
| `STORAGE_PATH` | `ROOT_PATH/storage` |
| `APP_VERSION` | Current Budjit version string |

---

## Support

- GitHub: *(link to Budjit repo)*
- Docs: *(link to Budjit docs)*
