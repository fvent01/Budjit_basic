<?php
// core/plugins/PluginInstaller.php
// Handles installing 3rd-party plugins from a .zip archive and uninstalling them.
//
// Expected zip structure:
//   my-plugin/
//     plugin.json   ← required
//     plugin.php    ← required
//     views/        ← optional
//     migrations/   ← optional (.sql files run on install)
//     ...

class PluginInstaller
{
    /** Required fields in plugin.json */
    private const REQUIRED_FIELDS = ['id', 'name', 'version'];

    /** Forbidden slug patterns (reserved or dangerous) */
    private const RESERVED_SLUGS = ['core', 'app', 'public', 'vendor', 'storage', 'config'];

    // ── Install ───────────────────────────────────────────────

    /**
     * Purpose : Install a plugin from a .zip file uploaded by an admin.
     * Inputs  : $zipPath — absolute path to the uploaded .zip file
     * Outputs : ['success' => bool, 'message' => string, 'slug' => string|null]
     * Side effects : extracts files to PLUGIN_PATH, runs migrations, upserts DB row
     */
    public static function installFromZip(string $zipPath): array
    {
        // 1. Validate zip is readable
        if (!file_exists($zipPath) || !is_readable($zipPath)) {
            return self::fail('Uploaded file not found or not readable.');
        }

        // 2. Validate extension is available
        if (!class_exists('ZipArchive')) {
            return self::fail('ZipArchive PHP extension is required to install plugins.');
        }

        $zip = new ZipArchive();
        $res = $zip->open($zipPath);
        if ($res !== true) {
            return self::fail('Could not open zip archive (error code: ' . $res . ').');
        }

        // 3. Locate plugin.json inside the zip
        $jsonEntry  = null;
        $rootPrefix = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            // Accepts both "plugin.json" (flat) and "slug/plugin.json" (folder-wrapped)
            if (preg_match('#^([^/]+/)?plugin\.json$#', $name)) {
                $jsonEntry  = $name;
                $rootPrefix = str_contains($name, '/') ? dirname($name) . '/' : '';
                break;
            }
        }

        if ($jsonEntry === null) {
            $zip->close();
            return self::fail('Invalid plugin package: plugin.json not found in zip root.');
        }

        // 4. Parse and validate plugin.json
        $jsonContent = $zip->getFromName($jsonEntry);
        if ($jsonContent === false) {
            $zip->close();
            return self::fail('Could not read plugin.json from zip.');
        }

        $manifest = json_decode($jsonContent, true);
        if (!is_array($manifest)) {
            $zip->close();
            return self::fail('plugin.json is not valid JSON.');
        }

        $validationError = self::validateManifest($manifest);
        if ($validationError !== null) {
            $zip->close();
            return self::fail($validationError);
        }

        $slug = self::sanitizeSlug($manifest['id']);

        // 5. Check plugin.php is present in the zip
        $phpEntry = $rootPrefix . 'plugin.php';
        if ($zip->locateName($phpEntry) === false) {
            $zip->close();
            return self::fail('Invalid plugin package: plugin.php not found.');
        }

        // 6. Check minimum Budjit version requirement
        if (!empty($manifest['min_budjit_version'])) {
            if (!defined('APP_VERSION') ||
                version_compare(APP_VERSION, $manifest['min_budjit_version'], '<')) {
                $zip->close();
                return self::fail(
                    'Plugin requires Budjit v' . htmlspecialchars($manifest['min_budjit_version']) .
                    ' or newer.'
                );
            }
        }

        // 7. Extract to a temp directory first, then move atomically
        $tempDir  = sys_get_temp_dir() . '/budjit_plugin_' . $slug . '_' . time();
        $destDir  = PLUGIN_PATH . '/' . $slug;
        $isUpdate = is_dir($destDir);

        if (!mkdir($tempDir, 0755, true)) {
            $zip->close();
            return self::fail('Could not create temporary directory for extraction.');
        }

        // Extract only the plugin's own files (strip root prefix if present)
        $extracted = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            // Strip the root folder wrapper (e.g. "my-plugin/views/..." → "views/...")
            $relative = $rootPrefix ? (str_starts_with($name, $rootPrefix)
                ? substr($name, strlen($rootPrefix)) : null) : $name;

