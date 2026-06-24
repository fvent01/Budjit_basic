<?php $pageTitle = 'Bank Import'; ?>

<div class="page-header">
  <div><h1 class="page-title">Bank Import</h1><p class="page-sub">Import transactions via Plaid or CSV/Excel upload.</p></div>
</div>

<div class="two-col">
  <!-- Plaid live sync -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="ti ti-building-bank" style="color:#7F77DD;margin-right:5px;"></i>Plaid — Live Bank Sync</span>
    </div>
    <?php $plaidKey = 'PLAID_PUBLIC_KEY'; if (!$plaidKey): ?>
      <div class="alert alert-error" style="font-size:12px;">
        Plaid is not configured. Add the following to <code>config/config.php</code>:
        <pre style="margin-top:8px; background:var(--bg-secondary); padding:8px; border-radius:var(--radius-sm); font-size:11px;">define('PLAID_CLIENT_ID', 'your_client_id');
define('PLAID_SECRET',    'your_secret');
define('PLAID_ENV',       'sandbox'); // or 'production'
define('PLAID_PUBLIC_KEY','your_public_key');</pre>
        <a href="https://dashboard.plaid.com/signup" target="_blank" class="action-link" style="margin-top:8px; display:inline-block;">Get Plaid API keys →</a>
      </div>
    <?php else: ?>
      <?php if (!empty($accounts)): ?>
        <div style="margin-bottom:14px;">
          <div style="font-size:12px; font-weight:500; color:var(--text-secondary); margin-bottom:8px;">Connected accounts</div>
          <?php foreach ($accounts as $acc): ?>
            <div style="display:flex; align-items:center; gap:9px; padding:8px; background:var(--bg-secondary); border-radius:var(--radius-md); margin-bottom:6px;">
              <i class="ti ti-building-bank" style="color:#7F77DD; font-size:16px;"></i>
              <div style="flex:1;">
                <div style="font-size:13px; font-weight:500;"><?= htmlspecialchars($acc['institution_name']) ?></div>
                <div style="font-size:11px; color:var(--text-tertiary);"><?= htmlspecialchars($acc['account_name']) ?> ····<?= htmlspecialchars($acc['account_mask']) ?></div>
              </div>
              <span class="pill pill-green">Connected</span>
              <form method="POST" action="<?= BASE_URL ?>/bank-import/plaid/disconnect" onsubmit="return confirm('Disconnect this account?')">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="account_id" value="<?= $acc['id'] ?>">
                <button class="action-link text-red" style="font-size:11px;">Disconnect</button>
              </form>
            </div>
          <?php endforeach; ?>
          <form method="POST" action="<?= BASE_URL ?>/bank-import/plaid/sync">
            <?= Auth::csrfField() ?>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:8px;"><i class="ti ti-refresh"></i> Sync last 30 days</button>
          </form>
        </div>
      <?php endif; ?>
      <button id="plaid-link-btn" class="btn" style="width:100%;"><i class="ti ti-link"></i> Connect a bank account</button>
    <?php endif; ?>
  </div>

  <!-- CSV / Excel upload -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="ti ti-file-spreadsheet" style="color:var(--green);margin-right:5px;"></i>CSV / Excel Upload</span>
    </div>
    <p style="font-size:12px; color:var(--text-secondary); margin-bottom:14px;">Upload a bank statement export. Your CSV/Excel should have columns for <strong>Date</strong>, <strong>Description</strong>, and <strong>Amount</strong>.</p>
    <form method="POST" action="<?= BASE_URL ?>/bank-import/upload" enctype="multipart/form-data">
      <?= Auth::csrfField() ?>
      <div class="form-group">
        <label>File <span class="label-hint">(.csv, .xlsx, .xls)</span></label>
        <input type="file" name="import_file" accept=".csv,.xlsx,.xls" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;"><i class="ti ti-upload"></i> Upload & preview</button>
    </form>
  </div>
</div>

<!-- Import history -->
<?php if (!empty($sessions)): ?>
  <div class="card" style="margin-top:16px;">
    <div class="card-header"><span class="card-title">Import history</span></div>
    <table class="data-table">
      <thead><tr><th>Date</th><th>Source</th><th>File</th><th>Rows</th><th>Imported</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($sessions as $s): ?>
          <tr>
            <td><?= date('M j, Y g:i a', strtotime($s['created_at'])) ?></td>
            <td><span class="pill" style="background:var(--blue-light);color:var(--blue-dark);"><?= ucfirst($s['source']) ?></span></td>
            <td style="font-size:11px; color:var(--text-tertiary);"><?= htmlspecialchars($s['filename'] ?? '—') ?></td>
            <td><?= $s['row_count'] ?></td>
            <td><?= $s['imported'] ?></td>
            <td><span class="pill <?= $s['status'] === 'complete' ? 'pill-green' : 'pill-amber' ?>"><?= ucfirst($s['status']) ?></span></td>
            <td>
              <?php if ($s['status'] === 'processing'): ?>
                <a href="<?= BASE_URL ?>/bank-import/review/<?= $s['id'] ?>" class="action-link">Review</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php if ($plaidKey): ?>
<script src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"></script>
<script>
document.getElementById('plaid-link-btn').addEventListener('click', async function() {
  // Step 1: get link token from our server
  const res = await fetch('<?= BASE_URL ?>/bank-import/plaid/link', {
    method: 'POST',
    body: new URLSearchParams({ csrf_token: '<?= Auth::csrfToken() ?>' })
  });
  const data = await res.json();
  if (data.error) { alert('Error: ' + data.error); return; }

  // Step 2: open Plaid Link
  const handler = Plaid.create({
    token: data.link_token,
    onSuccess: async function(publicToken) {
      const ex = await fetch('<?= BASE_URL ?>/bank-import/plaid/exchange', {
        method: 'POST',
        body: new URLSearchParams({ public_token: publicToken, csrf_token: '<?= Auth::csrfToken() ?>' })
      });
      const exData = await ex.json();
      if (exData.ok) location.reload();
      else alert('Exchange failed: ' + JSON.stringify(exData));
    },
    onExit: function() {}
  });
  handler.open();
});
</script>
<?php endif; ?>
