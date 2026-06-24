<?php $pageTitle = 'Add Recurring Bill'; ?>
<div class="page-header"><div><h1 class="page-title">Add recurring bill</h1></div><a href="<?= BASE_URL ?>/recurring-bills" class="btn">← Back</a></div>
<?php if (!empty($errors)): ?><div class="alert alert-error"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="form-layout form-layout-narrow">
  <form method="POST" action="<?= BASE_URL ?>/recurring-bills">
    <?= Auth::csrfField() ?>
    <div class="card form-card">
      <div class="form-group"><label>Bill / subscription name</label><input type="text" name="name" value="<?= htmlspecialchars($bill['name'] ?? '') ?>" placeholder="e.g. Netflix" required></div>
      <div class="form-row">
        <div class="form-group"><label>Category</label><input type="text" name="category" value="<?= htmlspecialchars($bill['category'] ?? 'Subscription') ?>" placeholder="Subscription"></div>
        <div class="form-group"><label>Amount ($)</label><input type="number" name="amount" step="0.01" min="0.01" value="<?= htmlspecialchars($bill['amount'] ?? '') ?>" required></div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Frequency</label>
          <select name="frequency">
            <?php foreach (['weekly'=>'Weekly','biweekly'=>'Bi-weekly','monthly'=>'Monthly','quarterly'=>'Quarterly','annually'=>'Annually'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= ($bill['frequency'] ?? 'monthly') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Due day of month</label><input type="number" name="due_day" min="1" max="31" value="<?= htmlspecialchars($bill['due_day'] ?? '1') ?>" required></div>
      </div>
      <div class="form-group"><label>Billing URL <span class="label-hint">(optional)</span></label><input type="url" name="billing_url" value="<?= htmlspecialchars($bill['billing_url'] ?? '') ?>" placeholder="https://"></div>
      <div class="form-row">
        <div class="form-group"><label>Icon class</label><input type="text" name="icon" value="<?= htmlspecialchars($bill['icon'] ?? 'ti-refresh') ?>"></div>
        <div class="form-group"><label>Color</label><input type="color" name="color" value="<?= htmlspecialchars($bill['color'] ?? '#378ADD') ?>" style="height:38px; cursor:pointer;"></div>
      </div>
      <div class="form-group form-check"><label><input type="checkbox" name="auto_pay" value="1" <?= !empty($bill['auto_pay']) ? 'checked' : '' ?>> Auto-pay enabled</label></div>
      <div class="form-group"><label>Notes</label><textarea name="notes" rows="2"><?= htmlspecialchars($bill['notes'] ?? '') ?></textarea></div>
    </div>
    <div class="form-actions"><a href="<?= BASE_URL ?>/recurring-bills" class="btn">Cancel</a><button type="submit" class="btn btn-primary">Save bill</button></div>
  </form>
</div>
