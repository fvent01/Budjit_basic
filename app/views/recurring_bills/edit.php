<?php $pageTitle = 'Edit Bill'; ?>
<div class="page-header"><div><h1 class="page-title">Edit bill</h1><p class="page-sub"><?= htmlspecialchars($bill['name']) ?></p></div><a href="<?= BASE_URL ?>/recurring-bills" class="btn">← Back</a></div>
<?php if (!empty($errors)): ?><div class="alert alert-error"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="form-layout form-layout-narrow">
  <form method="POST" action="<?= BASE_URL ?>/recurring-bills/<?= $bill['id'] ?>/update">
    <?= Auth::csrfField() ?>
    <div class="card form-card">
      <div class="form-group"><label>Name</label><input type="text" name="name" value="<?= htmlspecialchars($bill['name']) ?>" required></div>
      <div class="form-row">
        <div class="form-group"><label>Category</label><input type="text" name="category" value="<?= htmlspecialchars($bill['category']) ?>"></div>
        <div class="form-group"><label>Amount ($)</label><input type="number" name="amount" step="0.01" min="0.01" value="<?= htmlspecialchars($bill['amount']) ?>" required></div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Frequency</label>
          <select name="frequency">
            <?php foreach (['weekly'=>'Weekly','biweekly'=>'Bi-weekly','monthly'=>'Monthly','quarterly'=>'Quarterly','annually'=>'Annually'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= $bill['frequency'] === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Due day</label><input type="number" name="due_day" min="1" max="31" value="<?= htmlspecialchars($bill['due_day']) ?>" required></div>
      </div>
      <div class="form-group"><label>Billing URL</label><input type="url" name="billing_url" value="<?= htmlspecialchars($bill['billing_url'] ?? '') ?>"></div>
      <div class="form-row">
        <div class="form-group"><label>Icon</label><input type="text" name="icon" value="<?= htmlspecialchars($bill['icon']) ?>"></div>
        <div class="form-group"><label>Color</label><input type="color" name="color" value="<?= htmlspecialchars($bill['color']) ?>" style="height:38px; cursor:pointer;"></div>
      </div>
      <div class="form-group form-check"><label><input type="checkbox" name="auto_pay" value="1" <?= $bill['auto_pay'] ? 'checked' : '' ?>> Auto-pay enabled</label></div>
      <div class="form-group"><label>Notes</label><textarea name="notes" rows="2"><?= htmlspecialchars($bill['notes'] ?? '') ?></textarea></div>
    </div>
    <div class="form-actions">
      <form method="POST" action="<?= BASE_URL ?>/recurring-bills/<?= $bill['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Delete this bill?')">
        <?= Auth::csrfField() ?><button type="submit" class="btn btn-danger-outline">Delete</button>
      </form>
      <a href="<?= BASE_URL ?>/recurring-bills" class="btn">Cancel</a>
      <button type="submit" class="btn btn-primary">Update bill</button>
    </div>
  </form>
</div>
