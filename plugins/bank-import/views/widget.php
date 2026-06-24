<?php
if (!Auth::check()) return;
$importModel = new BankImportModel();
$sessions    = $importModel->getSessionsForUser(Auth::id());
$last        = $sessions[0] ?? null;
if (!$last) return;
?>
<div class="card" style="margin-bottom:14px;">
  <div class="card-header">
    <span class="card-title"><i class="ti ti-building-bank" style="color:#7F77DD; margin-right:5px;"></i>Bank Import</span>
    <a href="<?= BASE_URL ?>/bank-import" class="card-link">Import →</a>
  </div>
  <div style="font-size:12px; color:var(--text-secondary);">
    Last import: <strong><?= date('M j, Y', strtotime($last['created_at'])) ?></strong>
    · <?= $last['imported'] ?> transactions · <span class="pill <?= $last['status'] === 'complete' ? 'pill-green' : 'pill-amber' ?>"><?= ucfirst($last['status']) ?></span>
  </div>
  <?php if ($last['status'] === 'processing'): ?>
    <a href="<?= BASE_URL ?>/bank-import/review/<?= $last['id'] ?>" class="btn btn-sm" style="margin-top:8px; display:inline-flex;">Finish reviewing →</a>
  <?php endif; ?>
</div>
