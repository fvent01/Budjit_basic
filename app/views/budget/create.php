<?php $pageTitle = 'New Budget'; ?>
<div class="page-header">
  <div><h1 class="page-title">New budget</h1><p class="page-sub">Set your spending allocations for this period.</p></div>
  <a href="<?= BASE_URL ?>/budgets" class="btn">← Back</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="form-layout">
  <form method="POST" action="<?= BASE_URL ?>/budgets">
    <?= Auth::csrfField() ?>

    <div class="card form-card">
      <h2 class="form-section-title">Details</h2>

      <div class="form-group">
        <label for="title">Budget title</label>
        <input type="text" id="title" name="title" value="<?= htmlspecialchars($budget['title'] ?? ('Week of ' . date('M j', strtotime($defaults['start_date'] ?? 'now')))) ?>" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="period_type">Period type</label>
          <select id="period_type" name="period_type">
            <option value="weekly"  <?= ($budget['period_type'] ?? 'weekly') === 'weekly'  ? 'selected' : '' ?>>Weekly</option>
            <option value="monthly" <?= ($budget['period_type'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
          </select>
        </div>
        <div class="form-group">
          <label for="total_income">Expected income ($)</label>
          <input type="number" id="total_income" name="total_income" step="0.01" min="0"
                 value="<?= htmlspecialchars($budget['total_income'] ?? '0') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="start_date">Start date</label>
          <input type="date" id="start_date" name="start_date"
                 value="<?= htmlspecialchars($budget['start_date'] ?? $defaults['start_date'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="end_date">End date</label>
          <input type="date" id="end_date" name="end_date"
                 value="<?= htmlspecialchars($budget['end_date'] ?? $defaults['end_date'] ?? '') ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label for="notes">Notes <span class="label-hint">(optional)</span></label>
        <textarea id="notes" name="notes" rows="2"><?= htmlspecialchars($budget['notes'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="card form-card">
      <h2 class="form-section-title">Category allocations</h2>
      <p class="form-hint">Set how much you plan to spend in each category. Leave at $0 to exclude.</p>

      <div class="allocations-grid">
        <?php
          $existingItems = [];
          if (!empty($budget['items'])) {
              foreach ($budget['items'] as $item) {
                  $existingItems[$item['category_id']] = $item['allocated'];
              }
          }
        ?>
        <?php foreach ($categories as $cat): ?>
          <div class="allocation-row">
            <div class="alloc-cat">
              <i class="ti <?= htmlspecialchars($cat['icon']) ?>" style="color:<?= htmlspecialchars($cat['color']) ?>; font-size:18px;"></i>
              <label for="alloc_<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></label>
            </div>
            <div class="alloc-input-wrap">
              <span class="alloc-currency">$</span>
              <input type="number" id="alloc_<?= $cat['id'] ?>" name="allocations[<?= $cat['id'] ?>]"
                     step="0.01" min="0" value="<?= htmlspecialchars($existingItems[$cat['id']] ?? '0') ?>"
                     class="alloc-input">
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="alloc-total-row">
        <span>Total allocated:</span>
        <strong id="alloc-total">$0.00</strong>
      </div>
    </div>

    <div class="form-actions">
      <a href="<?= BASE_URL ?>/budgets" class="btn">Cancel</a>
      <button type="submit" class="btn btn-primary">Save budget</button>
    </div>
  </form>
</div>

<script>
(function() {
  const inputs = document.querySelectorAll('.alloc-input');
  const totalEl = document.getElementById('alloc-total');
  function recalc() {
    let sum = 0;
    inputs.forEach(i => sum += parseFloat(i.value) || 0);
    totalEl.textContent = '$' + sum.toFixed(2);
  }
  inputs.forEach(i => i.addEventListener('input', recalc));
  recalc();
})();
</script>
