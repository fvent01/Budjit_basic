<?php
if (!Auth::check()) return;
$billModel = new RecurringBillModel();
$unpaid    = $billModel->getUpcomingUnpaid(Auth::id(), 14);
$monthly   = $billModel->getMonthlyTotal(Auth::id());
if (empty($unpaid) && $monthly == 0) return;
?>
<div class="card" style="margin-bottom:14px;">
  <div class="card-header">
    <span class="card-title"><i class="ti ti-refresh" style="color:var(--blue); margin-right:5px;"></i>Recurring Bills</span>
    <a href="<?= BASE_URL ?>/recurring-bills" class="card-link">All bills →</a>
  </div>
  <div style="font-size:11px; color:var(--text-secondary); margin-bottom:10px;">
    Monthly total: <strong>$<?= number_format($monthly, 2) ?></strong>
    <?php if (!empty($unpaid)): ?>
      · <span style="color:var(--amber-dark);"><?= count($unpaid) ?> due soon</span>
    <?php endif; ?>
  </div>
  <?php foreach (array_slice($unpaid, 0, 4) as $bill): ?>
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:7px;">
      <i class="ti <?= htmlspecialchars($bill['icon']) ?>" style="color:<?= htmlspecialchars($bill['color']) ?>; font-size:15px; flex-shrink:0;"></i>
      <span style="flex:1; font-size:12px; color:var(--text-primary);"><?= htmlspecialchars($bill['name']) ?></span>
      <span style="font-size:11px; color:var(--text-tertiary);"><?= date('M j', strtotime($bill['due_date'])) ?></span>
      <span style="font-size:12px; font-weight:500;">$<?= number_format($bill['amount'], 2) ?></span>
    </div>
  <?php endforeach; ?>
</div>
