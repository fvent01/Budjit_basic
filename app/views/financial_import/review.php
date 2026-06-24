<?php $pageTitle = 'Review Transactions'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Review Transactions</h1>
    <p class="page-sub">
      <?= number_format($total) ?> transaction(s) pending — assign categories and confirm.
    </p>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="<?= BASE_URL ?>/import" class="btn btn-sm"><i class="ti ti-arrow-left"></i> Back</a>
    <?php if ($total > 0): ?>
      <form method="POST" action="<?= BASE_URL ?>/import/skip-all"
            onsubmit="return confirm('Skip all <?= $total ?> pending transactions?')">
        <?= Auth::csrfField() ?>
        <button type="submit" class="btn btn-sm text-red">
          <i class="ti ti-x"></i> Skip all
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if (empty($byDate)): ?>
  <div class="empty-state">
    <i class="ti ti-check" style="font-size:40px;color:var(--green);"></i>
    <p>All caught up — no transactions pending review.</p>
    <a href="<?= BASE_URL ?>/import" class="btn btn-primary">Back to Import</a>
  </div>
<?php else: ?>

<form method="POST" action="<?= BASE_URL ?>/import/confirm" id="review-form">
  <?= Auth::csrfField() ?>

  <!-- Default budget selector -->
  <?php if (!empty($budgets)): ?>
    <div class="card" style="margin-bottom:12px;padding:10px 14px;">
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <label style="font-size:12px;font-weight:500;white-space:nowrap;">Default budget:</label>
        <select name="default_budget_id" style="font-size:12px;padding:4px 8px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--bg-primary);color:var(--text-primary);">
          <option value="">— none —</option>
          <?php foreach ($budgets as $b): ?>
            <option value="<?= $b['id'] ?>" <?= isset($current['id']) && $current['id'] == $b['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($b['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span style="font-size:11px;color:var(--text-tertiary);">Applied to rows without a budget selected.</span>
      </div>
    </div>
  <?php endif; ?>

  <?php foreach ($byDate as $date => $txns): ?>
    <div style="margin-bottom:4px;">
      <div style="font-size:11px;font-weight:600;color:var(--text-tertiary);text-transform:uppercase;letter-spacing:.05em;padding:6px 0 4px;">
        <?= date('l, F j, Y', strtotime($date)) ?>
      </div>

      <?php foreach ($txns as $txn): ?>
        <?php
        $txnId  = $txn['id'];
        $source = $txn['source'] ?? 'unknown';
        $sourceColors = ['plaid' => '#7F77DD', 'csv' => 'var(--green)', 'excel' => '#185FA5'];
        $sourceColor  = $sourceColors[$source] ?? 'var(--text-secondary)';
        ?>
        <div class="card" style="margin-bottom:8px;padding:10px 14px;" data-txn-id="<?= $txnId ?>">
          <div style="display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap;">

            <!-- Checkbox -->
            <div style="padding-top:2px;">
              <input type="checkbox" name="txn_ids[]" value="<?= $txnId ?>" checked
                     style="width:15px;height:15px;cursor:pointer;" class="txn-checkbox">
            </div>

            <!-- Transaction info -->
            <div style="flex:1;min-width:180px;">
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <span style="font-size:13px;font-weight:500;"><?= htmlspecialchars($txn['name']) ?></span>
                <?php if ($txn['merchant'] && $txn['merchant'] !== $txn['name']): ?>
                  <span style="font-size:11px;color:var(--text-tertiary);"><?= htmlspecialchars($txn['merchant']) ?></span>
                <?php endif; ?>
                <span class="pill" style="background:<?= $sourceColor ?>22;color:<?= $sourceColor ?>;border:1px solid <?= $sourceColor ?>44;font-size:10px;">
                  <?= strtoupper($source) ?>
                </span>
                <?php if ($txn['pending']): ?>
                  <span class="pill" style="background:var(--yellow-light);color:var(--yellow-dark);font-size:10px;">PENDING</span>
                <?php endif; ?>
              </div>
              <?php if (!empty($txn['institution_name'])): ?>
                <div style="font-size:11px;color:var(--text-tertiary);margin-top:2px;">
                  <?= htmlspecialchars($txn['institution_name']) ?>
                  <?php if (!empty($txn['account_mask'])): ?>
                    ····<?= htmlspecialchars($txn['account_mask']) ?>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <?php if (!empty($txn['category'])): ?>
                <div style="font-size:10px;color:var(--text-tertiary);">
                  <i class="ti ti-tag"></i> <?= htmlspecialchars($txn['category']) ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Amount -->
            <div style="text-align:right;min-width:80px;">
              <div style="font-size:15px;font-weight:600;color:<?= $txn['mapped_as'] === 'income' ? 'var(--green)' : 'var(--text-primary)' ?>;">
                <?= $txn['mapped_as'] === 'income' ? '+' : '-' ?>$<?= number_format($txn['amount'], 2) ?>
              </div>
            </div>

            <!-- Type (income/expense/skip) -->
            <div style="min-width:110px;">
              <select name="mapped_as[<?= $txnId ?>]" class="mapped-as-select"
                      style="font-size:12px;padding:4px 6px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--bg-primary);color:var(--text-primary);width:100%;"
                      data-txn-id="<?= $txnId ?>">
                <option value="expense" <?= $txn['mapped_as'] === 'expense' ? 'selected' : '' ?>>Expense</option>
                <option value="income"  <?= $txn['mapped_as'] === 'income'  ? 'selected' : '' ?>>Income</option>
                <option value="skip">Skip</option>
              </select>
            </div>

            <!-- Category (only for expenses) -->
            <div style="min-width:160px;" class="category-col" data-txn-id="<?= $txnId ?>"
                 <?= $txn['mapped_as'] === 'income' ? 'style="min-width:160px;opacity:.35;pointer-events:none;"' : 'style="min-width:160px;"' ?>>
              <select name="category_id[<?= $txnId ?>]"
                      style="font-size:12px;padding:4px 6px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--bg-primary);color:var(--text-primary);width:100%;">
                <option value="">— category —</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>"
                          <?= (int)($txn['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Budget (optional) -->
            <?php if (!empty($budgets)): ?>
              <div style="min-width:130px;">
                <select name="budget_id[<?= $txnId ?>]"
                        style="font-size:12px;padding:4px 6px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--bg-primary);color:var(--text-primary);width:100%;">
                  <option value="">— budget —</option>
                  <?php foreach ($budgets as $b): ?>
                    <option value="<?= $b['id'] ?>"
                            <?= isset($current['id']) && $current['id'] == $b['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($b['title']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>

          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
    <div style="display:flex;justify-content:center;gap:6px;margin:16px 0;">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a href="?page=<?= $p ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>

  <!-- Submit bar -->
  <div style="position:sticky;bottom:0;background:var(--bg-primary);border-top:1px solid var(--border);padding:12px 0;display:flex;gap:10px;align-items:center;z-index:50;">
    <button type="submit" class="btn btn-primary" id="confirm-btn">
      <i class="ti ti-check"></i> Confirm selected
    </button>
    <span style="font-size:12px;color:var(--text-tertiary);" id="selected-count">
      <?= $total ?> selected
    </span>
  </div>
</form>

<script>
(function() {
  const checkboxes  = document.querySelectorAll('.txn-checkbox');
  const countEl     = document.getElementById('selected-count');
  const confirmBtn  = document.getElementById('confirm-btn');

  function updateCount() {
    const n = document.querySelectorAll('.txn-checkbox:checked').length;
    if (countEl) countEl.textContent = n + ' selected';
  }

  checkboxes.forEach(cb => cb.addEventListener('change', updateCount));

  // mapped_as changes: dim/enable category selector
  document.querySelectorAll('.mapped-as-select').forEach(sel => {
    sel.addEventListener('change', function() {
      const txnId  = this.dataset.txnId;
      const catCol = document.querySelector('.category-col[data-txn-id="' + txnId + '"]');
      if (!catCol) return;
      const isSkip   = this.value === 'skip';
      const isIncome = this.value === 'income';
      catCol.style.opacity         = (isSkip || isIncome) ? '.35' : '1';
      catCol.style.pointerEvents   = (isSkip || isIncome) ? 'none' : '';
      // Also uncheck the row's checkbox if skipped
      if (isSkip) {
        const card = this.closest('[data-txn-id]');
        const cb = card ? card.querySelector('.txn-checkbox') : null;
        if (cb) cb.checked = false;
        updateCount();
      }
    });
  });

  // Prevent double-submit
  document.getElementById('review-form')?.addEventListener('submit', () => {
    if (confirmBtn) {
      confirmBtn.disabled = true;
      confirmBtn.innerHTML = '<i class="ti ti-loader"></i> Saving…';
    }
  });

  updateCount();
})();
</script>
<?php endif; ?>
