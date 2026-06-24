<?php
// Dashboard widget for financial-import plugin
$importModel  = new FinancialImportModel();
$pending      = $importModel->countPendingReview(Auth::id());
$sessions     = $importModel->getSessionsForUser(Auth::id(), 3);
$lastImported = 0;
if (!empty($sessions)) {
    foreach ($sessions as $s) {
        $lastImported += (int)$s['imported'];
    }
}
?>
<div class="widget-card" style="border-left:3px solid #7F77DD;">
  <div class="widget-header">
    <span><i class="ti ti-file-import" style="color:#7F77DD;"></i> Import</span>
    <a href="<?= BASE_URL ?>/import" class="action-link" style="font-size:11px;">Manage →</a>
  </div>
  <div style="display:flex;gap:16px;margin-top:10px;">
    <div style="text-align:center;">
      <div style="font-size:22px;font-weight:700;color:<?= $pending > 0 ? 'var(--yellow-dark)' : 'var(--text-secondary)' ?>;">
        <?= $pending ?>
      </div>
      <div style="font-size:10px;color:var(--text-tertiary);text-transform:uppercase;">Pending</div>
    </div>
    <div style="text-align:center;">
      <div style="font-size:22px;font-weight:700;color:var(--green);"><?= $lastImported ?></div>
      <div style="font-size:10px;color:var(--text-tertiary);text-transform:uppercase;">Recent</div>
    </div>
  </div>
  <?php if ($pending > 0): ?>
    <a href="<?= BASE_URL ?>/import/review" class="btn btn-primary btn-sm" style="margin-top:10px;width:100%;font-size:12px;">
      <i class="ti ti-eye"></i> Review <?= $pending ?> transaction(s)
    </a>
  <?php else: ?>
    <a href="<?= BASE_URL ?>/import" class="btn btn-sm" style="margin-top:10px;width:100%;font-size:12px;">
      <i class="ti ti-upload"></i> Import transactions
    </a>
  <?php endif; ?>
</div>
