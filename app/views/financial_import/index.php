<?php $pageTitle = 'Import'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Financial Import</h1>
    <p class="page-sub">Import transactions from your bank (Plaid), or upload a CSV / Excel file.</p>
  </div>
  <?php if ($pendingCount > 0): ?>
    <a href="<?= BASE_URL ?>/import/review" class="btn btn-primary">
      <i class="ti ti-eye"></i> Review <?= $pendingCount ?> pending
    </a>
  <?php endif; ?>
</div>

<?php if ($pendingCount > 0): ?>
  <div class="alert alert-info" style="margin-bottom:16px;">
    <i class="ti ti-info-circle"></i>
    <strong><?= $pendingCount ?></strong> transaction(s) are waiting for your review.
    <a href="<?= BASE_URL ?>/import/review" class="action-link" style="margin-left:8px;">Review now →</a>
  </div>
<?php endif; ?>

<div class="two-col">

  <!-- ── Plaid ───────────────────────────────────────────── -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="ti ti-building-bank" style="color:#7F77DD;margin-right:5px;"></i>Plaid — Live Bank Sync</span>
    </div>

    <?php if (!$plaidConfigured): ?>
      <div class="alert alert-error" style="font-size:12px;">
        Plaid is not configured. Add the following to <code>config/config.php</code>:
        <pre style="margin-top:8px;background:var(--bg-secondary);padding:8px;border-radius:var(--radius-sm);font-size:11px;">define('PLAID_CLIENT_ID', 'your_client_id');
define('PLAID_SECRET',    'your_secret');
define('PLAID_ENV',       'sandbox');</pre>
        <a href="https://dashboard.plaid.com/signup" target="_blank" class="action-link" style="margin-top:8px;display:inline-block;">Get Plaid API keys →</a>
      </div>

    <?php else: ?>

      <?php if (!empty($accounts)): ?>
        <div style="margin-bottom:14px;">
          <div style="font-size:12px;font-weight:500;color:var(--text-secondary);margin-bottom:8px;">Connected accounts</div>
          <?php foreach ($accounts as $acc): ?>
            <div style="display:flex;align-items:center;gap:9px;padding:8px;background:var(--bg-secondary);border-radius:var(--radius-md);margin-bottom:6px;">
              <i class="ti ti-building-bank" style="color:#7F77DD;font-size:16px;"></i>
              <div style="flex:1;">
                <div style="font-size:13px;font-weight:500;"><?= htmlspecialchars($acc['institution_name'] ?? 'Bank') ?></div>
                <div style="font-size:11px;color:var(--text-tertiary);">
                  <?= htmlspecialchars($acc['account_name'] ?? '') ?>
                  <?php if ($acc['account_mask']): ?> ····<?= htmlspecialchars($acc['account_mask']) ?><?php endif; ?>
                </div>
                <?php if (!empty($acc['error_code'])): ?>
                  <div style="font-size:10px;color:var(--red);margin-top:2px;">
                    <i class="ti ti-alert-triangle"></i> Re-auth required
                  </div>
                <?php elseif (!empty($acc['last_synced'])): ?>
                  <div style="font-size:10px;color:var(--text-tertiary);margin-top:2px;">
                    Last synced <?= date('M j g:i a', strtotime($acc['last_synced'])) ?>
                  </div>
                <?php endif; ?>
              </div>

              <?php if (!empty($acc['error_code'])): ?>
                <span class="pill pill-red">Error</span>
              <?php else: ?>
                <span class="pill pill-green">Active</span>
              <?php endif; ?>

              <form method="POST" action="<?= BASE_URL ?>/import/plaid/sync" style="margin:0;">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="account_id" value="<?= $acc['id'] ?>">
                <button type="submit" class="btn btn-sm" title="Sync this account"><i class="ti ti-refresh"></i></button>
              </form>

              <form method="POST" action="<?= BASE_URL ?>/import/plaid/disconnect"
                    onsubmit="return confirm('Disconnect this account? Existing transactions are kept.')" style="margin:0;">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="account_id" value="<?= $acc['id'] ?>">
                <button type="submit" class="btn btn-sm text-red" title="Disconnect"><i class="ti ti-unlink"></i></button>
              </form>
            </div>
          <?php endforeach; ?>

          <form method="POST" action="<?= BASE_URL ?>/import/plaid/sync" style="margin-top:8px;">
            <?= Auth::csrfField() ?>
            <button type="submit" class="btn btn-primary" style="width:100%;">
              <i class="ti ti-refresh"></i> Sync all accounts
            </button>
          </form>
        </div>
      <?php endif; ?>

      <button id="plaid-link-btn" class="btn" style="width:100%;">
        <i class="ti ti-link"></i>
        <?= empty($accounts) ? 'Connect a bank account' : 'Add another account' ?>
      </button>
    <?php endif; ?>
  </div>

  <!-- ── CSV / Excel Upload ──────────────────────────────── -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="ti ti-file-spreadsheet" style="color:var(--green);margin-right:5px;"></i>CSV / Excel Upload</span>
    </div>
    <p style="font-size:12px;color:var(--text-secondary);margin-bottom:14px;">
      Upload a bank statement export. Your file should have columns for
      <strong>Date</strong>, <strong>Description</strong>, and <strong>Amount</strong>.
      CSV and Excel (.xlsx, .xls) files are supported. Maximum 10 MB.
    </p>
    <form method="POST" action="<?= BASE_URL ?>/import/upload" enctype="multipart/form-data" id="upload-form">
      <?= Auth::csrfField() ?>
      <div class="form-group">
        <label>File <span class="label-hint">(.csv, .xlsx, .xls, .xlsm)</span></label>
        <input type="file" name="import_file" accept=".csv,.xlsx,.xls,.xlsm" required id="import-file-input">
      </div>
      <div id="upload-preview" style="display:none;margin-bottom:12px;">
        <div style="font-size:12px;color:var(--text-secondary);">Selected: <span id="upload-filename"></span></div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;" id="upload-btn">
        <i class="ti ti-upload"></i> Upload &amp; import
      </button>
    </form>

    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);">
      <div style="font-size:11px;color:var(--text-tertiary);font-weight:500;margin-bottom:6px;">EXPECTED FORMAT</div>
      <table style="font-size:11px;color:var(--text-secondary);width:100%;border-collapse:collapse;">
        <thead>
          <tr>
            <?php foreach (['Date','Description','Amount','Type (optional)'] as $h): ?>
              <th style="text-align:left;padding:3px 6px;background:var(--bg-secondary);border-radius:3px;"><?= $h ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="padding:3px 6px;">2024-03-15</td>
            <td style="padding:3px 6px;">Walmart Grocery</td>
            <td style="padding:3px 6px;">-87.42</td>
            <td style="padding:3px 6px;">debit</td>
          </tr>
          <tr>
            <td style="padding:3px 6px;">2024-03-14</td>
            <td style="padding:3px 6px;">Paycheck</td>
            <td style="padding:3px 6px;">2500.00</td>
            <td style="padding:3px 6px;">credit</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── Recent import sessions ──────────────────────────────── -->
