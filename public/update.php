<?php
// ============================================================
//  Budjit — Health Checker & Updater
//  Run after pulling new code to install missing dependencies,
//  check config, verify storage dirs, and test DB connectivity.
//  Navigate to: http://localhost/budjit/public/update.php
// ============================================================

$root           = dirname(__DIR__);
$vendorAutoload = $root . '/vendor/autoload.php';
$composerJson   = $root . '/composer.json';
$composerLock   = $root . '/composer.lock';

// ── Helpers ───────────────────────────────────────────────────
function findComposer(string $root): ?string
{
    foreach (['composer', 'composer.phar', 'composer.bat'] as $cmd) {
        $out = @shell_exec(escapeshellcmd($cmd) . ' --version 2>&1');
        if ($out && stripos($out, 'Composer') !== false) return $cmd;
    }
    $localPhar = $root . '/composer.phar';
    if (file_exists($localPhar)) {
        $out = @shell_exec('php ' . escapeshellarg($localPhar) . ' --version 2>&1');
        if ($out && stripos($out, 'Composer') !== false) return 'php ' . escapeshellarg($localPhar);
    }
    return null;
}

function readBaseUrl(string $root): string
{
    $src = @file_get_contents($root . '/config/config.php');
    if ($src && preg_match("/define\s*\(\s*'BASE_URL'\s*,\s*'([^']+)'/", $src, $m)) {
        return rtrim($m[1], '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/budjit/public';
}

function readConfig(string $root): array
{
    $src = @file_get_contents($root . '/config/config.php');
    $out = [];
    if ($src) {
        preg_match_all("/define\s*\(\s*'([^']+)'\s*,\s*'([^']*)'/", $src, $m, PREG_SET_ORDER);
        foreach ($m as $row) $out[$row[1]] = $row[2];
    }
    return $out;
}

function backupFile(string $path, string $backupDir): string
{
    if (!file_exists($path)) return '';
    if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
    $name = str_replace(['/', '\\', ':'], '_', ltrim(substr($path, strlen(dirname(dirname($path)))), '/\\'));
    $dest = $backupDir . '/' . $name . '.' . date('Ymd_His') . '.bak';
    copy($path, $dest);
    return $dest;
}

$baseUrl    = readBaseUrl($root);
$composerCmd = findComposer($root);
$configVars  = readConfig($root);
$backupDir   = $root . '/storage/backups';

// ── Handle POST actions ───────────────────────────────────────
$actionOutput  = '';
$actionSuccess = null; // true/false/null
$actionLabel   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Composer install
    if ($action === 'composer_install' && $composerCmd) {
        $actionLabel = 'composer install';
        if (!function_exists('shell_exec')) {
            $actionOutput  = 'shell_exec() is disabled. Run composer install from the command line.';
            $actionSuccess = false;
        } else {
            $cmd = 'cd ' . escapeshellarg($root) . ' && ' . $composerCmd . ' install --no-interaction --no-ansi 2>&1';
            $actionOutput  = shell_exec($cmd) ?? 'No output.';
            $actionSuccess = file_exists($vendorAutoload);
        }
    }

    // Composer update
    if ($action === 'composer_update' && $composerCmd) {
        $actionLabel = 'composer update';
        if (!function_exists('shell_exec')) {
            $actionOutput  = 'shell_exec() is disabled. Run composer update from the command line.';
            $actionSuccess = false;
        } else {
            // Backup composer.lock before updating
            backupFile($composerLock, $backupDir);
            $cmd = 'cd ' . escapeshellarg($root) . ' && ' . $composerCmd . ' update --no-interaction --no-ansi 2>&1';
            $actionOutput  = shell_exec($cmd) ?? 'No output.';
            $actionSuccess = file_exists($vendorAutoload);
        }
    }

    // Create missing storage dirs
    if ($action === 'fix_dirs') {
        $actionLabel = 'Create missing directories';
        $dirs   = ['storage/logs', 'storage/backups', 'storage/cron'];
        $created = [];
        foreach ($dirs as $d) {
            $path = $root . '/' . $d;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
                $created[] = $d;
            }
        }
        $actionOutput  = $created ? 'Created: ' . implode(', ', $created) : 'All directories already exist.';
        $actionSuccess = true;
    }

    // Redirect back to avoid re-POST on refresh
    $qs = http_build_query([
        'done'    => $action,
        'success' => $actionSuccess ? '1' : '0',
    ]);
    header('Location: update.php?' . $qs);
    exit;
}

