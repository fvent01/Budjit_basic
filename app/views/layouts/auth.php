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
  // Load global color settings for auth pages
  $settingsModel = new SettingsModel();
  $primaryColor = $settingsModel->get('primary_color', '#1e40af');
  $accentColor = $settingsModel->get('accent_color', '#059669');
  
  echo "
  :root {
    --blue: " . htmlspecialchars($primaryColor) . ";
    --green: " . htmlspecialchars($accentColor) . ";
    --blue-light: " . htmlspecialchars($primaryColor . "20") . ";
    --green-light: " . htmlspecialchars($accentColor . "20") . ";
  }
  ";
?>
</style>
</head>
<body class="auth-body">

<script>
// Apply saved theme on auth pages
(function() {
  const savedTheme = localStorage.getItem('app_theme') || 'system';
  
  function applyTheme(theme) {
    if (theme === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark');
    } else if (theme === 'light') {
      document.documentElement.setAttribute('data-theme', 'light');
    } else {
      if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.setAttribute('data-theme', 'dark');
      } else {
        document.documentElement.removeAttribute('data-theme');
      }
    }
  }
  
  applyTheme(savedTheme);
  
  if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
      if (localStorage.getItem('app_theme') === 'system' || !localStorage.getItem('app_theme')) {
        applyTheme('system');
      }
    });
  }
})();
</script>

<div class="auth-shell">
  <div class="auth-logo">
    <?php
      $settingsModel = new SettingsModel();
      $appIcon = htmlspecialchars($settingsModel->get('app_icon', 'ti-home-dollar'));
    ?>
    <div class="logo-icon-lg"><i class="ti <?= $appIcon ?>"></i></div>
    <div class="auth-app-name"><?= Controller::appName() ?></div>
  </div>
  <?= $content ?>
</div>
</body>
</html>