<?php if (!empty($recentSessions)): ?>
  <div class="card" style="margin-top:16px;">
    <div class="card-header">
      <span class="card-title">Recent imports</span>
      <a href="<?= BASE_URL ?>/import/history" class="action-link" style="font-size:12px;">View all →</a>
    </div>
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Source</th>
          <th>File</th>
          <th>Total</th>
          <th>Imported</th>
          <th>Duplicates</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentSessions as $s): ?>
          <tr>
            <td style="font-size:12px;"><?= date('M j, Y g:i a', strtotime($s['created_at'])) ?></td>
            <td>
              <?php
              $sourceColors = ['plaid' => '#7F77DD', 'csv' => 'var(--green)', 'excel' => '#185FA5'];
              $c = $sourceColors[$s['source']] ?? 'var(--text-secondary)';
              ?>
              <span class="pill" style="background:<?= $c ?>22;color:<?= $c ?>;border:1px solid <?= $c ?>44;">
                <?= strtoupper(htmlspecialchars($s['source'])) ?>
              </span>
            </td>
            <td style="font-size:11px;color:var(--text-tertiary);"><?= htmlspecialchars($s['filename'] ?? '—') ?></td>
            <td><?= number_format((int)$s['total_rows']) ?></td>
            <td style="color:var(--green);"><?= number_format((int)$s['imported']) ?></td>
            <td style="color:var(--text-tertiary);"><?= number_format((int)$s['duplicates']) ?></td>
            <td>
              <?php
              $statusMap = [
                  'complete'   => ['pill-green', 'Complete'],
                  'processing' => ['pill-blue',  'Processing'],
                  'pending'    => ['pill-blue',  'Pending'],
                  'failed'     => ['pill-red',   'Failed'],
              ];
              [$cls, $label] = $statusMap[$s['status']] ?? ['', ucfirst($s['status'])];
              ?>
              <span class="pill <?= $cls ?>"><?= $label ?></span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php if ($plaidConfigured): ?>
<!-- Plaid Link JS -->
<script src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"></script>
<script>
(function() {
  const btn = document.getElementById('plaid-link-btn');
  if (!btn) return;

  btn.addEventListener('click', async () => {
    btn.disabled = true;
    btn.innerHTML = '<i class="ti ti-loader"></i> Connecting…';

    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="csrf_token"]')?.value
                || '';

      const resp = await fetch('<?= BASE_URL ?>/import/plaid/link-token', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=' + encodeURIComponent(csrf),
      });
      const data = await resp.json();

      if (data.error) {
        alert('Error: ' + data.error);
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-link"></i> Connect a bank account';
        return;
      }

      const handler = Plaid.create({
        token: data.link_token,
        onSuccess: async (public_token, metadata) => {
          btn.innerHTML = '<i class="ti ti-loader"></i> Saving…';
          const connectResp = await fetch('<?= BASE_URL ?>/import/plaid/connect', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'csrf_token=' + encodeURIComponent(csrf) + '&public_token=' + encodeURIComponent(public_token),
          });
          const result = await connectResp.json();
          if (result.ok || result.warning) {
            window.location.reload();
          } else {
            alert('Error: ' + (result.error || 'Could not save account.'));
            btn.disabled = false;
            btn.innerHTML = '<i class="ti ti-link"></i> Connect a bank account';
          }
        },
        onExit: () => {
          btn.disabled = false;
          btn.innerHTML = '<i class="ti ti-link"></i> Connect a bank account';
        },
      });
      handler.open();
    } catch (err) {
      console.error(err);
      alert('Failed to start Plaid Link. Check your configuration.');
      btn.disabled = false;
      btn.innerHTML = '<i class="ti ti-link"></i> Connect a bank account';
    }
  });

  // File name preview
  const fileInput = document.getElementById('import-file-input');
  const preview   = document.getElementById('upload-preview');
  const fname     = document.getElementById('upload-filename');
  if (fileInput) {
    fileInput.addEventListener('change', () => {
      if (fileInput.files[0]) {
        fname.textContent = fileInput.files[0].name;
        preview.style.display = 'block';
      }
    });
  }

  // Upload form — disable button while submitting
  const form = document.getElementById('upload-form');
  if (form) {
    form.addEventListener('submit', () => {
      const uploadBtn = document.getElementById('upload-btn');
      if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="ti ti-loader"></i> Uploading…';
      }
    });
  }
})();
</script>
<?php endif; ?>
