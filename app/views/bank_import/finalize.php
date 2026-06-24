<?php $pageTitle = 'Finalize Import'; ?>
<div class="page-header">
  <div><h1 class="page-title">Finalize Import</h1><p class="page-sub">Review and confirm transactions before finalizing.</p></div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="ti ti-checkbox" style="color:var(--green); margin-right:5px;"></i>Import Summary</span>
  </div>
  
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px;">
    <div style="padding: 12px; background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 3px solid var(--blue);">
      <div style="font-size: 11px; font-weight: 500; color: var(--text-secondary);">Total Rows</div>
      <div style="font-size: 24px; font-weight: 600; color: var(--text-primary);"><?= $totalRows ?? 0 ?></div>
    </div>
    <div style="padding: 12px; background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 3px solid var(--green);">
      <div style="font-size: 11px; font-weight: 500; color: var(--text-secondary);">Ready to Import</div>
      <div style="font-size: 24px; font-weight: 600; color: var(--text-primary);"><?= $readyCount ?? 0 ?></div>
    </div>
    <div style="padding: 12px; background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 3px solid var(--orange);">
      <div style="font-size: 11px; font-weight: 500; color: var(--text-secondary);">Needs Review</div>
      <div style="font-size: 24px; font-weight: 600; color: var(--text-primary);"><?= $needsReviewCount ?? 0 ?></div>
    </div>
    <div style="padding: 12px; background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 3px solid var(--red);">
      <div style="font-size: 11px; font-weight: 500; color: var(--text-secondary);">Duplicates/Errors</div>
      <div style="font-size: 24px; font-weight: 600; color: var(--text-primary);"><?= $errorCount ?? 0 ?></div>
    </div>
  </div>

  <!-- Detailed transaction preview -->
  <?php if (!empty($transactions)): ?>
    <div style="margin-top: 20px;">
      <h3 style="font-size: 14px; font-weight: 600; margin-bottom: 12px;">Transaction Details</h3>
      <div style="overflow-x: auto;">
        <table class="data-table" style="font-size: 12px;">
          <thead>
            <tr>
              <th style="width: 30px;"><input type="checkbox" id="select-all" /></th>
              <th>Date</th>
              <th>Description</th>
              <th style="text-align: right;">Amount</th>
              <th>Category</th>
              <th>Status</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($transactions as $t): ?>
              <tr>
                <td><input type="checkbox" class="transaction-check" value="<?= $t['id'] ?>" /></td>
                <td><?= date('M j, Y', strtotime($t['date'])) ?></td>
                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($t['description']) ?>">
                  <?= htmlspecialchars(substr($t['description'], 0, 50)) ?>
                </td>
                <td style="text-align: right; font-weight: 500; color: <?= $t['amount'] < 0 ? 'var(--red)' : 'var(--green)' ?>;">
                  <?= number_format($t['amount'], 2) ?>
                </td>
                <td>
                  <select name="category[<?= $t['id'] ?>]" style="padding: 4px 8px; border-radius: var(--radius-sm); border: 1px solid var(--border); font-size: 11px;">
                    <option value="">— Select —</option>
                    <?php foreach ($categories ?? [] as $cat): ?>
                      <option value="<?= $cat['id'] ?>" <?= ($t['category_id'] ?? '') === $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <span class="pill <?= 
                    $t['status'] === 'ready' ? 'pill-green' : 
                    ($t['status'] === 'duplicate' ? 'pill-red' : 'pill-amber') 
                  ?>">
                    <?= ucfirst($t['status']) ?>
                  </span>
                </td>
                <td style="font-size: 11px; color: var(--text-secondary);">
                  <?= htmlspecialchars($t['notes'] ?? '—') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <!-- Warnings/Alerts -->
  <?php if ($errorCount > 0): ?>
    <div style="margin-top: 20px;">
      <div class="alert alert-warning" style="font-size: 12px;">
        <strong><?= $errorCount ?> transaction(s) need attention</strong>
        <p style="margin-top: 8px; margin-bottom: 0; font-size: 11px;">
          Duplicates and errors are highlighted above. You can deselect them to skip importing, or fix categorization issues.
        </p>
      </div>
    </div>
  <?php endif; ?>

  <!-- Action buttons -->
  <div style="display: flex; gap: 8px; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
    <a href="<?= BASE_URL ?>/bank-import/review/<?= $sessionId ?>" class="btn" style="flex: 1;">
      <i class="ti ti-arrow-left"></i> Back to Review
    </a>
    <form method="POST" action="<?= BASE_URL ?>/bank-import/finalize/<?= $sessionId ?>" style="flex: 1;">
      <?= Auth::csrfField() ?>
      <input type="hidden" id="selected-ids" name="selected_ids" value="">
      <button type="submit" class="btn btn-primary" style="width: 100%;">
        <i class="ti ti-check"></i> Confirm & Import
      </button>
    </form>
  </div>
</div>

<script>
document.getElementById('select-all').addEventListener('change', function() {
  document.querySelectorAll('.transaction-check').forEach(cb => {
    cb.checked = this.checked;
  });
  updateSelectedIds();
});

document.querySelectorAll('.transaction-check').forEach(cb => {
  cb.addEventListener('change', updateSelectedIds);
});

function updateSelectedIds() {
  const selected = Array.from(document.querySelectorAll('.transaction-check:checked'))
    .map(cb => cb.value)
    .join(',');
  document.getElementById('selected-ids').value = selected;
}

// Initial update
updateSelectedIds();
</script>