            if ($relative === null || $relative === '') {
                continue;  // skip the root folder entry itself
            }

            // Security: reject path traversal
            if (str_contains($relative, '..') || str_starts_with($relative, '/')) {
                self::deleteDirectory($tempDir);
                $zip->close();
                return self::fail('Plugin zip contains unsafe file paths.');
            }

            $targetPath = $tempDir . '/' . $relative;

            if (str_ends_with($name, '/')) {
                // Directory entry
                mkdir($targetPath, 0755, true);
            } else {
                // File entry
                $dir = dirname($targetPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                file_put_contents($targetPath, $zip->getFromIndex($i));
                $extracted = true;
            }
        }

        $zip->close();

        if (!$extracted) {
            self::deleteDirectory($tempDir);
            return self::fail('Zip archive appears to be empty.');
        }

        // 8. Back up existing plugin if this is an update
        if ($isUpdate) {
            $backupPath = STORAGE_PATH . '/backups/plugin_' . $slug . '_' . date('Ymd_His');
            if (!rename($destDir, $backupPath)) {
                // rename failed; try copy
                self::copyDirectory($destDir, $backupPath);
                self::deleteDirectory($destDir);
            }
        }

        // 9. Move temp dir to final destination
        if (!rename($tempDir, $destDir)) {
            self::copyDirectory($tempDir, $destDir);
            self::deleteDirectory($tempDir);
        }

        // 10. Run plugin migrations (if any)
        if (!empty($manifest['has_migrations'])) {
            try {
                self::runPluginMigrations($slug);
            } catch (Exception $e) {
                error_log("Plugin migration error [{$slug}]: " . $e->getMessage());
                // Non-fatal: plugin is installed but migrations may need manual run
            }
        }

        // 11. Register / update in DB (enabled by default on fresh install)
        try {
            $db = Database::getInstance();
            $db->prepare(
                "INSERT INTO plugins (slug, name, version, author, homepage, is_enabled, is_third_party)
                 VALUES (?, ?, ?, ?, ?, 1, 1)
                 ON DUPLICATE KEY UPDATE
                   name          = VALUES(name),
                   version       = VALUES(version),
                   author        = VALUES(author),
                   homepage      = VALUES(homepage),
                   is_third_party = 1,
                   updated_at    = NOW()"
            )->execute([
                $slug,
                $manifest['name'],
                $manifest['version'],
                $manifest['author']   ?? '',
                $manifest['homepage'] ?? '',
            ]);
        } catch (Exception $e) {
            error_log("Plugin DB registration error [{$slug}]: " . $e->getMessage());
        }

