<?php $pageTitle = 'Import Settings'; ?>
<div class="page-header">
  <div><h1 class="page-title">Import Settings</h1><p class="page-sub">Configure column mapping and default categories for bank imports.</p></div>
</div>

<form method="POST" action="<?= BASE_URL ?>/bank-import/settings/save" style="display: grid; gap: 16px;">
  <?= Auth::csrfField() ?>

  <!-- Column Mapping -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="ti ti-columns" style="color:var(--blue); margin-right:5px;"></i>CSV Column Mapping</span>
    </div>
    <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 16px;">
      Define which columns in your CSV/Excel files contain transaction data. This helps us correctly identify dates, amounts, and descriptions.
    </p>

    <div style="display: grid; gap: 12px;">
      <div class="form-group">
        <label for="col-date">Date Column <span class="label-hint">(required)</span></label>
        <select id="col-date" name="column_date" required>
          <option value="">— Select column —</option>
          <option value="date" <?= ($columnMapping['date'] ?? '') === 'date' ? 'selected' : '' ?>>Date</option>
          <option value="transaction_date" <?= ($columnMapping['date'] ?? '') === 'transaction_date' ? 'selected' : '' ?>>Transaction Date</option>
          <option value="posted_date" <?= ($columnMapping['date'] ?? '') === 'posted_date' ? 'selected' : '' ?>>Posted Date</option>
          <option value="col_1" <?= ($columnMapping['date'] ?? '') === 'col_1' ? 'selected' : '' ?>>Column 1</option>
          <option value="col_2" <?= ($columnMapping['date'] ?? '') === 'col_2' ? 'selected' : '' ?>>Column 2</option>
          <option value="col_3" <?= ($columnMapping['date'] ?? '') === 'col_3' ? 'selected' : '' ?>>Column 3</option>
        </select>
      </div>

      <div class="form-group">
        <label for="col-description">Description Column <span class="label-hint">(required)</span></label>
        <select id="col-description" name="column_description" required>
          <option value="">— Select column —</option>
          <option value="description" <?= ($columnMapping['description'] ?? '') === 'description' ? 'selected' : '' ?>>Description</option>
          <option value="memo" <?= ($columnMapping['description'] ?? '') === 'memo' ? 'selected' : '' ?>>Memo</option>
          <option value="reference" <?= ($columnMapping['description'] ?? '') === 'reference' ? 'selected' : '' ?>>Reference</option>
          <option value="col_1" <?= ($columnMapping['description'] ?? '') === 'col_1' ? 'selected' : '' ?>>Column 1</option>
          <option value="col_2" <?= ($columnMapping['description'] ?? '') === 'col_2' ? 'selected' : '' ?>>Column 2</option>
          <option value="col_3" <?= ($columnMapping['description'] ?? '') === 'col_3' ? 'selected' : '' ?>>Column 3</option>
        </select>
      </div>

      <div class="form-group">
        <label for="col-amount">Amount Column <span class="label-hint">(required)</span></label>
        <select id="col-amount" name="column_amount" required>
          <option value="">— Select column —</option>
          <option value="amount" <?= ($columnMapping['amount'] ?? '') === 'amount' ? 'selected' : '' ?>>Amount</option>
          <option value="value" <?= ($columnMapping['amount'] ?? '') === 'value' ? 'selected' : '' ?>>Value</option>
          <option value="debit" <?= ($columnMapping['amount'] ?? '') === 'debit' ? 'selected' : '' ?>>Debit</option>
          <option value="credit" <?= ($columnMapping['amount'] ?? '') === 'credit' ? 'selected' : '' ?>>Credit</option>
          <option value="col_1" <?= ($columnMapping['amount'] ?? '') === 'col_1' ? 'selected' : '' ?>>Column 1</option>
          <option value="col_2" <?= ($columnMapping['amount'] ?? '') === 'col_2' ? 'selected' : '' ?>>Column 2</option>
          <option value="col_3" <?= ($columnMapping['amount'] ?? '') === 'col_3' ? 'selected' : '' ?>>Column 3</option>
        </select>
      </div>

      <div class="form-group">
        <label for="col-account">Account Column <span class="label-hint">(optional)</span></label>
        <select id="col-account" name="column_account">
          <option value="">— Skip (use default) —</option>
          <option value="account" <?= ($columnMapping['account'] ?? '') === 'account' ? 'selected' : '' ?>>Account</option>
          <option value="account_name" <?= ($columnMapping['account'] ?? '') === 'account_name' ? 'selected' : '' ?>>Account Name</option>
          <option value="account_number" <?= ($columnMapping['account'] ?? '') === 'account_number' ? 'selected' : '' ?>>Account Number</option>
          <option value="col_1" <?= ($columnMapping['account'] ?? '') === 'col_1' ? 'selected' : '' ?>>Column 1</option>
          <option value="col_2" <?= ($columnMapping['account'] ?? '') === 'col_2' ? 'selected' : '' ?>>Column 2</option>
          <option value="col_3" <?= ($columnMapping['account'] ?? '') === 'col_3' ? 'selected' : '' ?>>Column 3</option>
        </select>
      </div>
    </div>
  </div>

  <!-- Default Categories -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="ti ti-tag" style="color:var(--orange); margin-right:5px;"></i>Default Categories</span>
    </div>
    <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 16px;">
      Assign default categories to auto-categorize transactions based on keywords in the description.
    </p>

    <div style="display: grid; gap: 12px;">
      <?php for ($i = 1; $i <= 5; $i++): ?>
        <div style="padding: 12px; background: var(--bg-secondary); border-radius: var(--radius-md); display: grid; grid-template-columns: 1fr 150px; gap: 8px; align-items: end;">
          <div class="form-group" style="margin: 0;">
            <label for="keyword-<?= $i ?>" style="font-size: 11px;">Keyword <?= $i ?></label>
            <input type="text" id="keyword-<?= $i ?>" name="keyword_<?= $i ?>" placeholder="e.g., Netflix, Amazon, Starbucks" 
                   value="<?= htmlspecialchars($defaultRules[$i]['keyword'] ?? '') ?>" style="font-size: 12px;">
          </div>
          <div class="form-group" style="margin: 0;">
            <label for="category-<?= $i ?>" style="font-size: 11px;">Category</label>
            <select id="category-<?= $i ?>" name="category_<?= $i ?>" style="font-size: 12px;">
              <option value="">— Select —</option>
              <?php foreach ($categories ?? [] as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($defaultRules[$i]['category_id'] ?? '') === $cat['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- Duplicate Detection -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="ti ti-alert-circle" style="color:var(--orange); margin-right:5px;"></i>Duplicate Detection</span>
    </div>
    <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 16px;">
      Configure how the system identifies and handles duplicate transactions.
    </p>

    <div style="display: grid; gap: 12px;">
      <div class="form-group">
        <label for="dup-days">Match within <span class="label-hint">(days)</span></label>
        <input type="number" id="dup-days" name="duplicate_match_days" min="1" max="30" 
               value="<?= $duplicateSettings['match_days'] ?? 3 ?>" placeholder="3">
        <small style="font-size: 10px; color: var(--text-tertiary); display: block; margin-top: 4px;">
          Transactions within this many days of an existing transaction with the same amount are considered duplicates.
        </small>
      </div>

      <div class="form-group">
        <label><input type="checkbox" name="ignore_cents" value="1" <?= ($duplicateSettings['ignore_cents'] ?? false) ? 'checked' : '' ?> /> 
          Ignore cents in duplicate matching</label>
        <small style="font-size: 10px; color: var(--text-tertiary); display: block; margin-top: 4px;">
          Treat $50.00 and $50.49 as the same amount for duplicate detection.
        </small>
      </div>

      <div class="form-group">
        <label><input type="checkbox" name="ignore_sign" value="1" <?= ($duplicateSettings['ignore_sign'] ?? false) ? 'checked' : '' ?> /> 
          Ignore transaction sign (debit/credit)</label>
        <small style="font-size: 10px; color: var(--text-tertiary); display: block; margin-top: 4px;">
          Treat −$50.00 and +$50.00 as the same transaction.
        </small>
      </div>
    </div>
  </div>

  <!-- Auto-Import Settings -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="ti ti-zap" style="color:var(--green); margin-right:5px;"></i>Auto-Import Options</span>
    </div>
    <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 16px;">
      Automatically handle certain import scenarios to speed up your workflow.
    </p>

    <div style="display: grid; gap: 12px;">
      <div class="form-group">
        <label><input type="checkbox" name="auto_skip_duplicates" value="1" <?= ($autoImportSettings['skip_duplicates'] ?? false) ? 'checked' : '' ?> /> 
          Skip duplicate transactions automatically</label>
      </div>

      <div class="form-group">
        <label><input type="checkbox" name="auto_categorize" value="1" <?= ($autoImportSettings['categorize'] ?? false) ? 'checked' : '' ?> /> 
          Auto-categorize using rules above</label>
      </div>

      <div class="form-group">
        <label><input type="checkbox" name="auto_finalize" value="1" <?= ($autoImportSettings['finalize'] ?? false) ? 'checked' : '' ?> /> 
          Auto-finalize when all transactions are ready</label>
      </div>
    </div>
  </div>

  <!-- Action buttons -->
  <div style="display: flex; gap: 8px; padding-top: 16px;">
    <a href="<?= BASE_URL ?>/bank-import" class="btn" style="flex: 1;">
      <i class="ti ti-arrow-left"></i> Back
    </a>
    <button type="reset" class="btn" style="flex: 1;">
      <i class="ti ti-refresh"></i> Reset
    </button>
    <button type="submit" class="btn btn-primary" style="flex: 1;">
      <i class="ti ti-check"></i> Save Settings
    </button>
  </div>
</form>
