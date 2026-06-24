<?php
// ============================================================
//  Budjit — Web Installer
//  Accessible when vendor/autoload.php is missing.
//  Navigate to: http://localhost/budjit/public/install.php
// ============================================================

$root            = dirname(__DIR__);
$vendorAutoload  = $root . '/vendor/autoload.php';
$composerJson    = $root . '/composer.json';

$alreadyInstalled = file_exists($vendorAutoload);
$forceReinstall   = isset($_GET['force']);

// ── Find Composer ─────────────────────────────────────────────
function findComposer(string $root): ?string
{
    $candidates = ['composer', 'composer.phar', 'composer.bat'];
    foreach ($candidates as $cmd) {
        $out = @shell_exec(escapeshellcmd($cmd) . ' --version 2>&1');
        if ($out && stripos($out, 'Composer') !== false) {
            return $cmd;
        }
    }
    // composer.phar in project root
    $localPhar = $root . '/composer.phar';
    if (file_exists($localPhar)) {
        $out = @shell_exec('php ' . escapeshellarg($localPhar) . ' --version 2>&1');
        if ($out && stripos($out, 'Composer') !== false) {
            return 'php ' . escapeshellarg($localPhar);
        }
    }
    return null;
}

// ── Pre-flight checks ─────────────────────────────────────────
$composerCmd = findComposer($root);

$checks = [
    ['label' => 'PHP &ge; 8.0',   'ok' => version_compare(PHP_VERSION, '8.0.0', '>='), 'detail' => PHP_VERSION],
    ['label' => 'composer.json',  'ok' => file_exists($composerJson),                  'detail' => $composerJson],
    ['label' => 'Composer',       'ok' => (bool)$composerCmd,                           'detail' => $composerCmd ?? 'Not found — see below'],
];
foreach (['openssl', 'curl', 'mbstring', 'json', 'gmp'] as $ext) {
    $ok = extension_loaded($ext);
    $checks[] = ['label' => "ext-{$ext}", 'ok' => $ok, 'detail' => $ok ? 'loaded' : 'MISSING'];
}

$preflight  = array_reduce($checks, fn($carry, $c) => $carry && $c['ok'], true);
$canInstall = $preflight;

// ── Run install ───────────────────────────────────────────────
$output  = '';
$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'install') {
    if (!$canInstall) {
        $error = 'Pre-flight checks failed. Fix the issues above before installing.';
    } elseif (!function_exists('shell_exec')) {
        $error = 'shell_exec() is disabled on this server. Run <code>composer install</code> from the command line instead.';
    } else {
        $cmd    = 'cd ' . escapeshellarg($root) . ' && ' . $composerCmd . ' install --no-interaction --no-ansi 2>&1';
        $output = shell_exec($cmd) ?? 'No output captured.';
        if (file_exists($vendorAutoload)) {
            $success = true;
        } else {
            $error = 'composer install completed but vendor/autoload.php was not created. See output above.';
        }
    }
}

