<?php $pageTitle = 'Edit Goal'; ?>
<div class="page-header">
  <div><h1 class="page-title">Edit goal</h1><p class="page-sub"><?= htmlspecialchars($goal['name']) ?></p></div>
  <a href="<?= BASE_URL ?>/savings-goals" class="btn">← Back</a>
</div>
<?php if (!empty($errors)): ?><div class="alert alert-error"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="form-layout form-layout-narrow">
  <form method="POST" action="<?= BASE_URL ?>/savings-goals/<?= $goal['id'] ?>/update">
    <?= Auth::csrfField() ?>
    <div class="card form-card">
      <div class="form-group">
        <label>Goal name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($goal['name']) ?>" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Target amount ($)</label>
          <input type="number" name="target_amount" step="0.01" min="0.01" value="<?= htmlspecialchars($goal['target_amount']) ?>" required>
        </div>
        <div class="form-group">
          <label>Target date</label>
          <input type="date" name="target_date" value="<?= htmlspecialchars($goal['target_date'] ?? '') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Icon class</label>
          <input type="text" name="icon" value="<?= htmlspecialchars($goal['icon']) ?>">
        </div>
        <div class="form-group">
          <label>Color</label>
          <input type="color" name="color" value="<?= htmlspecialchars($goal['color']) ?>" style="height:38px; cursor:pointer;">
        </div>
      </div>
      <div class="form-group form-check">
        <label><input type="checkbox" name="auto_allocate" value="1" id="auto_toggle" <?= $goal['auto_allocate'] ? 'checked' : '' ?>> Auto-allocate % of income</label>
      </div>
      <div class="form-group" id="auto_pct_row" style="<?= !$goal['auto_allocate'] ? 'display:none' : '' ?>">
        <label>Percentage (%)</label>
        <input type="number" name="auto_percent" step="0.1" min="0.1" max="100" value="<?= htmlspecialchars($goal['auto_percent']) ?>">
      </div>
      <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" rows="2"><?= htmlspecialchars($goal['notes'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="form-actions">
      <form method="POST" action="<?= BASE_URL ?>/savings-goals/<?= $goal['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Delete this goal?')">
        <?= Auth::csrfField() ?><button type="submit" class="btn btn-danger-outline">Delete</button>
      </form>
      <a href="<?= BASE_URL ?>/savings-goals" class="btn">Cancel</a>
      <button type="submit" class="btn btn-primary">Update goal</button>
    </div>
  </form>
</div>
<script>
document.getElementById('auto_toggle').addEventListener('change', function() {
  document.getElementById('auto_pct_row').style.display = this.checked ? '' : 'none';
});
</script>
