<?php $pageTitle = 'Review Import'; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Review Import</h1>
    <p class="page-sub"><?= htmlspecialchars($session['filename']) ?> · <?= number_format($session['row_count']) ?> rows parsed</p>
  </div>
  <a href="<?= BASE_URL ?>/excel-import" class="btn">← Cancel</a>
</div>

<!-- Summary strip -->
<?php if (!empty($summary)): ?>
  <div class="metrics-grid" style="margin-bottom:16px;">
    <div class="metric-card">
      <div class="metric-label">Date range</div>
      <div class="metric-value" style="font-size:16px;"><?= date('M j', strtotime($summary['date_from'])) ?> – <?= date('M j, Y', strtotime($summary['date_to'])) ?></div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Total income</div>
      <div class="metric-value text-green">$<?= number_format($summary['total_income'], 2) ?></div>
      <div style="font-size:11px; color:var(--text-tertiary);"><?= number_format($summary['income_count']) ?> entries</div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Total expenses</div>
      <div class="metric-value">$<?= number_format($summary['total_expenses'], 2) ?></div>
      <div style="font-size:11px; color:var(--text-tertiary);"><?= number_format($summary['expense_count']) ?> entries</div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Net</div>
      <?php $net = $summary['total_income'] - $summary['total_expenses']; ?>
      <div class="metric-value <?= $net >= 0 ? 'text-green' : 'text-red' ?>">$<?= number_format(abs($net), 2) ?><?= $net < 0 ? ' deficit' : '' ?></div>
    </div>
  </div>
<?php endif; ?>

<!-- Filter tabs -->
<div style="display:flex; gap:6px; margin-bottom:14px;">
  <?php foreach (['all' => 'All rows', 'income' => 'Income only', 'expense' => 'Expenses only'] as $val => $label): ?>
    <a href="?filter=<?= $val ?>"
       class="btn btn-sm <?= $filter === $val ? 'btn-primary' : '' ?>">
      <?= $label ?>
    </a>
  <?php endforeach; ?>
  <span style="margin-left:auto; font-size:12px; color:var(--text-secondary); align-self:center;">
    Showing <?= count($rows) ?> rows
  </span>
</div>

<?php if (empty($rows)): ?>
  <div class="empty-card"><p>No rows to display for this filter.</p></div>
<?php else: ?>

<form method="POST" action="<?= BASE_URL ?>/excel-import/confirm/<?= $session['id'] ?>">
  <?= Auth::csrfField() ?>

  <!-- Default budget for all rows -->
  <div class="card" style="margin-bottom:12px; padding:12px 16px;">
    <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
      <span style="font-size:13px; font-weight:500; color:var(--text-primary);">Apply budget to all rows:</span>
      <select name="default_budget_id" style="font-size:13px; padding:5px 8px; flex:1; max-width:320px;">
        <option value="">— No budget —</option>
        <?php foreach ($budgets as $b): ?>
          <option value="<?= $b['id'] ?>" <?= ($current && $current['id'] == $b['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($b['title']) ?> (<?= date('M j', strtotime($b['start_date'])) ?> – <?= date('M j', strtotime($b['end_date'])) ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <span style="font-size:11px; color:var(--text-tertiary);">You can override per-row below.</span>
    </div>
  </div>

  <div class="card">
    <table class="data-table" style="font-size:12px;">
      <thead>
        <tr>
          <th style="width:90px;">Date</th>
          <th>Description</th>
          <th>Sheet</th>
          <th style="width:80px;">Amount</th>
          <th style="width:80px;">Type</th>
          <th>Category</th>
          <th>Budget</th>
          <th style="width:70px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
        // Group rows by week for readability
        $currentWeek = null;
        foreach ($rows as $row):
          $rowWeek = date('Y-W', strtotime($row['week_date']));
          if ($rowWeek !== $currentWeek):
            $currentWeek = $rowWeek;
        ?>
          <tr>
            <td colspan="8" style="background:var(--bg-secondary); font-size:11px; font-weight:600; color:var(--text-secondary); padding:6px 8px;">
              Week of <?= date('M j, Y', strtotime($row['week_date'])) ?>
            </td>
          </tr>
        <?php endif; ?>
          <tr>
            <td style="color:var(--text-tertiary);"><?= date('M j', strtotime($row['week_date'])) ?></td>
            <td>
              <span style="display:flex; align-items:center; gap:6px;">
                <?php if ($row['category_id'] && $row['color']): ?>
                  <span style="width:8px; height:8px; border-radius:2px; background:<?= htmlspecialchars($row['color']) ?>; flex-shrink:0;"></span>
                <?php endif; ?>
                <?= htmlspecialchars($row['description']) ?>
              </span>
            </td>
            <td style="font-size:10px; color:var(--text-tertiary);">
              <?= str_contains($row['sheet_source'], 'Household') ? 'Household' : 'Personal' ?>
            </td>
            <td style="font-weight:500; color:<?= $row['record_type'] === 'income' ? 'var(--green-dark)' : 'var(--text-primary)' ?>">
              <?= $row['record_type'] === 'income' ? '+' : '' ?>$<?= number_format($row['amount'], 2) ?>
            </td>
            <td>
              <select name="mapped_as[<?= $row['id'] ?>]" style="font-size:11px; padding:3px 5px; width:72px;">
                <option value="import" <?= $row['mapped_as'] === 'import' ? 'selected' : '' ?>>Import</option>
                <option value="skip"   <?= $row['mapped_as'] === 'skip'   ? 'selected' : '' ?>>Skip</option>
              </select>
            </td>
            <td>
              <?php if ($row['record_type'] === 'expense'): ?>
                <select name="category_id[<?= $row['id'] ?>]" style="font-size:11px; padding:3px 5px;">
                  <option value="">— None —</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $row['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($cat['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php else: ?>
                <span style="font-size:11px; color:var(--text-tertiary);">Income</span>
              <?php endif; ?>
            </td>
            <td>
              <select name="budget_id[<?= $row['id'] ?>]" style="font-size:11px; padding:3px 5px;">
                <option value="">— Default —</option>
                <?php foreach ($budgets as $b): ?>
                  <option value="<?= $b['id'] ?>" <?= ($row['budget_id'] == $b['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars(mb_strimwidth($b['title'], 0, 22, '…')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <!-- inline skip toggle for quick action -->
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="form-actions" style="margin-top:14px;">
    <a href="<?= BASE_URL ?>/excel-import" class="btn">Cancel</a>
    <button type="submit" class="btn btn-primary" onclick="return confirm('Import all selected rows into Budjit?')">
      <i class="ti ti-check"></i> Confirm & Import
    </button>
  </div>
</form>

<?php endif; ?>

<style>
/* Sticky table header */
.data-table thead th { position:sticky; top:0; background:var(--bg-primary); z-index:2; }
</style>