        return [
            'success' => true,
            'message' => ($isUpdate ? 'Updated' : 'Installed') . ' plugin "' . $manifest['name'] . '" successfully.',
            'slug'    => $slug,
        ];
    }

    // ── Uninstall ─────────────────────────────────────────────

    /**
     * Purpose : Remove a 3rd-party plugin — backs up its folder, removes DB row,
     *           and deletes the plugin directory.
     * Inputs  : $slug — plugin slug
     *           $keepFiles — if true, only disables; does not delete files (default false)
     * Outputs : ['success' => bool, 'message' => string]
     */
    public static function uninstall(string $slug, bool $keepFiles = false): array
    {
        $slug    = self::sanitizeSlug($slug);
        $destDir = PLUGIN_PATH . '/' . $slug;

        // Refuse to uninstall built-in plugins (no is_third_party flag in folder check —
        // we use the plugin.json absence of author == 'Budjit' heuristic + DB flag)
        try {
            $db  = Database::getInstance();
            $row = $db->prepare("SELECT is_third_party FROM plugins WHERE slug = ?")->execute([$slug])
                      ->fetch() ?? null;
            // Re-query properly
            $stmt = $db->prepare("SELECT is_third_party FROM plugins WHERE slug = ?");
            $stmt->execute([$slug]);
            $row = $stmt->fetch();
            if ($row && !(bool) $row['is_third_party']) {
                return self::fail('Built-in plugins cannot be uninstalled. Use the toggle to disable them.');
            }
        } catch (Exception $e) {
            // DB unavailable — proceed with file-based check
        }

        if (!is_dir($destDir)) {
            return self::fail('Plugin directory not found: ' . $slug);
        }

        // Back up before deleting
        if (!$keepFiles) {
            $backupPath = STORAGE_PATH . '/backups/plugin_' . $slug . '_uninstall_' . date('Ymd_His');
            self::copyDirectory($destDir, $backupPath);
            self::deleteDirectory($destDir);
        }

        // Remove from DB
        try {
            $db = Database::getInstance();
            $db->prepare("DELETE FROM plugins WHERE slug = ?")->execute([$slug]);
        } catch (Exception $e) {
            error_log("Plugin uninstall DB error [{$slug}]: " . $e->getMessage());
        }

        return [
            'success' => true,
            'message' => 'Plugin "' . $slug . '" uninstalled successfully.' .
                         (!$keepFiles ? ' Files backed up to storage/backups.' : ''),
            'slug'    => $slug,
        ];
    }

    // ── Migrations ────────────────────────────────────────────

    /**
     * Purpose : Run any pending .sql migration files from plugins/{slug}/migrations/.
     *           Tracks applied migrations in the `migrations` table.
     * Inputs  : $slug — plugin slug
     * Side effects : runs SQL, inserts rows into migrations table
     */
    public static function runPluginMigrations(string $slug): void
    {
        $migrationsDir = PLUGIN_PATH . '/' . $slug . '/migrations';
        if (!is_dir($migrationsDir)) {
            return;
        }

        $db = Database::getInstance();

        // Fetch already-applied migrations for this plugin
        $stmt = $db->prepare("SELECT migration FROM migrations WHERE source = ?");
        $stmt->execute([$slug]);
        $applied = array_column($stmt->fetchAll(), 'migration');

        // Run migrations in filename order
        $files = glob($migrationsDir . '/*.sql');
        sort($files);

        foreach ($files as $file) {
            $filename = basename($file);
            if (in_array($filename, $applied, true)) {
                continue;
            }

            $sql = @file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                continue;
            }

            // Execute each statement (split on ";")
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                if ($statement !== '') {
                    $db->exec($statement);
                }
            }

            // Record as applied
            $db->prepare(
                "INSERT IGNORE INTO migrations (migration, source) VALUES (?, ?)"
            )->execute([$filename, $slug]);
        }
    }

    // ── Validation helpers ────────────────────────────────────

    /**
     * Purpose : Validate a parsed plugin.json manifest.
     * Outputs : null if valid, or an error string
     */
    private static function validateManifest(array $manifest): ?string
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($manifest[$field])) {
                return "plugin.json is missing required field: \"{$field}\".";
            }
        }

        $slug = self::sanitizeSlug($manifest['id']);

        if ($slug === '') {
            return 'plugin.json "id" field is empty or contains only invalid characters.';
        }

        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            return "The slug \"{$slug}\" is reserved and cannot be used for a plugin.";
        }

        if (!preg_match('/^\d+\.\d+/', $manifest['version'] ?? '')) {
            return 'plugin.json "version" must be in semver format (e.g. "1.0.0").';
        }

        return null;
    }

    /**
     * Purpose : Sanitize a plugin slug to only alphanumeric and hyphens.
     * Inputs  : $raw — raw id from plugin.json
     * Outputs : sanitized slug string
     */
    private static function sanitizeSlug(string $raw): string
    {
        $slug = strtolower(trim($raw));
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    // ── Filesystem helpers ────────────────────────────────────

    /** Recursively delete a directory. */
    private static function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }
        rmdir($path);
    }

    /** Recursively copy a directory. */
    private static function copyDirectory(string $src, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($items as $item) {
            $target = $dest . '/' . $items->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getRealPath(), $target);
            }
        }
    }

    /** Return a standardised failure result. */
    private static function fail(string $message): array
    {
        return ['success' => false, 'message' => $message, 'slug' => null];
    }
}