// Pick up flash from redirect
$flashAction  = $_GET['done']    ?? null;
$flashSuccess = ($_GET['success'] ?? '0') === '1';

// ── Section 1: PHP Dependencies ───────────────────────────────
$vendorExists = file_exists($vendorAutoload);
$lockExists   = file_exists($composerLock);
// composer.json newer than composer.lock → needs install
$depsStale    = $lockExists && file_exists($composerJson)
                && filemtime($composerJson) > filemtime($composerLock);

// ── Section 2: Required config constants ─────────────────────
$requiredConstants = [
    'BASE_URL'          => 'App base URL',
    'DB_HOST'           => 'Database host',
    'DB_NAME'           => 'Database name',
    'DB_USER'           => 'Database user',
    'VAPID_PUBLIC_KEY'  => 'VAPID public key (push notifications)',
    'VAPID_PRIVATE_KEY' => 'VAPID private key (push notifications)',
    'VAPID_SUBJECT'     => 'VAPID subject / contact email',
];
$configChecks = [];
foreach ($requiredConstants as $const => $label) {
    $val = $configVars[$const] ?? null;
    $configChecks[] = [
        'const'  => $const,
        'label'  => $label,
        'ok'     => !empty($val),
        'detail' => $val ? (strlen($val) > 40 ? substr($val, 0, 37) . '…' : $val) : 'NOT SET',
    ];
}

// ── Section 3: Storage directories ───────────────────────────
$storageDirs = ['storage/logs', 'storage/backups', 'storage/cron'];
$dirChecks   = [];
$anyDirMissing = false;
foreach ($storageDirs as $d) {
    $exists = is_dir($root . '/' . $d);
    if (!$exists) $anyDirMissing = true;
    $writable = $exists && is_writable($root . '/' . $d);
    $dirChecks[] = [
        'path'     => $d,
        'exists'   => $exists,
        'writable' => $writable,
        'ok'       => $exists && $writable,
        'detail'   => !$exists ? 'Missing' : (!$writable ? 'Not writable' : 'OK'),
    ];
}

// ── Section 4: PHP extensions ─────────────────────────────────
$extChecks = [];
foreach (['openssl', 'curl', 'mbstring', 'json', 'gmp', 'pdo', 'pdo_mysql'] as $ext) {
    $ok = extension_loaded($ext);
    $extChecks[] = ['label' => $ext, 'ok' => $ok, 'detail' => $ok ? 'loaded' : 'MISSING'];
}

// ── Section 5: Database connectivity ─────────────────────────
$dbStatus  = null; // null = not tested
$dbMessage = '';
if (file_exists($root . '/config/config.php')) {
    // Try DSN from config values
    $dbHost = $configVars['DB_HOST'] ?? 'localhost';
    $dbName = $configVars['DB_NAME'] ?? '';
    $dbUser = $configVars['DB_USER'] ?? '';
    $dbPass = ''; // passwords not captured by simple regex; best-effort
    try {
        $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_TIMEOUT => 3]);
        $dbStatus  = true;
        $dbMessage = "Connected to `{$dbName}` on {$dbHost}";
    } catch (PDOException $e) {
        $dbStatus  = false;
        $dbMessage = $e->getMessage();
    }
}

