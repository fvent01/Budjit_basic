<?php $pageTitle = 'Pay Stubs'; ?>
<div class="page-header">
  <div><h1 class="page-title">Pay Stubs</h1><p class="page-sub">Import your ADP earnings statements to track net income.</p></div>
  <a href="<?= BASE_URL ?>/paystub/manual" class="btn"><i class="ti ti-pencil"></i> Enter manually</a>
</div>

<!-- Upload card -->
<div class="two-col" style="align-items:start; margin-bottom:20px;">
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="ti ti-file-type-pdf" style="color:var(--red); margin-right:5px;"></i>Upload PDF Pay Stub</span>
    </div>
    <p style="font-size:12px; color:var(--text-secondary); margin-bottom:14px; line-height:1.6;">
      Upload your ADP Earnings Statement PDF. The parser extracts gross pay, deductions, and net pay automatically.
      Only <strong>net pay</strong> (what hits your bank) is recorded as income.
    </p>
    <form method="POST" action="<?= BASE_URL ?>/paystub/upload" enctype="multipart/form-data">
      <?= Auth::csrfField() ?>
      <div class="form-group">
        <label>ADP Earnings Statement <span class="label-hint">(.pdf)</span></label>
        <input type="file" name="stub_pdf" accept=".pdf" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">
        <i class="ti ti-upload"></i> Parse & Review
      </button>
    </form>
    <div style="margin-top:12px; padding-top:12px; border-top:0.5px solid var(--border); font-size:11px; color:var(--text-tertiary);">
      <i class="ti ti-info-circle" style="font-size:13px; vertical-align:middle;"></i>
      Requires Python + <code>pdfplumber</code>. Install with: <code>pip install pdfplumber</code>
    </div>
  </div>

  <!-- What gets captured -->
  <div class="card">
    <div class="card-header"><span class="card-title">What gets captured</span></div>
    <div style="display:flex; flex-direction:column; gap:10px;">
      <?php
      $fields = [
        ['ti-calendar',      'var(--blue)',  'Pay period',      'Period beginning, ending, and pay date'],
        ['ti-cash',          'var(--green)', 'Net pay',         'Direct deposit amount recorded as income'],
        ['ti-receipt',       'var(--amber)', 'Gross pay',       'Pre-tax earnings stored for reference'],
        ['ti-file-invoice',  'var(--red)',   'Deductions',      'Federal, SS, Medicare, KY state, Glasgow city'],
        ['ti-trending-up',   'var(--blue)',  'YTD gross',       'Year-to-date for anomaly detection'],
        ['ti-alert-triangle','var(--amber)', 'Anomaly flags',   'Unusual changes vs your recent pay history'],
      ];
      foreach ($fields as [$icon, $color, $label, $desc]):
      ?>
        <div style="display:flex; align-items:center; gap:10px;">
          <div style="width:30px; height:30px; border-radius:var(--radius-md); background:<?= $color ?>22; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <i class="ti <?= $icon ?>" style="color:<?= $color ?>; font-size:15px;"></i>
          </div>
          <div>
            <div style="font-size:12px; font-weight:500; color:var(--text-primary);"><?= $label ?></div>
            <div style="font-size:11px; color:var(--text-tertiary);"><?= $desc ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Pay stub history -->
<?php if (empty($stubs)): ?>
  <div class="empty-card">
    <i class="ti ti-receipt" style="font-size:40px; color:var(--text-tertiary);"></i>
    <p>No pay stubs imported yet. Upload your first one above.</p>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-header"><span class="card-title">Import history</span></div>
    <table class="data-table">
      <thead>
        <tr><th>Pay Date</th><th>Period</th><th>Employer</th><th>Gross</th><th>Deductions</th><th>Net Pay</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($stubs as $s): ?>
          <tr>
            <td style="font-weight:500; white-space:nowrap;"><?= date('M j, Y', strtotime($s['pay_date'])) ?></td>
            <td style="font-size:11px; color:var(--text-tertiary); white-space:nowrap;">
              <?= date('M j', strtotime($s['period_beginning'])) ?> – <?= date('M j', strtotime($s['period_ending'])) ?>
            </td>
            <td style="font-size:12px;"><?= htmlspecialchars($s['employer_name'] ?: '—') ?></td>
            <td>$<?= number_format($s['gross_pay'], 2) ?></td>
            <td style="color:var(--red-dark);">-$<?= number_format($s['total_deductions'], 2) ?></td>
            <td style="color:var(--green-dark); font-weight:500;">$<?= number_format($s['net_pay'], 2) ?></td>
            <td>
              <?php if ($s['ytd_flag'] && $s['status'] !== 'imported'): ?>
                <span class="pill pill-amber" title="<?= htmlspecialchars($s['ytd_flag_reason']) ?>">⚠ Flagged</span>
              <?php else: ?>
                <span class="pill <?= match($s['status']) { 'imported' => 'pill-green', 'skipped' => '', default => 'pill-amber' } ?>">
                  <?= ucfirst($s['status']) ?>
                </span>
              <?php endif; ?>
            </td>
            <td class="actions-cell">
              <?php if ($s['status'] === 'parsed' || $s['status'] === 'reviewed'): ?>
                <a href="<?= BASE_URL ?>/paystub/<?= $s['id'] ?>/review" class="action-link">Review</a>
              <?php endif; ?>
              <a href="<?= BASE_URL ?>/paystub/<?= $s['id'] ?>/edit" class="action-link">Edit</a>
              <form method="POST" action="<?= BASE_URL ?>/paystub/<?= $s['id'] ?>/delete" style="display:inline;"
                    onsubmit="return confirm('Delete this pay stub?')">
                <?= Auth::csrfField() ?>
                <button class="action-link text-red">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
