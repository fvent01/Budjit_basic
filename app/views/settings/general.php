<?php $pageTitle = 'General Settings'; ?>
<?php require APP_PATH . '/views/settings/_tabs.php'; ?>

<div class="settings-wrap">
  <form method="POST" action="<?= BASE_URL ?>/settings/general">
    <?= Auth::csrfField() ?>

    <!-- App -->
    <div class="card" style="margin-bottom:14px;">
      <div class="settings-section-title">Application</div>

      <div class="form-group">
        <label>App name</label>
        <input type="text" name="app_name" value="<?= htmlspecialchars($global['app_name'] ?? 'Budjit') ?>" required>
        <div style="font-size:11px; color:var(--text-tertiary); margin-top:4px;">Appears in the browser tab and sidebar.</div>
      </div>

      <div class="form-group">
        <label>App slogan</label>
        <input type="text" name="app_slogan" value="<?= htmlspecialchars($global['app_slogan'] ?? 'Family Plan') ?>" placeholder="e.g., Family Plan">
        <div style="font-size:11px; color:var(--text-tertiary); margin-top:4px;">Subtitle shown below the app name.</div>
      </div>

      <div class="form-group">
        <label>Sidebar footer text</label>
        <textarea name="sidebar_footer_text" rows="2" placeholder="Enter a small footer message for the sidebar."><?= htmlspecialchars($global['sidebar_footer_text'] ?? '') ?></textarea>
        <div style="font-size:11px; color:var(--text-tertiary); margin-top:4px;">This text appears below your user info and logout link in the left sidebar.</div>
      </div>

      <div class="form-group">
        <label>App icon</label>
        <div style="display:flex; gap:10px; align-items:center;">
          <div style="display:flex; align-items:center; justify-content:center; width:48px; height:48px; border:0.5px solid var(--border); border-radius:var(--radius-md); background:var(--bg-secondary);">
            <i class="ti <?= htmlspecialchars($currentIcon) ?>" style="font-size:22px;"></i>
          </div>
          <select name="app_icon" style="flex:1; min-width:0;">
            <?php
            $icons = [
              'ti-home-dollar' => 'Home Dollar',
              'ti-wallet' => 'Wallet',
              'ti-piggy-bank' => 'Piggy Bank',
              'ti-coin' => 'Coin',
              'ti-chart-bar' => 'Chart Bar',
              'ti-calculator' => 'Calculator',
              'ti-briefcase' => 'Briefcase',
              'ti-cash' => 'Cash',
              'ti-money' => 'Money',
              'ti-vault' => 'Vault'
            ];
            $currentIcon = $global['app_icon'] ?? 'ti-home-dollar';
            ?>
            <?php foreach ($icons as $value => $label): ?>
              <option value="<?= $value ?>" <?= $currentIcon === $value ? 'selected' : '' ?>>
                <?= $label ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="font-size:11px; color:var(--text-tertiary); margin-top:4px;">Select an icon from the dropdown. Icons from <a href="https://tabler-icons.io/" target="_blank" style="color:var(--link);">Tabler Icons</a>.</div>
      </div>

      <script>
      // Update icon preview when dropdown changes
      document.querySelector('select[name="app_icon"]').addEventListener('change', function() {
        const preview = this.closest('.form-group').querySelector('i');
        preview.className = 'ti ' + this.value;
      });
      </script>

      <div class="form-row">
        <div class="form-group">
          <label>Timezone</label>
          <select name="timezone">
            <?php foreach ($timezones as $tz => $label): ?>
              <option value="<?= $tz ?>" <?= ($global['timezone'] ?? '') === $tz ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Date format</label>
          <select name="date_format">
            <?php foreach ($dateFormats as $fmt => $preview): ?>
              <option value="<?= htmlspecialchars($fmt) ?>" <?= ($global['date_format'] ?? 'M j, Y') === $fmt ? 'selected' : '' ?>>
                <?= htmlspecialchars($preview) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- Currency -->
    <div class="card" style="margin-bottom:14px;">
      <div class="settings-section-title">Currency</div>

      <div class="form-row">
        <div class="form-group">
          <label>Currency</label>
          <select name="currency" id="currency-select">
            <?php foreach ($currencies as $code => $info): ?>
              <option value="<?= $code ?>" data-symbol="<?= $info['symbol'] ?>"
                <?= ($global['currency'] ?? 'USD') === $code ? 'selected' : '' ?>>
                <?= $code ?> — <?= $info['name'] ?> (<?= $info['symbol'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Symbol position</label>
          <select name="currency_position">
            <option value="before" <?= ($global['currency_position'] ?? 'before') === 'before' ? 'selected' : '' ?>>Before amount ($100)</option>
            <option value="after"  <?= ($global['currency_position'] ?? 'before') === 'after'  ? 'selected' : '' ?>>After amount (100$)</option>
          </select>
        </div>
      </div>

      <!-- Live preview -->
      <div style="background:var(--bg-secondary); border-radius:var(--radius-md); padding:10px 14px; font-size:13px; color:var(--text-secondary);">
        Preview: <strong id="currency-preview" style="color:var(--text-primary);">$1,234.56</strong>
      </div>
    </div>

    <!-- Budget calendar -->
    <div class="card" style="margin-bottom:14px;">
      <div class="settings-section-title">Budget Calendar</div>

      <div class="form-row">
        <div class="form-group">
          <label>Week starts on</label>
          <select name="week_start_day">
            <?php foreach (['monday'=>'Monday','sunday'=>'Sunday','saturday'=>'Saturday'] as $val => $label): ?>
              <option value="<?= $val ?>" <?= ($global['week_start_day'] ?? 'monday') === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <!-- empty for grid alignment -->
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Fiscal year start month</label>
          <select name="fiscal_year_month" id="fy-month">
            <?php
              $fyParts     = explode('-', $global['fiscal_year_start'] ?? '01-01');
              $currentMonth= $fyParts[0] ?? '01';
              $currentDay  = $fyParts[1] ?? '01';
            ?>
            <?php foreach ($months as $num => $name): ?>
              <option value="<?= $num ?>" <?= $currentMonth === $num ? 'selected' : '' ?>><?= $name ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Fiscal year start day</label>
          <select name="fiscal_year_day">
            <?php for ($d = 1; $d <= 28; $d++): ?>
              <option value="<?= $d ?>" <?= (int)$currentDay === $d ? 'selected' : '' ?>><?= $d ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>

      <div style="font-size:11px; color:var(--text-tertiary);">
        Used for annual reports and year-to-date calculations.
        Current: <strong><?= date('F j', mktime(0,0,0,(int)$currentMonth,(int)$currentDay)) ?></strong>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Save settings</button>
    </div>
  </form>
</div>

<script>
// Live currency preview
(function() {
  const sel     = document.getElementById('currency-select');
  const preview = document.getElementById('currency-preview');
  const posSel  = document.querySelector('select[name="currency_position"]');

  function update() {
    const opt    = sel.options[sel.selectedIndex];
    const symbol = opt.dataset.symbol || '$';
    const pos    = posSel.value;
    preview.textContent = pos === 'before' ? symbol + '1,234.56' : '1,234.56' + symbol;
  }

  sel.addEventListener('change', update);
  posSel.addEventListener('change', update);
  update();
})();
</script>