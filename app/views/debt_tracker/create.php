<?php $pageTitle = 'Add Debt'; ?>
<div class="page-header"><div><h1 class="page-title">Add debt</h1></div><a href="<?= BASE_URL ?>/debt-tracker" class="btn">← Back</a></div>
<?php if (!empty($errors)): ?><div class="alert alert-error"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="form-layout form-layout-narrow">
  <form method="POST" action="<?= BASE_URL ?>/debt-tracker">
    <?= Auth::csrfField() ?>
    <div class="card form-card">
      <div class="form-group"><label>Debt name</label><input type="text" name="name" value="<?= htmlspecialchars($debt['name'] ?? '') ?>" placeholder="e.g. Visa Credit Card" required></div>
      <div class="form-row">
        <div class="form-group">
          <label>Type</label>
          <select name="debt_type">
            <?php foreach (['credit_card'=>'Credit Card','student_loan'=>'Student Loan','auto'=>'Auto Loan','medical'=>'Medical','personal'=>'Personal Loan','other'=>'Other'] as $val => $label): ?>
              <option value="<?= $val ?>" <?= ($debt['debt_type'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Current balance ($)</label><input type="number" name="balance" step="0.01" min="0.01" value="<?= htmlspecialchars($debt['balance'] ?? '') ?>" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Interest rate (APR %)</label><input type="number" name="interest_rate" step="0.01" min="0" value="<?= htmlspecialchars($debt['interest_rate'] ?? '0') ?>"></div>
        <div class="form-group"><label>Minimum payment ($/mo)</label><input type="number" name="minimum_payment" step="0.01" min="0" value="<?= htmlspecialchars($debt['minimum_payment'] ?? '0') ?>"></div>
      </div>
      <div class="form-group"><label>Due day of month <span class="label-hint">(optional)</span></label><input type="number" name="due_day" min="1" max="31" value="<?= htmlspecialchars($debt['due_day'] ?? '') ?>"></div>
      <div class="form-group"><label>Notes</label><textarea name="notes" rows="2"><?= htmlspecialchars($debt['notes'] ?? '') ?></textarea></div>
    </div>
    <div class="form-actions"><a href="<?= BASE_URL ?>/debt-tracker" class="btn">Cancel</a><button type="submit" class="btn btn-primary">Add debt</button></div>
  </form>
</div>
