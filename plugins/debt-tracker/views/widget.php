<?php
if (!Auth::check()) return;
$debtModel = new DebtModel();
$debts     = $debtModel->getForUser(Auth::id());
$active    = array_filter($debts, fn($d) => !$d['is_paid_off']);
if (empty($active)) return;
$total = $debtModel->getTotalDebt(Auth::id());
?>
<div class="card" style="margin-bottom:14px;">
  <div class="card-header">
    <span class="card-title"><i class="ti ti-credit-card-off" style="color:var(--red); margin-right:5px;"></i>Debt Tracker</span>
    <a href="<?= BASE_URL ?>/debt-tracker" class="card-link">Payoff plan →</a>
  </div>
  <div style="font-size:11px; color:var(--text-secondary); margin-bottom:10px;">Total remaining: <strong style="color:var(--red-dark);">$<?= number_format($total,2) ?></strong></div>
  <?php foreach (array_slice($active, 0, 3) as $debt):
    $pct = $debt['original_balance'] > 0 ? min(100, round((1 - $debt['balance'] / $debt['original_balance']) * 100)) : 0;
  ?>
    <div style="margin-bottom:8px;">
      <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:3px;">
        <span style="font-weight:500; color:var(--text-primary);"><?= htmlspecialchars($debt['name']) ?></span>
        <span style="color:var(--text-secondary);">$<?= number_format($debt['balance'],2) ?></span>
      </div>
      <div class="progress-bg" style="height:5px;"><div class="progress-fill" style="width:<?= $pct ?>%; height:5px; background:var(--red);"></div></div>
    </div>
  <?php endforeach; ?>
</div>
