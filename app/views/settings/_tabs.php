<?php
// app/views/settings/_tabs.php
// Include at the top of each settings view
$currentUri = $_SERVER['REQUEST_URI'];
$tabs = [
    'profile'    => ['icon' => 'ti-user',       'label' => 'Profile'],
    'appearance' => ['icon' => 'ti-palette',     'label' => 'Appearance'],
    'categories' => ['icon' => 'ti-tag',         'label' => 'Categories'],
];
if (Auth::isAdmin()) {
    $tabs['general'] = ['icon' => 'ti-settings',    'label' => 'General'];
    $tabs['users']   = ['icon' => 'ti-users',        'label' => 'Users'];
}
?>
<div class="settings-tabs">
  <?php foreach ($tabs as $slug => $tab): ?>
    <a href="<?= BASE_URL ?>/settings/<?= $slug ?>"
       class="settings-tab <?= str_contains($currentUri, "/settings/{$slug}") ? 'settings-tab-active' : '' ?>">
      <i class="ti <?= $tab['icon'] ?>"></i> <?= $tab['label'] ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!isset($tabs_css_done)): $tabs_css_done = true; ?>
<style>
.settings-wrap  { max-width: 700px; }
.settings-tabs  { display:flex; gap:4px; margin-bottom:20px; border-bottom:0.5px solid var(--border); padding-bottom:0; }
.settings-tab   { display:flex; align-items:center; gap:7px; padding:8px 14px; font-size:13px; color:var(--text-secondary); text-decoration:none; border-radius:var(--radius-md) var(--radius-md) 0 0; margin-bottom:-0.5px; border:0.5px solid transparent; border-bottom:none; transition:background 0.12s; }
.settings-tab:hover { background:var(--bg-secondary); color:var(--text-primary); }
.settings-tab-active { background:var(--bg-primary); color:var(--text-primary); font-weight:500; border-color:var(--border); border-bottom-color:var(--bg-primary); }
.settings-tab i { font-size:16px; }
.settings-section { margin-bottom:24px; }
.settings-section-title { font-size:13px; font-weight:600; color:var(--text-primary); margin-bottom:14px; padding-bottom:8px; border-bottom:0.5px solid var(--border); }
.settings-row { display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:0.5px solid var(--border); gap:20px; }
.settings-row:last-child { border-bottom:none; }
.settings-row-label { font-size:13px; font-weight:500; color:var(--text-primary); }
.settings-row-desc  { font-size:11px; color:var(--text-tertiary); margin-top:2px; }
.settings-row-control { flex-shrink:0; }
</style>
<?php endif; ?>