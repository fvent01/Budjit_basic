<?php $pageTitle = 'Import History'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Import History</h1>
    <p class="page-sub">All import sessions and confirmed transactions.</p>
  </div>
  <a href="<?= BASE_URL ?>/import" class="btn btn-sm"><i class="ti ti-arrow-left"></i> Back</a>
</div>

<!-- ── Import sessions ─────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-header"><span class="card-title">Import Sessions</span></div>
  <?php if (empty($sessions)): ?>
    <p style="font-size:13px;color:var(--text-secondary);padding:12px;">No import sessions yet.</p>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Source</th>
          <th>File</th>
          <th>Total</th>
          <th>Imported</th>
          <th>Duplicates</th>
          <th>Failed</th>
          <th>Status</th>
          <th>Duration</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sessions as $s):
          $sourceColors = ['plaid' => '#7F77DD', 'csv' => 'var(--green)', 'excel' => '#185FA5'];
          $c = $sourceColors[$s['source']] ?? 'var(--text-secondary)';
          $statusMap = [
              'complete'   => ['pill-green', 'Complete'],
              'processing' => ['pill-blue',  'Processing'],
              'pending'    => ['pill-blue',  'Pending'],
              'failed'     => ['pill-red',   'Failed'],
          ];
          [$sCls, $sLabel] = $statusMap[$s['status']] ?? ['', ucfirst($s['status'])];
        ?>
          <tr>
            <td style="font-size:12px;white-space:nowrap;"><?= date('M j Y, g:i a', strtotime($s['created_at'])) ?></td>
            <td>
              <span class="pill" style="background:<?= $c ?>22;color:<?= $c ?>;border:1px solid <?= $c ?>44;">
                <?= strtoupper(htmlspecialchars($s['source'])) ?>
              </span>
            </td>
            <td style="font-size:11px;color:var(--text-tertiary);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              <?= htmlspecialchars($s['filename'] ?? '—') ?>
            </td>
            <td><?= number_format((int)$s['total_rows']) ?></td>
            <td style="color:var(--green);"><?= number_format((int)$s['imported']) ?></td>
            <td style="color:var(--text-tertiary);"><?= number_format((int)$s['duplicates']) ?></td>
            <td style="color:<?= (int)$s['failed'] > 0 ? 'var(--red)' : 'var(--text-tertiary)' ?>;">
              <?= number_format((int)$s['failed']) ?>
            </td>
            <td><span class="pill <?= $sCls ?>"><?= $sLabel ?></span></td>
            <td style="font-size:11px;color:var(--text-tertiary);">
              <?php if ($s['completed_at']): ?>
                <?= round((strtotime($s['completed_at']) - strtotime($s['created_at'])) / 60, 1) ?>m
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
          </tr>
          <?php if (!empty($s['errors'])): ?>
            <tr>
              <td colspan="9" style="padding:4px 10px;background:var(--red-light);font-size:11px;color:var(--red);">
                <i class="ti ti-alert-triangle"></i> <?= htmlspecialchars(mb_substr($s['errors'], 0, 300)) ?>
              </td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- ── Confirmed transactions ──────────────────────────────── -->
<div class="card">
  <div class="card-header"><span class="card-title">Confirmed Transactions (last 100)</span></div>
  <?php if (empty($recent)): ?>
    <p style="font-size:13px;color:var(--text-secondary);padding:12px;">No confirmed transactions yet.</p>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Name</th>
          <th>Account</th>
          <th>Source</th>
          <th>Category</th>
          <th>Type</th>
          <th class="text-right">Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $txn):
          $sourceColors = ['plaid' => '#7F77DD', 'csv' => 'var(--green)', 'excel' => '#185FA5'];
          $c = $sourceColors[$txn['source']] ?? 'var(--text-secondary)';
        ?>
          <tr>
            <td style="font-size:12px;white-space:nowrap;"><?= date('M j, Y', strtotime($txn['date'])) ?></td>
            <td>
              <div style="font-size:13px;"><?= htmlspecialchars($txn['name']) ?></div>
              <?php if ($txn['merchant'] && $txn['merchant'] !== $txn['name']): ?>
                <div style="font-size:11px;color:var(--text-tertiary);"><?= htmlspecialchars($txn['merchant']) ?></div>
              <?php endif; ?>
            </td>
            <td style="font-size:11px;color:var(--text-tertiary);">
              <?php if (!empty($txn['institution_name'])): ?>
                <?= htmlspecialchars($txn['institution_name']) ?>
                <?php if (!empty($txn['account_name'])): ?>
                  <br><?= htmlspecialchars($txn['account_name']) ?>
                <?php endif; ?>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td>
              <span class="pill" style="background:<?= $c ?>22;color:<?= $c ?>;border:1px solid <?= $c ?>44;font-size:10px;">
                <?= strtoupper(htmlspecialchars($txn['source'])) ?>
              </span>
            </td>
            <td>
              <?php if (!empty($txn['category_name'])): ?>
                <span class="pill" style="background:<?= htmlspecialchars($txn['color'] ?? '#888') ?>22;color:<?= htmlspecialchars($txn['color'] ?? '#888') ?>;">
                  <?= htmlspecialchars($txn['category_name']) ?>
                </span>
              <?php else: ?>
                <span style="font-size:11px;color:var(--text-tertiary);">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($txn['mapped_as'] === 'income'): ?>
                <span class="pill pill-green">Income</span>
              <?php else: ?>
                <span class="pill">Expense</span>
              <?php endif; ?>
            </td>
            <td class="text-right" style="font-weight:500;color:<?= $txn['mapped_as'] === 'income' ? 'var(--green)' : 'var(--text-primary)' ?>;">
              <?= $txn['mapped_as'] === 'income' ? '+' : '-' ?>$<?= number_format($txn['amount'], 2) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
