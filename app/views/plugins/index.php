<?php $pageTitle = 'Plugins'; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Plugins</h1>
    <p class="page-sub">Enable or disable feature modules. Changes take effect on next page load.</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('install-modal').classList.add('open')">
      <i class="ti ti-upload"></i> Install Plugin
    </button>
  </div>
</div>

<div class="plugin-grid">
  <?php foreach ($plugins as $slug => $meta): ?>
    <?php
      $isDeprecated = str_contains(strtolower($meta['description'] ?? ''), 'deprecated');
      $isThirdParty = !empty($meta['is_third_party']);
    ?>
    <div class="plugin-card <?= empty($meta['enabled']) ? 'plugin-disabled' : '' ?> <?= $isDeprecated ? 'plugin-deprecated' : '' ?>">
      <div class="plugin-card-top">
        <div class="plugin-icon" style="background:<?= htmlspecialchars($meta['color'] ?? '#888') ?>22; color:<?= htmlspecialchars($meta['color'] ?? '#888') ?>">
          <i class="ti <?= htmlspecialchars($meta['icon'] ?? 'ti-puzzle') ?>"></i>
        </div>
        <div class="plugin-meta">
          <div class="plugin-name">
            <?= htmlspecialchars($meta['name']) ?>
            <?php if ($isThirdParty): ?>
              <span class="badge-third-party">3rd party</span>
            <?php endif; ?>
          </div>
          <div class="plugin-version">v<?= htmlspecialchars($meta['version'] ?? '1.0.0') ?>
            <?php if (!empty($meta['author'])): ?>
              &middot; <?= htmlspecialchars($meta['author']) ?>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!$isDeprecated): ?>
          <form method="POST" action="<?= BASE_URL ?>/plugins/toggle" style="margin-left:auto;">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="slug"   value="<?= htmlspecialchars($slug) ?>">
            <input type="hidden" name="action" value="<?= empty($meta['enabled']) ? 'enable' : 'disable' ?>">
            <button type="submit" class="toggle-btn <?= !empty($meta['enabled']) ? 'toggle-on' : 'toggle-off' ?>"
                    title="<?= empty($meta['enabled']) ? 'Enable' : 'Disable' ?> plugin">
              <span class="toggle-knob"></span>
            </button>
          </form>
        <?php endif; ?>
      </div>

      <p class="plugin-desc"><?= htmlspecialchars($meta['description'] ?? '') ?></p>

      <div class="plugin-card-footer">
        <span class="pill <?= !empty($meta['enabled']) && !$isDeprecated ? 'pill-green' : '' ?>"
              style="<?= (empty($meta['enabled']) || $isDeprecated) ? 'background:var(--bg-secondary);color:var(--text-tertiary)' : '' ?>">
          <?= $isDeprecated ? 'Deprecated' : (!empty($meta['enabled']) ? 'Active' : 'Inactive') ?>
        </span>

        <div style="display:flex;gap:6px;align-items:center;">
          <?php if (!empty($meta['enabled']) && !empty($meta['url'])): ?>
            <a href="<?= BASE_URL ?>/<?= htmlspecialchars($meta['url']) ?>" class="card-link">Open →</a>
          <?php endif; ?>

          <?php if (!empty($meta['homepage'])): ?>
            <a href="<?= htmlspecialchars($meta['homepage']) ?>" target="_blank" rel="noopener" class="card-link"
               title="Plugin homepage"><i class="ti ti-external-link" style="font-size:12px;"></i></a>
          <?php endif; ?>

          <?php if ($isThirdParty): ?>
            <form method="POST" action="<?= BASE_URL ?>/plugins/uninstall"
                  onsubmit="return confirm('Uninstall \'<?= htmlspecialchars(addslashes($meta['name'])) ?>\'?\nFiles will be backed up to storage/backups.')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">
              <button type="submit" class="btn-uninstall" title="Uninstall plugin">
                <i class="ti ti-trash"></i>
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- ── Install Plugin Modal ─────────────────────────────────── -->
<div class="modal-overlay" id="install-modal"
     onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal-box">
    <div class="modal-head">
      <h2>Install Plugin</h2>
      <button class="modal-close"
              onclick="document.getElementById('install-modal').classList.remove('open')">
        <i class="ti ti-x"></i>
      </button>
    </div>
    <div class="modal-body">
      <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">
        Upload a <code>.zip</code> file containing a valid Budjit plugin.
        The zip must include <code>plugin.json</code> and <code>plugin.php</code> at its root.
      </p>
      <form method="POST" action="<?= BASE_URL ?>/plugins/install" enctype="multipart/form-data">
        <?= Auth::csrfField() ?>
        <div class="form-group">
          <label class="form-label">Plugin .zip file</label>
          <input type="file" name="plugin_zip" accept=".zip" required class="form-control">
          <div class="form-hint">Max 10 MB &middot; <code>.zip</code> only</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary"
                  onclick="document.getElementById('install-modal').classList.remove('open')">
            Cancel
          </button>
          <button type="submit" class="btn btn-primary">
            <i class="ti ti-upload"></i> Install
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
.plugin-grid       { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px,1fr)); gap: 14px; }
.plugin-card       { background: var(--bg-primary); border: 0.5px solid var(--border); border-radius: var(--radius-lg); padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.plugin-disabled   { opacity: 0.65; }
.plugin-deprecated { opacity: 0.40; }
.plugin-card-top   { display: flex; align-items: center; gap: 12px; }
.plugin-icon       { width: 38px; height: 38px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.plugin-name       { font-size: 14px; font-weight: 500; color: var(--text-primary); display: flex; align-items: center; gap: 6px; }
.plugin-version    { font-size: 10px; color: var(--text-tertiary); margin-top: 2px; }
.plugin-desc       { font-size: 12px; color: var(--text-secondary); line-height: 1.5; }
.plugin-card-footer { display: flex; align-items: center; justify-content: space-between; }
.badge-third-party { font-size: 10px; font-weight: 600; background: #ede9fe; color: #6d28d9; padding: 2px 7px; border-radius: 20px; white-space: nowrap; flex-shrink: 0; }
.toggle-btn  { position: relative; width: 40px; height: 22px; border-radius: 11px; border: none; cursor: pointer; transition: background 0.2s; padding: 0; flex-shrink: 0; }
.toggle-on   { background: var(--green); }
.toggle-off  { background: var(--text-tertiary); }
.toggle-knob { position: absolute; top: 3px; width: 16px; height: 16px; background: #fff; border-radius: 50%; transition: left 0.2s; }
.toggle-on  .toggle-knob { left: 21px; }
.toggle-off .toggle-knob { left: 3px; }
.btn-uninstall { background: none; border: 1px solid var(--border); color: var(--text-tertiary); border-radius: var(--radius-sm); padding: 3px 7px; cursor: pointer; font-size: 12px; line-height: 1; transition: color .15s, border-color .15s; }
.btn-uninstall:hover { color: #dc2626; border-color: #fca5a5; }
.modal-overlay        { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 900; align-items: center; justify-content: center; }
.modal-overlay.open   { display: flex; }
.modal-box            { background: var(--bg-primary); border-radius: var(--radius-lg); width: 100%; max-width: 480px; box-shadow: 0 8px 32px rgba(0,0,0,.2); margin: 16px; }
.modal-head           { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--border); }
.modal-head h2        { font-size: 15px; font-weight: 600; }
.modal-close          { background: none; border: none; cursor: pointer; color: var(--text-tertiary); font-size: 18px; padding: 2px; line-height: 1; }
.modal-close:hover    { color: var(--text-primary); }
.modal-body           { padding: 20px; }
.modal-footer         { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border); }
.form-hint            { font-size: 11px; color: var(--text-tertiary); margin-top: 4px; }
</style>
ight: 600; }
.modal-close          { background: none; border: none; cursor: pointer; color: var(--text-tertiary); font-size: 18px; padding: 2px; line-height: 1; }
.modal-close:hover    { color: var(--text-primary); }
.modal-body           { padding: 20px; }
.modal-footer         { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border); }
.form-hint            { font-size: 11px; color: var(--text-tertiary); margin-top: 4px; }
</style>
