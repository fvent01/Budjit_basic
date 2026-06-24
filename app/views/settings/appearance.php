<?php $pageTitle = 'Appearance Settings'; ?>
<?php require APP_PATH . '/views/settings/_tabs.php'; ?>

<div class="settings-wrap">
  <div class="card">
    <div class="settings-section-title">Theme</div>
    <form method="POST" action="<?= BASE_URL ?>/settings/appearance" id="theme-form">
      <?= Auth::csrfField() ?>

      <div class="theme-grid">
        <?php
        $themes = [
          'light'  => ['icon' => 'ti-sun',           'label' => 'Light',  'desc' => 'Always use light mode'],
          'dark'   => ['icon' => 'ti-moon',           'label' => 'Dark',   'desc' => 'Always use dark mode'],
          'system' => ['icon' => 'ti-device-desktop', 'label' => 'System', 'desc' => 'Follow your OS setting'],
        ];
        foreach ($themes as $val => $theme_opt):
          $icon = $theme_opt['icon'];
          $label = $theme_opt['label'];
          $desc = $theme_opt['desc'];
        ?>
          <label class="theme-option <?= $theme === $val ? 'theme-selected' : '' ?>">
            <input type="radio" name="theme" value="<?= $val ?>"
                   <?= $theme === $val ? 'checked' : '' ?>
                   onchange="applyAndSubmitTheme()" style="display:none;">
            <div class="theme-preview theme-preview-<?= $val ?>">
              <i class="ti <?= $icon ?>" style="font-size:28px;"></i>
            </div>
            <div style="text-align:center; margin-top:8px;">
              <div style="font-size:13px; font-weight:500; color:var(--text-primary);"><?= $label ?></div>
              <div style="font-size:11px; color:var(--text-tertiary);"><?= $desc ?></div>
            </div>
          </label>
        <?php endforeach; ?>
      </div>

      <?php if (Auth::isAdmin()): ?>
        <div style="margin-top:16px; padding-top:14px; border-top:0.5px solid var(--border);">
          <label class="form-check" style="font-size:13px;">
            <input type="checkbox" name="set_global" value="1">
            Set this as the default theme for all users
          </label>
        </div>
      <?php endif; ?>

    </form>
  </div>

  <!-- Color Themes -->
  <div class="card" style="margin-top:14px;">
    <div class="settings-section-title">Color Theme</div>
    <form method="POST" action="<?= BASE_URL ?>/settings/appearance" id="color-form">
      <?= Auth::csrfField() ?>

      <div style="margin-bottom:16px;">
        <div style="font-size:12px; color:var(--text-secondary); margin-bottom:10px;">Choose a predefined color scheme:</div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); gap:10px;">
          <?php
          $colorSchemes = [
            'default'  => ['primary' => '#1e40af', 'accent' => '#059669', 'label' => 'Default Blue'],
            'modern'   => ['primary' => '#7c3aed', 'accent' => '#db2777', 'label' => 'Modern Purple'],
            'forest'   => ['primary' => '#065f46', 'accent' => '#16a34a', 'label' => 'Forest Green'],
            'ocean'    => ['primary' => '#0369a1', 'accent' => '#0891b2', 'label' => 'Ocean Teal'],
            'sunset'   => ['primary' => '#ea580c', 'accent' => '#f59e0b', 'label' => 'Sunset Orange'],
          ];
          $currentColorScheme = isset($colorScheme) ? $colorScheme : 'default';
          ?>
          <?php foreach ($colorSchemes as $scheme => $colors): ?>
            <label style="cursor:pointer; padding:12px; border:0.5px solid var(--border); border-radius:var(--radius-md); text-align:center; transition:all 0.2s; <?= $currentColorScheme === $scheme ? 'border-color:var(--green); background:var(--green-light);' : '' ?>">
              <input type="radio" name="color_scheme" value="<?= $scheme ?>" 
                     data-primary="<?= htmlspecialchars($colors['primary']) ?>"
                     data-accent="<?= htmlspecialchars($colors['accent']) ?>"
                     style="display:none;" 
                     <?= $currentColorScheme === $scheme ? 'checked' : '' ?> 
                     onchange="applyColorScheme(this)">
              <div style="display:flex; gap:6px; justify-content:center; margin-bottom:6px;">
                <div style="width:20px; height:20px; border-radius:4px; background:<?= htmlspecialchars($colors['primary']) ?>;"></div>
                <div style="width:20px; height:20px; border-radius:4px; background:<?= htmlspecialchars($colors['accent']) ?>;"></div>
              </div>
              <div style="font-size:12px; color:var(--text-primary); font-weight:500;"><?= htmlspecialchars($colors['label']) ?></div>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="padding-top:14px; border-top:0.5px solid var(--border);">
        <div style="font-size:12px; color:var(--text-secondary); margin-bottom:12px;">Or customize colors:</div>
        <div class="form-row">
          <div class="form-group">
            <label>Primary Color</label>
            <div style="display:flex; align-items:center; gap:8px;">
              <input type="color" name="primary_color" value="<?= htmlspecialchars($primaryColor ?? '#1e40af') ?>" style="width:50px; height:40px; border:0.5px solid var(--border); border-radius:var(--radius-md); cursor:pointer;">
              <input type="text" name="primary_color_hex" value="<?= htmlspecialchars($primaryColor ?? '#1e40af') ?>" placeholder="#1e40af" style="flex:1; font-size:12px; font-family:monospace;">
            </div>
          </div>
          <div class="form-group">
            <label>Accent Color</label>
            <div style="display:flex; align-items:center; gap:8px;">
              <input type="color" name="accent_color" value="<?= htmlspecialchars($accentColor ?? '#059669') ?>" style="width:50px; height:40px; border:0.5px solid var(--border); border-radius:var(--radius-md); cursor:pointer;">
              <input type="text" name="accent_color_hex" value="<?= htmlspecialchars($accentColor ?? '#059669') ?>" placeholder="#059669" style="flex:1; font-size:12px; font-family:monospace;">
            </div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top:12px;"><i class="ti ti-check"></i> Save Colors</button>
      </div>
    </form>
  </div>

  <!-- Live preview -->
  <div class="card" style="margin-top:14px;">
    <div class="settings-section-title">Preview</div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <!-- Mini dashboard mockup -->
      <div style="flex:1; min-width:200px; background:var(--bg-secondary); border-radius:var(--radius-md); padding:14px;">
        <div style="font-size:11px; color:var(--text-tertiary); margin-bottom:8px;">Dashboard preview</div>
        <div style="display:flex; gap:6px; margin-bottom:8px;">
          <?php foreach ([['var(--green)','Income'],['var(--red)','Expenses'],['var(--blue)','Savings']] as [$color,$label]): ?>
            <div style="flex:1; background:var(--bg-primary); border:0.5px solid var(--border); border-radius:var(--radius-md); padding:8px 6px; text-align:center;">
              <div style="font-size:10px; color:var(--text-tertiary);"><?= $label ?></div>
              <div style="font-size:14px; font-weight:600; color:<?= $color ?>;">$0</div>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="height:6px; background:var(--bg-primary); border-radius:3px; margin-bottom:4px;"><div style="width:65%; height:6px; border-radius:3px; background:var(--green);"></div></div>
        <div style="height:6px; background:var(--bg-primary); border-radius:3px;"><div style="width:40%; height:6px; border-radius:3px; background:var(--blue);"></div></div>
      </div>

      <div style="flex:1; min-width:180px; display:flex; flex-direction:column; gap:8px;">
        <div style="background:var(--bg-primary); border:0.5px solid var(--border); border-radius:var(--radius-md); padding:10px;">
          <div style="font-size:11px; color:var(--text-secondary);">Text primary</div>
          <div style="font-size:13px; color:var(--text-primary); font-weight:500;">Budjit</div>
        </div>
        <div style="display:flex; gap:6px;">
          <span class="pill pill-green">Income</span>
          <span class="pill pill-amber">Due</span>
          <span class="pill pill-red">Over</span>
        </div>
        <button class="btn btn-primary btn-sm">Primary button</button>
      </div>
    </div>
  </div>
