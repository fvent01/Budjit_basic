<?php $pageTitle = 'Plugins'; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Plugins</h1>
    <p class="page-sub">Enable or disable feature modules. Changes take effect on next page load.</p>
  </div>
</div>

<div class="plugin-grid">
  <?php foreach ($plugins as $slug => $meta): ?>
    <div class="plugin-card <?= empty($meta['enabled']) ? 'plugin-disabled' : '' ?>">
      <div class="plugin-card-top">
        <div class="plugin-icon" style="background:<?= htmlspecialchars($meta['color'] ?? '#888') ?>22; color:<?= htmlspecialchars($meta['color'] ?? '#888') ?>">
          <i class="ti <?= htmlspecialchars($meta['icon'] ?? 'ti-puzzle') ?>"></i>
        </div>
        <div class="plugin-meta">
          <div class="plugin-name"><?= htmlspecialchars($meta['name']) ?></div>
          <div class="plugin-version">v<?= htmlspecialchars($meta['version'] ?? '1.0.0') ?></div>
        </div>
        <!-- Toggle switch -->
        <form method="POST" action="<?= BASE_URL ?>/plugins/toggle" style="margin-left:auto;">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">
          <input type="hidden" name="action" value="<?= empty($meta['enabled']) ? 'enable' : 'disable' ?>">
          <button type="submit" class="toggle-btn <?= !empty($meta['enabled']) ? 'toggle-on' : 'toggle-off' ?>"
                  title="<?= empty($meta['enabled']) ? 'Enable' : 'Disable' ?> plugin">
            <span class="toggle-knob"></span>
          </button>
        </form>
      </div>

      <p class="plugin-desc"><?= htmlspecialchars($meta['description'] ?? '') ?></p>

      <div class="plugin-card-footer">
        <span class="pill <?= !empty($meta['enabled']) ? 'pill-green' : '' ?>"
              style="<?= empty($meta['enabled']) ? 'background:var(--bg-secondary);color:var(--text-tertiary)' : '' ?>">
          <?= !empty($meta['enabled']) ? 'Active' : 'Inactive' ?>
        </span>
        <?php if (!empty($meta['enabled'])): ?>
          <a href="<?= BASE_URL ?>/<?= htmlspecialchars($meta['url'] ?? $slug) ?>" class="card-link">Open →</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<style>
.plugin-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px,1fr)); gap: 14px; }
.plugin-card { background: var(--bg-primary); border: 0.5px solid var(--border); border-radius: var(--radius-lg); padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.plugin-disabled { opacity: 0.65; }
.plugin-card-top { display: flex; align-items: center; gap: 12px; }
.plugin-icon { width: 38px; height: 38px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.plugin-name { font-size: 14px; font-weight: 500; color: var(--text-primary); }
.plugin-version { font-size: 10px; color: var(--text-tertiary); }
.plugin-desc { font-size: 12px; color: var(--text-secondary); line-height: 1.5; }
.plugin-card-footer { display: flex; align-items: center; justify-content: space-between; }

/* Toggle switch */
.toggle-btn { position: relative; width: 40px; height: 22px; border-radius: 11px; border: none; cursor: pointer; transition: background 0.2s; padding: 0; flex-shrink: 0; }
.toggle-on  { background: var(--green); }
.toggle-off { background: var(--text-tertiary); }
.toggle-knob { position: absolute; top: 3px; width: 16px; height: 16px; background: #fff; border-radius: 50%; transition: left 0.2s; }
.toggle-on  .toggle-knob { left: 21px; }
.toggle-off .toggle-knob { left: 3px; }
</style>
