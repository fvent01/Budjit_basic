<?php $pageTitle = 'New Savings Goal'; ?>
<div class="page-header">
  <div><h1 class="page-title">New savings goal</h1></div>
  <a href="<?= BASE_URL ?>/savings-goals" class="btn">← Back</a>
</div>
<?php if (!empty($errors)): ?><div class="alert alert-error"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="form-layout form-layout-narrow">
  <form method="POST" action="<?= BASE_URL ?>/savings-goals">
    <?= Auth::csrfField() ?>
    <div class="card form-card">
      <div class="form-group">
        <label>Goal name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($goal['name'] ?? '') ?>" placeholder="e.g. Emergency Fund" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Target amount ($)</label>
          <input type="number" name="target_amount" step="0.01" min="0.01" value="<?= htmlspecialchars($goal['target_amount'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Target date <span class="label-hint">(optional)</span></label>
          <input type="date" name="target_date" value="<?= htmlspecialchars($goal['target_date'] ?? '') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Icon class <span class="label-hint">(Tabler icon)</span></label>
          <input type="text" name="icon" value="<?= htmlspecialchars($goal['icon'] ?? 'ti-piggy-bank') ?>" placeholder="ti-piggy-bank">
        </div>
        <div class="form-group">
          <label>Color</label>
          <input type="color" name="color" value="<?= htmlspecialchars($goal['color'] ?? '#1D9E75') ?>" style="height:38px; cursor:pointer;">
        </div>
      </div>
      <div class="form-group form-check">
        <label><input type="checkbox" name="auto_allocate" value="1" id="auto_toggle" <?= !empty($goal['auto_allocate']) ? 'checked' : '' ?>> Auto-allocate % of weekly income</label>
      </div>
      <div class="form-group" id="auto_pct_row" style="<?= empty($goal['auto_allocate']) ? 'display:none' : '' ?>">
        <label>Percentage of income to allocate (%)</label>
        <input type="number" name="auto_percent" step="0.1" min="0.1" max="100" value="<?= htmlspecialchars($goal['auto_percent'] ?? '5') ?>">
      </div>
      <div class="form-group">
        <label>Notes <span class="label-hint">(optional)</span></label>
        <textarea name="notes" rows="2"><?= htmlspecialchars($goal['notes'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="form-actions">
      <a href="<?= BASE_URL ?>/savings-goals" class="btn">Cancel</a>
      <button type="submit" class="btn btn-primary">Save goal</button>
    </div>
  </form>
</div>
<script>
document.getElementById('auto_toggle').addEventListener('change', function() {
  document.getElementById('auto_pct_row').style.display = this.checked ? '' : 'none';
});
</script>