</div>

<style>
.theme-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
.theme-option { cursor:pointer; border:0.5px solid var(--border); border-radius:var(--radius-lg); padding:14px; display:flex; flex-direction:column; align-items:center; transition:border-color 0.15s, background 0.15s; }
.theme-option:hover { background:var(--bg-secondary); }
.theme-selected { border-color:var(--green); background:var(--green-light); }
.theme-preview { width:60px; height:48px; border-radius:var(--radius-md); display:flex; align-items:center; justify-content:center; }
.theme-preview-light  { background:#ffffff; border:0.5px solid #ddd; color:#1a1a1a; }
.theme-preview-dark   { background:#1e1e1c; border:0.5px solid #444; color:#f0efe9; }
.theme-preview-system { background:linear-gradient(135deg,#ffffff 50%,#1e1e1c 50%); border:0.5px solid var(--border); color:var(--text-primary); }
</style>

<script>
// Apply predefined color scheme to custom color inputs
function applyColorScheme(radio) {
  const primary = radio.dataset.primary;
  const accent = radio.dataset.accent;
  
  // Update color pickers and hex inputs
  const primaryColorPicker = document.querySelector('input[name="primary_color"]');
  const primaryColorHex = document.querySelector('input[name="primary_color_hex"]');
  const accentColorPicker = document.querySelector('input[name="accent_color"]');
  const accentColorHex = document.querySelector('input[name="accent_color_hex"]');
  
  if (primaryColorPicker) primaryColorPicker.value = primary;
  if (primaryColorHex) primaryColorHex.value = primary;
  if (accentColorPicker) accentColorPicker.value = accent;
  if (accentColorHex) accentColorHex.value = accent;
  
  // Submit the form
  document.getElementById('color-form').submit();
}

// Apply theme and submit form
function applyAndSubmitTheme() {
  const themeForm = document.getElementById('theme-form');
  const selectedTheme = document.querySelector('input[name="theme"]:checked');
  
  if (selectedTheme) {
    const theme = selectedTheme.value;
    
    // Save to localStorage for instant effect
    localStorage.setItem('app_theme', theme);
    
    // Apply theme visually
    function applyTheme(t) {
      if (t === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
      } else if (t === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
      } else {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
          document.documentElement.setAttribute('data-theme', 'dark');
        } else {
          document.documentElement.removeAttribute('data-theme');
        }
      }
    }
    applyTheme(theme);
    
    // Update UI styling
    document.querySelectorAll('.theme-option').forEach(o => o.classList.remove('theme-selected'));
    selectedTheme.closest('.theme-option').classList.add('theme-selected');
    
    // Submit form to save to database
    themeForm.submit();
  }
}

// Sync color picker and hex inputs
const primaryColorPicker = document.querySelector('input[name="primary_color"]');
const primaryColorHex = document.querySelector('input[name="primary_color_hex"]');
const accentColorPicker = document.querySelector('input[name="accent_color"]');
const accentColorHex = document.querySelector('input[name="accent_color_hex"]');

if (primaryColorPicker && primaryColorHex) {
  primaryColorPicker.addEventListener('input', function() {
    primaryColorHex.value = this.value;
  });
  primaryColorHex.addEventListener('input', function() {
    if (/^#[0-9a-f]{6}$/i.test(this.value)) {
      primaryColorPicker.value = this.value;
    }
  });
}

if (accentColorPicker && accentColorHex) {
  accentColorPicker.addEventListener('input', function() {
    accentColorHex.value = this.value;
  });
  accentColorHex.addEventListener('input', function() {
    if (/^#[0-9a-f]{6}$/i.test(this.value)) {
      accentColorPicker.value = this.value;
    }
  });
}
</script>