<?php $pageTitle = 'Debt Tracker'; ?>
<div class="page-header">
  <div><h1 class="page-title">Debt Payoff Tracker</h1><p class="page-sub">Snowball strategy — smallest balance first.</p></div>
  <a href="<?= BASE_URL ?>/debt-tracker/create" class="btn btn-primary"><i class="ti ti-plus"></i> Add debt</a>
</div>

<!-- Summary -->
<div class="metrics-grid" style="margin-bottom:16px;">
  <div class="metric-card">
    <div class="metric-label">Total debt remaining</div>
    <div class="metric-value text-red">$<?= number_format($totalDebt, 2) ?></div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Active debts</div>
    <div class="metric-value"><?= count(array_filter($debts, fn($d) => !$d['is_paid_off'])) ?></div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Debts paid off</div>
    <div class="metric-value text-green"><?= count(array_filter($debts, fn($d) => $d['is_paid_off'])) ?></div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Total paid</div>
    <div class="metric-value text-green">$<?= number_format(array_sum(array_column($debts, 'total_paid')), 2) ?></div>
  </div>
</div>

<div class="two-col">
  <!-- Debt cards -->
  <div>
    <?php if (empty($debts)): ?>
      <div class="empty-card"><i class="ti ti-credit-card-off" style="font-size:40px;color:var(--text-tertiary);"></i><p>No debts added yet.</p><a href="<?= BASE_URL ?>/debt-tracker/create" class="btn btn-primary">Add first debt</a></div>
    <?php else: ?>
      <div style="display:flex; flex-direction:column; gap:12px;">
        <?php foreach ($debts as $i => $debt):
          $pct = $debt['original_balance'] > 0 ? min(100, round((1 - $debt['balance'] / $debt['original_balance']) * 100)) : 100;
        ?>
          <div class="card <?= $debt['is_paid_off'] ? 'debt-paidoff' : '' ?>">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
              <?php if (!$debt['is_paid_off']): ?>
                <div style="width:24px; height:24px; border-radius:50%; background:var(--red-light); color:var(--red-dark); font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0;"><?= $i+1 ?></div>
              <?php else: ?>
                <i class="ti ti-circle-check" style="color:var(--green); font-size:22px;"></i>
              <?php endif; ?>
              <div style="flex:1;">
                <div style="font-size:14px; font-weight:500; color:var(--text-primary);"><?= htmlspecialchars($debt['name']) ?></div>
                <div style="font-size:11px; color:var(--text-tertiary);"><?= ucfirst(str_replace('_',' ',$debt['debt_type'])) ?> · <?= $debt['interest_rate'] ?>% APR</div>
              </div>
              <div style="text-align:right;">
                <div style="font-size:18px; font-weight:600; color:<?= $debt['is_paid_off'] ? 'var(--green-dark)' : 'var(--red-dark)' ?>">
                  $<?= number_format($debt['balance'], 2) ?>
                </div>
                <div style="font-size:10px; color:var(--text-tertiary);">of $<?= number_format($debt['original_balance'],2) ?></div>
              </div>
            </div>

            <div class="progress-bg" style="height:6px; margin-bottom:6px;">
              <div class="progress-fill" style="width:<?= $pct ?>%; height:6px; background:<?= $debt['is_paid_off'] ? 'var(--green)' : 'var(--red)' ?>;"></div>
            </div>
            <div style="font-size:10px; color:var(--text-tertiary); margin-bottom:10px;"><?= $pct ?>% paid off · min payment $<?= number_format($debt['minimum_payment'],2) ?>/mo</div>

            <?php if (!$debt['is_paid_off']): ?>
              <form method="POST" action="<?= BASE_URL ?>/debt-tracker/<?= $debt['id'] ?>/payment" style="display:flex; gap:6px; align-items:center; margin-bottom:8px;">
                <?= Auth::csrfField() ?>
                <input type="number" name="amount" step="0.01" min="0.01" placeholder="Payment $" style="width:110px;" required>
                <input type="date" name="paid_date" value="<?= date('Y-m-d') ?>" style="width:140px;">
                <input type="text" name="note" placeholder="Note" style="flex:1;">
                <button type="submit" class="btn btn-sm btn-primary">Record</button>
              </form>
            <?php endif; ?>

            <div style="display:flex; gap:10px;">
              <a href="<?= BASE_URL ?>/debt-tracker/<?= $debt['id'] ?>/edit" class="action-link">Edit</a>
              <form method="POST" action="<?= BASE_URL ?>/debt-tracker/<?= $debt['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Remove this debt?')">
                <?= Auth::csrfField() ?><button class="action-link text-red">Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Snowball projection -->
  <div class="card" style="height:fit-content;">
    <div class="card-header"><span class="card-title">Snowball Payoff Projection</span></div>
    <div class="form-group" style="margin-bottom:14px;">
      <label>Extra monthly payment ($)</label>
      <form method="GET" style="display:flex; gap:6px;">
        <input type="number" name="extra" step="0.01" min="0" value="<?= htmlspecialchars($extra) ?>" placeholder="0.00" style="flex:1;">
        <button type="submit" class="btn btn-sm">Recalculate</button>
      </form>
    </div>
    <?php if (empty($projection)): ?>
      <p class="empty-state">Add a debt to see your payoff timeline.</p>
    <?php else: ?>
      <div style="display:flex; flex-direction:column; gap:10px;">
        <?php foreach ($projection as $p): ?>
          <div style="display:flex; align-items:center; gap:10px; padding:8px; background:var(--bg-secondary); border-radius:var(--radius-md);">
            <div style="flex:1;">
              <div style="font-size:13px; font-weight:500; color:var(--text-primary);"><?= htmlspecialchars($p['name']) ?></div>
              <div style="font-size:11px; color:var(--text-tertiary);">$<?= number_format($p['balance'],2) ?> · $<?= number_format($p['payment'],2) ?>/mo</div>
            </div>
            <div style="text-align:right;">
              <div style="font-size:13px; font-weight:500; color:var(--text-primary);"><?= $p['payoff_date'] ?></div>
              <div style="font-size:10px; color:var(--text-tertiary);"><?= $p['months'] < 600 ? $p['months'] . ' months' : 'Check rate' ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($extra > 0): ?>
        <p style="font-size:11px; color:var(--green-dark); margin-top:10px; background:var(--green-light); padding:8px; border-radius:var(--radius-md);">
          <i class="ti ti-trending-down"></i> With $<?= number_format($extra,2) ?>/mo extra, each freed minimum rolls into the next debt.
        </p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<style>
.debt-paidoff { opacity: 0.6; }
</style>