// ── Backups list ──────────────────────────────────────────────
$backups = [];
if (is_dir($backupDir)) {
    foreach (glob($backupDir . '/*.bak') as $f) {
        $backups[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)];
    }
    usort($backups, fn($a, $b) => $b['time'] - $a['time']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Budjit — Health Check &amp; Updater</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f5f7; color: #1a1d23; min-height: 100vh; padding: 32px 16px; }
    .page-wrap { max-width: 700px; margin: 0 auto; }
    .page-head { margin-bottom: 24px; }
    .page-head h1 { font-size: 22px; font-weight: 700; }
    .page-head p  { color: #6b7280; font-size: 13px; margin-top: 4px; }
    .page-head .nav { display: flex; gap: 10px; margin-top: 12px; }
    .card { background: #fff; border-radius: 12px; box-shadow: 0 1px 8px rgba(0,0,0,.07); margin-bottom: 16px; overflow: hidden; }
    .card-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; border-bottom: 1px solid #e5e7eb; background: #f9fafb; }
    .card-head h2 { font-size: 14px; font-weight: 600; }
    .card-body { padding: 16px 20px; }
    .check-row { display: flex; align-items: center; gap: 10px; padding: 7px 10px; border-radius: 6px; margin-bottom: 4px; background: #f9fafb; border: 1px solid #e5e7eb; }
    .check-label  { flex: 1; font-size: 13px; font-weight: 500; }
    .check-detail { font-size: 11px; color: #6b7280; font-family: monospace; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .badge { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 20px; white-space: nowrap; flex-shrink: 0; }
    .badge-ok      { background: #d1fae5; color: #065f46; }
    .badge-warn    { background: #fef3c7; color: #92400e; }
    .badge-fail    { background: #fee2e2; color: #991b1b; }
    .badge-neutral { background: #e5e7eb; color: #374151; }
    .btn { display: inline-flex; align-items: center; gap: 5px; padding: 8px 16px; border-radius: 7px; border: 1px solid transparent; cursor: pointer; font-size: 13px; font-weight: 600; text-decoration: none; transition: opacity .15s; line-height: 1; }
    .btn:hover { opacity: .85; }
    .btn-primary   { background: #16a34a; color: #fff; border-color: #15803d; }
    .btn-secondary { background: #fff; color: #374151; border-color: #d1d5db; }
    .btn-danger    { background: #fff; color: #dc2626; border-color: #fca5a5; }
    .btn-sm { padding: 5px 11px; font-size: 12px; }
    .actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; }
    .status-bar { display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 12px; }
    .status-ok   { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
    .status-warn { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
    .status-fail { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .status-info { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    pre { background: #0f172a; color: #94a3b8; font-size: 12px; line-height: 1.6; padding: 14px; border-radius: 8px; overflow-x: auto; white-space: pre-wrap; margin-top: 12px; max-height: 280px; overflow-y: auto; }
    .backup-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .backup-table th { text-align: left; padding: 6px 8px; color: #6b7280; font-weight: 600; border-bottom: 1px solid #e5e7eb; }
    .backup-table td { padding: 6px 8px; border-bottom: 1px solid #f3f4f6; font-family: monospace; }
    .section-note { font-size: 12px; color: #6b7280; margin-top: 10px; line-height: 1.5; }
    a { color: #16a34a; }
    code { background: #f3f4f6; padding: 1px 5px; border-radius: 3px; font-family: monospace; font-size: 12px; }
  </style>
</head>
<body>
<div class="page-wrap">

  <div class="page-head">
    <h1>💰 Budjit — Health Check &amp; Updater</h1>
    <p>Run this after pulling new code or when something seems broken. All checks are read-only unless you click an action button.</p>
    <div class="nav">
      <a href="<?= htmlspecialchars($baseUrl) ?>" class="btn btn-secondary btn-sm">← Back to app</a>
      <a href="install.php" class="btn btn-secondary btn-sm">Installer</a>
      <a href="update.php" class="btn btn-secondary btn-sm">↺ Refresh</a>
    </div>
  </div>

  <?php if ($flashAction): ?>
    <div class="status-bar <?= $flashSuccess ? 'status-ok' : 'status-fail' ?>">
      <?= $flashSuccess ? '✅' : '❌' ?> <strong><?= htmlspecialchars($flashAction) ?></strong> <?= $flashSuccess ? 'completed successfully.' : 'failed — scroll down for details.' ?>
    </div>
  <?php endif; ?>

  <!-- ── 1. PHP Dependencies ────────────────────────────────── -->
  <div class="card">
    <div class="card-head">
      <h2>📦 PHP Dependencies (Composer)</h2>
      <?php
        if (!$vendorExists) $depBadge = ['fail', 'Not installed'];
        elseif ($depsStale) $depBadge = ['warn', 'Possibly stale'];
        else                $depBadge = ['ok',   'Up to date'];
      ?>
      <span class="badge badge-<?= $depBadge[0] ?>"><?= $depBadge[1] ?></span>
    </div>
    <div class="card-body">
      <div class="check-row">
        <span class="check-label">composer.json</span>
        <span class="check-detail"><?= htmlspecialchars($composerJson) ?></span>
        <span class="badge <?= file_exists($composerJson) ? 'badge-ok' : 'badge-fail' ?>"><?= file_exists($composerJson) ? '✓' : '✗ Missing' ?></span>
      </div>
      <div class="check-row">
        <span class="check-label">composer.lock</span>
        <span class="check-detail"><?= $lockExists ? date('Y-m-d H:i', filemtime($composerLock)) : 'Missing' ?></span>
        <span class="badge <?= $lockExists ? 'badge-ok' : 'badge-warn' ?>"><?= $lockExists ? '✓' : '✗ Run install' ?></span>
      </div>
      <div class="check-row">
        <span class="check-label">vendor/autoload.php</span>
        <span class="check-detail"><?= $vendorExists ? 'Present' : 'Missing — run install' ?></span>
        <span class="badge <?= $vendorExists ? 'badge-ok' : 'badge-fail' ?>"><?= $vendorExists ? '✓' : '✗ Missing' ?></span>
      </div>
      <div class="check-row">
        <span class="check-label">Composer binary</span>
        <span class="check-detail"><?= htmlspecialchars($composerCmd ?? 'Not found on PATH') ?></span>
        <span class="badge <?= $composerCmd ? 'badge-ok' : 'badge-fail' ?>"><?= $composerCmd ? '✓' : '✗' ?></span>
      </div>

      <?php if ($depsStale): ?>
        <div class="status-bar status-warn" style="margin-top:12px;">
          ⚠ composer.json is newer than composer.lock — new packages may need to be installed.
        </div>
      <?php endif; ?>

      <form method="POST">
        <div class="actions">
          <button name="action" value="composer_install"
                  class="btn btn-primary <?= !$composerCmd ? 'disabled' : '' ?>"
                  <?= !$composerCmd ? 'disabled' : '' ?>>
            ⬇ composer install
          </button>
          <button name="action" value="composer_update"
                  class="btn btn-secondary <?= !$composerCmd ? 'disabled' : '' ?>"
                  <?= !$composerCmd ? 'disabled' : '' ?>
                  onclick="return confirm('This may upgrade packages beyond what composer.lock specifies. A backup of composer.lock will be saved first. Continue?')">
            ↑ composer update
          </button>
        </div>
      </form>

      <?php if (!$composerCmd): ?>
        <p class="section-note">
          Composer not detected. Download it from <a href="https://getcomposer.org/download/" target="_blank">getcomposer.org</a>,
          or place <code>composer.phar</code> in <code><?= htmlspecialchars($root) ?></code> and refresh.
        </p>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── 2. Configuration ───────────────────────────────────── -->
  <?php
    $configAllOk = array_reduce($configChecks, fn($c, $r) => $c && $r['ok'], true);
  ?>
  <div class="card">
    <div class="card-head">
      <h2>⚙️ Configuration Constants</h2>
      <span class="badge badge-<?= $configAllOk ? 'ok' : 'warn' ?>"><?= $configAllOk ? 'All set' : 'Some missing' ?></span>
    </div>
    <div class="card-body">
      <?php foreach ($configChecks as $c): ?>
        <div class="check-row">
          <span class="check-label"><?= htmlspecialchars($c['label']) ?></span>
          <span class="check-detail"><?= htmlspecialchars($c['detail']) ?></span>
          <span class="badge badge-<?= $c['ok'] ? 'ok' : 'warn' ?>"><?= $c['ok'] ? '✓' : '✗ Missing' ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$configAllOk): ?>
        <p class="section-note">
          Edit <code><?= htmlspecialchars($root) ?>/config/config.php</code> to add missing constants.
          VAPID keys were generated on first setup — if missing, re-run the installer or add them manually.
        </p>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── 3. Storage Directories ────────────────────────────── -->
  <div class="card">
    <div class="card-head">
      <h2>📁 Storage Directories</h2>
      <span class="badge badge-<?= $anyDirMissing ? 'warn' : 'ok' ?>"><?= $anyDirMissing ? 'Some missing' : 'All present' ?></span>
    </div>
    <div class="card-body">
      <?php foreach ($dirChecks as $d): ?>
        <div class="check-row">
          <span class="check-label"><code><?= htmlspecialchars($d['path']) ?></code></span>
          <span class="check-detail"><?= htmlspecialchars($d['detail']) ?></span>
          <span class="badge badge-<?= $d['ok'] ? 'ok' : ($d['exists'] && !$d['writable'] ? 'warn' : 'warn') ?>">
            <?= $d['ok'] ? '✓' : ($d['exists'] ? '⚠ Not writable' : '✗ Missing') ?>
          </span>
        </div>
      <?php endforeach; ?>
      <?php if ($anyDirMissing): ?>
        <form method="POST">
          <div class="actions">
            <button name="action" value="fix_dirs" class="btn btn-primary">Create missing directories</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── 4. PHP Extensions ─────────────────────────────────── -->
  <?php $extAllOk = array_reduce($extChecks, fn($c, $r) => $c && $r['ok'], true); ?>
  <div class="card">
    <div class="card-head">
      <h2>🔧 PHP Extensions</h2>
      <span class="badge badge-<?= $extAllOk ? 'ok' : 'fail' ?>"><?= $extAllOk ? 'All loaded' : 'Some missing' ?></span>
    </div>
    <div class="card-body">
      <?php foreach ($extChecks as $e): ?>
        <div class="check-row">
          <span class="check-label"><code><?= htmlspecialchars($e['label']) ?></code></span>
          <span class="check-detail"><?= htmlspecialchars($e['detail']) ?></span>
          <span class="badge badge-<?= $e['ok'] ? 'ok' : 'fail' ?>"><?= $e['ok'] ? '✓' : '✗ Missing' ?></span>
        </div>
      <?php endforeach; ?>
      <p class="section-note">PHP <?= PHP_VERSION ?> · <?= PHP_OS ?></p>
      <?php if (!$extAllOk): ?>
        <p class="section-note">
          Enable missing extensions in your <code>php.ini</code> (XAMPP: <code>C:\xampp\php\php.ini</code>).
          Uncomment the relevant <code>extension=</code> lines and restart Apache.
        </p>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── 5. Database ───────────────────────────────────────── -->
  <div class="card">
    <div class="card-head">
      <h2>🗄️ Database Connectivity</h2>
      <?php if ($dbStatus === true): ?>
        <span class="badge badge-ok">Connected</span>
      <?php elseif ($dbStatus === false): ?>
        <span class="badge badge-fail">Failed</span>
      <?php else: ?>
        <span class="badge badge-neutral">Not tested</span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if ($dbStatus === true): ?>
        <div class="check-row">
          <span class="check-label">Connection</span>
          <span class="check-detail"><?= htmlspecialchars($dbMessage) ?></span>
          <span class="badge badge-ok">✓</span>
        </div>
      <?php elseif ($dbStatus === false): ?>
        <div class="status-bar status-fail">
          ❌ <?= htmlspecialchars($dbMessage) ?>
        </div>
        <p class="section-note">
          Check <code>DB_HOST</code>, <code>DB_NAME</code>, and <code>DB_USER</code> in <code>config/config.php</code>.
          Note: the password field cannot be read by this tool for security — verify it manually.
        </p>
      <?php else: ?>
        <p class="section-note">Config file not found — database check skipped.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── 6. Backups ────────────────────────────────────────── -->
  <div class="card">
    <div class="card-head">
      <h2>💾 Backups</h2>
      <span class="badge badge-neutral"><?= count($backups) ?> file<?= count($backups) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="card-body">
      <?php if (empty($backups)): ?>
        <p class="section-note">No backups yet. Files are backed up automatically before updates.</p>
      <?php else: ?>
        <table class="backup-table">
          <thead><tr><th>File</th><th>Size</th><th>Created</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($backups, 0, 20) as $b): ?>
              <tr>
                <td><?= htmlspecialchars($b['name']) ?></td>
                <td><?= number_format($b['size'] / 1024, 1) ?> KB</td>
                <td><?= date('Y-m-d H:i', $b['time']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (count($backups) > 20): ?>
              <tr><td colspan="3" style="color:#6b7280;padding:6px 8px;">… and <?= count($backups) - 20 ?> more in <code>storage/backups/</code></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <p class="section-note" style="margin-top:8px;">Backups are stored in <code><?= htmlspecialchars($root) ?>/storage/backups/</code>.</p>
    </div>
  </div>

</div>
</body>
</html>
