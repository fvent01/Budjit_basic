<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?><?= Controller::appName() ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.4.0/dist/tabler-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
<style>
<?php
  // Load user's custom colors if logged in
  if (Auth::check()) {
    $settingsModel = new SettingsModel();
    $userId = Auth::id();
    $primaryColor = $settingsModel->getUserPref($userId, 'primary_color',
                    $settingsModel->get('primary_color', '#1e40af'));
    $accentColor = $settingsModel->getUserPref($userId, 'accent_color',
                   $settingsModel->get('accent_color', '#059669'));
    
    // Apply custom colors as CSS variables
    echo "
    :root {
      --blue: " . htmlspecialchars($primaryColor) . ";
      --green: " . htmlspecialchars($accentColor) . ";
      --blue-light: " . htmlspecialchars($primaryColor . "20") . ";
      --green-light: " . htmlspecialchars($accentColor . "20") . ";
    }
    ";
  }
?>
</style>
</head>
<body>

<div class="app-shell">

<script>
// Apply saved theme on page load
(function() {
  const savedTheme = localStorage.getItem('app_theme') || 'system';
  
  function applyTheme(theme) {
    if (theme === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark');
    } else if (theme === 'light') {
      document.documentElement.setAttribute('data-theme', 'light');
    } else {
      // system - detect from OS
      if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.setAttribute('data-theme', 'dark');
      } else {
        document.documentElement.removeAttribute('data-theme');
      }
    }
  }
  
  applyTheme(savedTheme);
  
  // Listen for OS theme changes if system theme is selected
  if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
      if (localStorage.getItem('app_theme') === 'system' || !localStorage.getItem('app_theme')) {
        applyTheme('system');
      }
    });
  }
})();
</script>

  <nav class="sidebar" aria-label="Main navigation">
    <div class="sidebar-logo">
      <?php
        $settingsModel = new SettingsModel();
        $appIcon = htmlspecialchars($settingsModel->get('app_icon', 'ti-home-dollar'));
        $appSlogan = htmlspecialchars($settingsModel->get('app_slogan', 'Family Plan'));
      ?>
      <div class="logo-icon"><i class="ti <?= $appIcon ?>"></i></div>
      <div>
        <div class="logo-name"><?= Controller::appName() ?></div>
        <div class="logo-sub"><?= $appSlogan ?></div>
      </div>
    </div>

    <div class="nav-group">
      <div class="nav-section-label">Main</div>
      <a href="<?= BASE_URL ?>/dashboard"  class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/dashboard')  ? 'active' : '' ?>"><i class="ti ti-layout-dashboard"></i> Dashboard</a>
      <a href="<?= BASE_URL ?>/budgets"    class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/budgets')    ? 'active' : '' ?>"><i class="ti ti-wallet"></i> Budgets</a>
      <a href="<?= BASE_URL ?>/income"     class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/income')     ? 'active' : '' ?>"><i class="ti ti-cash"></i> Income</a>
      <a href="<?= BASE_URL ?>/expenses"   class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/expenses')   ? 'active' : '' ?>">
        <i class="ti ti-credit-card"></i> Expenses
        <?php if (!empty($unpaidCount) && $unpaidCount > 0): ?>
          <span class="nav-badge"><?= $unpaidCount ?></span>
        <?php endif; ?>
      </a>
    </div>

    <?php
    // Plugin nav items
    $pluginNavItems = PluginLoader::collect('nav_items', []);
    // flatten — each hook may return an array of items
    $navItems = [];
    foreach ($pluginNavItems as $batch) {
        if (is_array($batch)) {
            foreach ($batch as $item) { $navItems[] = $item; }
        }
    }
    if (!empty($navItems)): ?>
    <div class="nav-group">
      <div class="nav-section-label">Plugins</div>
      <?php foreach ($navItems as $item): ?>
        <a href="<?= htmlspecialchars($item['url']) ?>"
           class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/' . ($item['match'] ?? '')) ? 'active' : '' ?>">
          <i class="ti <?= htmlspecialchars($item['icon'] ?? 'ti-puzzle') ?>"></i>
          <?= htmlspecialchars($item['label']) ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="nav-group">
      <div class="nav-section-label">Reports</div>
      <a href="<?= BASE_URL ?>/analytics" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/analytics') ? 'active' : '' ?>"><i class="ti ti-chart-bar"></i> Analytics</a>
      <a href="<?= BASE_URL ?>/export"    class="nav-item"><i class="ti ti-download"></i> Export</a>
    </div>

    <div class="nav-group">
      <div class="nav-section-label">System</div>
      <?php if (Auth::isAdmin()): ?>
        <a href="<?= BASE_URL ?>/plugins" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/plugins') ? 'active' : '' ?>"><i class="ti ti-puzzle"></i> Plugins</a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/settings" class="nav-item"><i class="ti ti-settings"></i> Settings</a>
      <a href="<?= dirname(BASE_URL) ?>/docs.html" target="_blank" class="nav-item"><i class="ti ti-book-2"></i> Developer Docs</a>
      <a href="<?= dirname(BASE_URL) ?>/about.html" target="_blank" class="nav-item"><i class="ti ti-info-circle"></i> About</a>
    </div>

    <div class="sidebar-footer">
      <?php $user = Auth::user();
            $sidebarFooterText = htmlspecialchars($settingsModel->get('sidebar_footer_text', '')); ?>
      <div class="user-row">
        <div class="avatar"><?= strtoupper(substr($user['name'], 0, 2)) ?></div>
        <div class="user-info">
          <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
          <div class="user-role"><?= $user['role'] === 1 ? 'Admin' : ($user['role'] === 3 ? 'Viewer' : 'Parent') ?></div>
        </div>
        <a href="<?= BASE_URL ?>/auth/logout" title="Sign out"><i class="ti ti-logout" style="font-size:16px; color:var(--text-tertiary);"></i></a>
      </div>
      <?php if ($sidebarFooterText): ?>
        <div class="sidebar-footer-note" style="margin-top:10px; font-size:12px; color:var(--text-secondary); line-height:1.4;">
          <?= $sidebarFooterText ?>
        </div>
      <?php endif; ?>
    </div>
  </nav>

  <div class="main-area">
    <?php foreach (Controller::getFlash() as $flash): ?>
      <div class="flash flash-<?= htmlspecialchars($flash['type']) ?>" role="alert">
        <i class="ti <?= $flash['type'] === 'success' ? 'ti-circle-check' : ($flash['type'] === 'error' ? 'ti-alert-circle' : 'ti-info-circle') ?>"></i>
        <?= htmlspecialchars($flash['message']) ?>
        <button onclick="this.parentElement.remove()" class="flash-close"><i class="ti ti-x"></i></button>
      </div>
    <?php endforeach; ?>

    <?= isset($content) ? $content : '' ?>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
