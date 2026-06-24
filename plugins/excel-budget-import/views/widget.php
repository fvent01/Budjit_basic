<?php
if (!Auth::check()) return;
try {
    $importModel = new ExcelBudgetImportModel();
    $sessions    = $importModel->getSessionsForUser(Auth::id());
} catch (Exception $e) { return; }

$last = $sessions[0] ?? null;
?>
<div class="card" style="margin-bottom:14px;">
  <div class="card-header">
    <span class="card-title"><i class="ti ti-file-spreadsheet" style="color:var(--green); margin-right:5px;"></i>Excel Import</span>
    <a href="<?= BASE_URL ?>/excel-import" class="card-link">Import →</a>
  </div>
  <?php if (!$last): ?>
    <p style="font-size:12px; color:var(--text-secondary);">No imports yet.</p>
    <a href="<?= BASE_URL ?>/excel-import" class="btn btn-sm btn-primary" style="margin-top:8px;">Import your budget workbook</a>
  <?php else: ?>
    <div style="font-size:12px; color:var(--text-secondary); margin-bottom:6px;">
      Last import: <strong><?= date('M j, Y', strtotime($last['created_at'])) ?></strong>
      · <?= number_format($last['imported']) ?> rows
      · <span class="pill <?= $last['status'] === 'complete' ? 'pill-green' : 'pill-amber' ?>"><?= ucfirst($last['status']) ?></span>
    </div>
    <?php if ($last['status'] === 'previewing'): ?>
      <a href="<?= BASE_URL ?>/excel-import/preview/<?= $last['id'] ?>" class="btn btn-sm btn-primary" style="margin-top:6px;">
        Finish reviewing →
      </a>
    <?php endif; ?>
  <?php endif; ?>
</div>
