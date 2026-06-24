<?php $pageTitle = 'Excel Budget Import'; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Excel Budget Import</h1>
    <p class="page-sub">Import your 2026 FamilyBudget workbook directly into Budjit.</p>
  </div>
</div>

<div class="two-col" style="align-items:start;">

  <!-- Upload card -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="ti ti-file-spreadsheet" style="color:var(--green); margin-right:5px;"></i>Upload Workbook</span>
    </div>

    <div style="background:var(--green-light); border-radius:var(--radius-md); padding:12px 14px; margin-bottom:16px;">
      <div style="font-size:12px; font-weight:500; color:var(--green-dark); margin-bottom:6px;"><i class="ti ti-check"></i> Supported format</div>
      <div style="font-size:12px; color:var(--green-dark); line-height:1.6;">
        Upload a supported spreadsheet to preview and validate your budget data before importing.
        This importer accepts your FamilyBudget workbook or a generic CSV export.
      </div>
    </div>

    <div style="font-size:12px; color:var(--text-secondary); margin-bottom:14px; line-height:1.6;">
      All weekly rows are extracted, expenses are auto-categorised, and income entries are separated out.
      You'll review everything before it's saved.
    </div>

    <form method="POST" action="<?= BASE_URL ?>/excel-import/upload" enctype="multipart/form-data">
      <?= Auth::csrfField() ?>
      <div class="form-group">
        <label>Select your budget workbook or CSV file <span class="label-hint">(.xlsx, .xlsm, .xls, .csv)</span></label>
        <input type="file" name="excel_file" accept=".xlsx,.xlsm,.xls,.csv" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">
        <i class="ti ti-upload"></i> Upload & Parse
      </button>
    </form>
  </div>

  <!-- What gets imported -->
  <div class="card">
    <div class="card-header"><span class="card-title">What gets imported</span></div>
    <div style="display:flex; flex-direction:column; gap:10px;">
      <?php
      $items = [
        ['ti-cash',          'var(--green)',  'Income',        'Francois & Mary paycheck columns, both sheets'],
        ['ti-building',      'var(--blue)',   'Rent',          'Monthly rent payment rows'],
        ['ti-bolt',          'var(--amber)',  'Utilities',     'Electric, water, internet, phone'],
        ['ti-shopping-cart', 'var(--green)',  'Food & Dining', 'Groceries, eating out'],
        ['ti-gas-station',   'var(--red)',    'Fuel',          'Gas station entries'],
        ['ti-device-gamepad-2','#D4537E',     'Subscriptions', 'All Amazon, streaming, phone services'],
        ['ti-car',           'var(--blue)',   'Auto',          'Car payment, insurance, maintenance'],
        ['ti-baby-carriage', '#7F77DD',       'Kids',          'Day care, kids subscriptions'],
        ['ti-piggy-bank',    'var(--green)',  'Savings',       'Sofi Investing contributions'],
      ];
      foreach ($items as [$icon, $color, $label, $desc]):
      ?>
        <div style="display:flex; align-items:center; gap:10px;">
          <div style="width:32px; height:32px; border-radius:var(--radius-md); background:<?= $color ?>22; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <i class="ti <?= $icon ?>" style="color:<?= $color ?>; font-size:16px;"></i>
          </div>
          <div>
            <div style="font-size:13px; font-weight:500; color:var(--text-primary);"><?= $label ?></div>
            <div style="font-size:11px; color:var(--text-tertiary);"><?= $desc ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Import history -->
<?php if (!empty($sessions)): ?>
  <div class="card" style="margin-top:16px;">
    <div class="card-header"><span class="card-title">Import history</span></div>
    <table class="data-table">
      <thead>
        <tr><th>Date</th><th>File</th><th>Rows parsed</th><th>Imported</th><th>Skipped</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($sessions as $s): ?>
          <tr>
            <td><?= date('M j, Y g:i a', strtotime($s['created_at'])) ?></td>
            <td style="font-size:11px; color:var(--text-secondary);"><?= htmlspecialchars($s['filename']) ?></td>
            <td><?= number_format($s['row_count']) ?></td>
            <td style="color:var(--green-dark); font-weight:500;"><?= number_format($s['imported']) ?></td>
            <td style="color:var(--text-tertiary);"><?= number_format($s['skipped']) ?></td>
            <td>
              <span class="pill <?= $s['status'] === 'complete' ? 'pill-green' : ($s['status'] === 'previewing' ? 'pill-amber' : '') ?>">
                <?= ucfirst($s['status']) ?>
              </span>
            </td>
            <td class="actions-cell">
              <?php if ($s['status'] === 'previewing'): ?>
                <a href="<?= BASE_URL ?>/excel-import/preview/<?= $s['id'] ?>" class="action-link">Review</a>
              <?php endif; ?>
              <form method="POST" action="<?= BASE_URL ?>/excel-import/session/<?= $s['id'] ?>/delete"
                    style="display:inline;" onsubmit="return confirm('Delete this import session?')">
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