// ── Determine base URL for redirect ──────────────────────────
$configFile = $root . '/config/config.php';
$baseUrl    = '';
if (file_exists($configFile)) {
    // Read BASE_URL without executing full bootstrap
    $src = file_get_contents($configFile);
    if (preg_match("/define\s*\(\s*'BASE_URL'\s*,\s*'([^']+)'/", $src, $m)) {
        $baseUrl = rtrim($m[1], '/');
    }
}
if (!$baseUrl) {
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $scheme . '://' . $host . '/budjit/public';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Budjit — Installer</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f5f7; color: #1a1d23; min-height: 100vh; display: flex; align-items: flex-start; justify-content: center; padding: 40px 16px; }
    .card { background: #fff; border-radius: 12px; box-shadow: 0 2px 16px rgba(0,0,0,.08); width: 100%; max-width: 640px; overflow: hidden; }
    .card-head { background: #1a1d23; padding: 24px 28px; }
    .card-head h1 { color: #fff; font-size: 20px; font-weight: 700; }
    .card-head p  { color: #9ca3af; font-size: 13px; margin-top: 4px; }
    .card-body { padding: 24px 28px; }
    .section-title { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; margin: 20px 0 10px; }
    .check-row { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 6px; margin-bottom: 4px; background: #f9fafb; border: 1px solid #e5e7eb; }
    .check-label { flex: 1; font-size: 13px; font-weight: 500; }
    .check-detail { font-size: 12px; color: #6b7280; font-family: monospace; }
    .badge { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 20px; white-space: nowrap; }
    .badge-ok  { background: #d1fae5; color: #065f46; }
    .badge-fail { background: #fee2e2; color: #991b1b; }
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; transition: opacity .15s; }
    .btn:hover { opacity: .88; }
    .btn-primary  { background: #16a34a; color: #fff; }
    .btn-secondary { background: #e5e7eb; color: #374151; }
    .btn-disabled { background: #d1d5db; color: #9ca3af; cursor: not-allowed; pointer-events: none; }
    .actions { display: flex; gap: 10px; align-items: center; margin-top: 20px; flex-wrap: wrap; }
    pre { background: #0f172a; color: #94a3b8; font-size: 12px; line-height: 1.6; padding: 16px; border-radius: 8px; overflow-x: auto; white-space: pre-wrap; margin-top: 16px; max-height: 320px; overflow-y: auto; }
    .alert { padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-top: 16px; }
    .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
    .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .alert-info    { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .divider { border: none; border-top: 1px solid #e5e7eb; margin: 20px 0; }
    .composer-help { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 14px 16px; font-size: 13px; color: #92400e; margin-top: 12px; line-height: 1.6; }
    .composer-help code { background: #fef3c7; padding: 1px 5px; border-radius: 3px; font-family: monospace; }
    a { color: #16a34a; }
  </style>
</head>
<body>
<div class="card">
  <div class="card-head">
    <h1>💰 Budjit — Installer</h1>
    <p>First-run dependency installer. This page disappears once setup is complete.</p>
  </div>
  <div class="card-body">

    <?php if ($alreadyInstalled && !$forceReinstall): ?>
      <div class="alert alert-success">
        ✅ Dependencies are already installed. <a href="<?= htmlspecialchars($baseUrl) ?>">Open Budjit →</a>
      </div>
      <div class="actions">
        <a href="update.php" class="btn btn-secondary">Run health check / updater →</a>
        <a href="install.php?force=1" class="btn btn-secondary" style="font-size:12px;">Force reinstall</a>
      </div>

    <?php else: ?>

      <!-- Pre-flight checks -->
      <div class="section-title">Pre-flight checks</div>
      <?php foreach ($checks as $c): ?>
        <div class="check-row">
          <span class="check-label"><?= $c['label'] ?></span>
          <span class="check-detail"><?= htmlspecialchars((string)$c['detail']) ?></span>
          <span class="badge <?= $c['ok'] ? 'badge-ok' : 'badge-fail' ?>"><?= $c['ok'] ? '✓ OK' : '✗ FAIL' ?></span>
        </div>
      <?php endforeach; ?>

      <?php if (!$composerCmd): ?>
        <div class="composer-help">
          <strong>Composer not found on PATH.</strong><br>
          Option 1 — Download the <a href="https://getcomposer.org/download/" target="_blank">Windows installer</a> and install globally.<br>
          Option 2 — Download <a href="https://getcomposer.org/composer.phar" target="_blank">composer.phar</a> and place it in <code><?= htmlspecialchars($root) ?></code>. This installer will detect it automatically — then refresh this page.
        </div>
      <?php endif; ?>

      <hr class="divider">

      <?php if ($success): ?>
        <div class="alert alert-success">
          ✅ Installation complete! Redirecting to Budjit…
          <script>setTimeout(function(){ window.location.href = <?= json_encode($baseUrl) ?>; }, 2000);</script>
        </div>
        <div class="actions">
          <a href="<?= htmlspecialchars($baseUrl) ?>" class="btn btn-primary">Open Budjit →</a>
          <a href="update.php" class="btn btn-secondary">Run health check →</a>
        </div>
      <?php elseif ($error): ?>
        <div class="alert alert-error">❌ <?= $error ?></div>
      <?php endif; ?>

      <?php if ($output): ?>
        <div class="section-title">Composer output</div>
        <pre><?= htmlspecialchars($output) ?></pre>
      <?php endif; ?>

      <?php if (!$success): ?>
        <div class="alert alert-info" style="margin-top:<?= $output ? '16' : '0' ?>px;">
          This will run <strong>composer install</strong> in <code><?= htmlspecialchars($root) ?></code> and install all required PHP libraries, including encrypted web-push support.
        </div>
        <form method="POST">
          <input type="hidden" name="action" value="install">
          <div class="actions">
            <button type="submit" class="btn <?= $canInstall ? 'btn-primary' : 'btn-disabled' ?>">
              ⬇ Install dependencies
            </button>
            <?php if ($alreadyInstalled): ?>
              <a href="<?= htmlspecialchars($baseUrl) ?>" class="btn btn-secondary">Skip (already installed) →</a>
            <?php endif; ?>
          </div>
        </form>

        <hr class="divider">
        <div class="section-title">Manual install (command line)</div>
        <pre>cd <?= htmlspecialchars($root) ?>
composer install</pre>
        <p style="font-size:12px;color:#6b7280;margin-top:8px;">After running this, refresh the page or navigate to <a href="<?= htmlspecialchars($baseUrl) ?>"><?= htmlspecialchars($baseUrl) ?></a>.</p>
      <?php endif; ?>

    <?php endif; ?>

  </div>
</div>
</body>
</html>
