<?php $pageTitle = 'Import Results'; ?>
<div class="page-header">
  <div><h1 class="page-title">Import Complete</h1><p class="page-sub">Your transactions have been successfully imported.</p></div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="ti ti-circle-check" style="color:var(--green); margin-right:5px;"></i>Import Summary</span>
  </div>

  <!-- Success metrics -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 20px;">
    <div style="padding: 16px; background: linear-gradient(135deg, var(--green-light) 0%, var(--green-lighter) 100%); border-radius: var(--radius-md); border-left: 4px solid var(--green);">
      <div style="font-size: 11px; font-weight: 600; color: var(--green-dark); text-transform: uppercase; letter-spacing: 0.5px;">Successfully Imported</div>
      <div style="font-size: 32px; font-weight: 700; color: var(--green-dark); margin-top: 4px;"><?= $importedCount ?? 0 ?></div>
      <div style="font-size: 10px; color: var(--green-dark); opacity: 0.8; margin-top: 4px;">Transactions</div>
    </div>
    <div style="padding: 16px; background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 4px solid var(--blue);">
      <div style="font-size: 11px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Total Amount</div>
      <div style="font-size: 28px; font-weight: 700; color: var(--text-primary); margin-top: 4px;"><?= '$' . number_format($totalAmount ?? 0, 2) ?></div>
      <div style="font-size: 10px; color: var(--text-tertiary); margin-top: 4px;">Across all accounts</div>
    </div>
    <div style="padding: 16px; background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 4px solid var(--orange);">
      <div style="font-size: 11px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Skipped/Errors</div>
      <div style="font-size: 28px; font-weight: 700; color: var(--text-primary); margin-top: 4px;"><?= $skippedCount ?? 0 ?></div>
      <div style="font-size: 10px; color: var(--text-tertiary); margin-top: 4px;">Duplicates & conflicts</div>
    </div>
    <div style="padding: 16px; background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 4px solid var(--purple);">
      <div style="font-size: 11px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Processing Time</div>
      <div style="font-size: 28px; font-weight: 700; color: var(--text-primary); margin-top: 4px;"><?= $processingTime ?? '—' ?></div>
      <div style="font-size: 10px; color: var(--text-tertiary); margin-top: 4px;">Seconds</div>
    </div>
  </div>
</div>

<!-- Breakdown by account (if Plaid) -->
<?php if (!empty($accountBreakdown)): ?>
  <div class="card" style="margin-top: 16px;">
    <div class="card-header">
      <span class="card-title"><i class="ti ti-building-bank" style="color:#7F77DD; margin-right:5px;"></i>Breakdown by Account</span>
    </div>
    <div style="display: grid; gap: 12px;">
      <?php foreach ($accountBreakdown as $account): ?>
        <div style="padding: 12px; background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 3px solid var(--blue);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <div>
              <div style="font-size: 13px; font-weight: 600;"><?= htmlspecialchars($account['account_name']) ?></div>
              <div style="font-size: 11px; color: var(--text-tertiary);"><?= htmlspecialchars($account['institution']) ?> ····<?= htmlspecialchars($account['account_mask']) ?></div>
            </div>
            <span class="pill pill-green"><?= $account['count'] ?> transactions</span>
          </div>
          <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
            <strong style="color: <?= $account['amount'] < 0 ? 'var(--red)' : 'var(--green)' ?>;">
              <?= $account['amount'] < 0 ? '−' : '+' ?><?= number_format(abs($account['amount']), 2) ?>
            </strong>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<!-- Category distribution -->
<?php if (!empty($categoryDistribution)): ?>
  <div class="card" style="margin-top: 16px;">
    <div class="card-header">
      <span class="card-title"><i class="ti ti-tag" style="color:var(--orange); margin-right:5px;"></i>Categorized Transactions</span>
    </div>
    <div style="display: grid; gap: 8px;">
      <?php foreach ($categoryDistribution as $cat): ?>
        <div style="padding: 10px; border-radius: var(--radius-md); background: var(--bg-secondary); border-left: 3px solid var(--blue);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <div style="font-size: 12px; font-weight: 500;"><?= htmlspecialchars($cat['name']) ?></div>
            <div style="font-size: 11px; color: var(--text-secondary);"><?= $cat['count'] ?> items</div>
          </div>
          <div style="height: 4px; background: var(--border); border-radius: var(--radius-sm); overflow: hidden;">
            <div style="height: 100%; width: <?= $cat['percentage'] ?>%; background: var(--blue); border-radius: var(--radius-sm);"></div>
          </div>
          <div style="font-size: 10px; color: var(--text-tertiary); margin-top: 4px; text-align: right;">
            <?= number_format($cat['percentage'], 1) ?>%
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<!-- Recent imported transactions -->
<div class="card" style="margin-top: 16px;">
  <div class="card-header">
    <span class="card-title"><i class="ti ti-list" style="color:var(--blue); margin-right:5px;"></i>Recent Imported Transactions</span>
  </div>
  <?php if (!empty($recentTransactions)): ?>
    <table class="data-table" style="font-size: 12px;">
      <thead>
        <tr>
          <th>Date</th>
          <th>Description</th>
          <th style="text-align: right;">Amount</th>
          <th>Category</th>
          <th>Account</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentTransactions as $t): ?>
          <tr>
            <td><?= date('M j, Y', strtotime($t['date'])) ?></td>
            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($t['description']) ?>">
              <?= htmlspecialchars(substr($t['description'], 0, 50)) ?>
            </td>
            <td style="text-align: right; font-weight: 500; color: <?= $t['amount'] < 0 ? 'var(--red)' : 'var(--green)' ?>;">
              <?= $t['amount'] < 0 ? '−' : '+' ?><?= number_format(abs($t['amount']), 2) ?>
            </td>
            <td><span class="pill" style="font-size: 10px;"><?= htmlspecialchars($t['category'] ?? 'Uncategorized') ?></span></td>
            <td style="font-size: 11px; color: var(--text-tertiary);"><?= htmlspecialchars($t['account'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div style="padding: 20px; text-align: center; color: var(--text-tertiary);">
      No transactions to display.
    </div>
  <?php endif; ?>
</div>

<!-- Action buttons -->
<div style="display: flex; gap: 8px; margin-top: 16px;">
  <a href="<?= BASE_URL ?>/bank-import" class="btn" style="flex: 1;">
    <i class="ti ti-arrow-left"></i> Back to Import
  </a>
  <a href="<?= BASE_URL ?>/expenses" class="btn btn-primary" style="flex: 1;">
    <i class="ti ti-arrow-right"></i> View All Transactions
  </a>
</div>
