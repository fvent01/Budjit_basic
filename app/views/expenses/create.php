<?php $pageTitle = 'Add Expense'; ?>
<div class="page-header">
  <div><h1 class="page-title">Add expense</h1></div>
  <a href="<?= BASE_URL ?>/expenses" class="btn">← Back</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="form-layout form-layout-narrow">
  <form method="POST" action="<?= BASE_URL ?>/expenses">
    <?= Auth::csrfField() ?>

    <div class="card form-card">
      <div class="form-group">
        <label for="description">Description</label>
        <input type="text" id="description" name="description" value="<?= htmlspecialchars($expense['description'] ?? '') ?>" required placeholder="e.g. Weekly groceries">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="amount">Amount ($)</label>
          <input type="number" id="amount" name="amount" step="0.01" min="0.01" value="<?= htmlspecialchars($expense['amount'] ?? '') ?>" required placeholder="0.00">
        </div>
        <div class="form-group">
          <label for="expense_date">Date</label>
          <input type="date" id="expense_date" name="expense_date" value="<?= htmlspecialchars($expense['expense_date'] ?? date('Y-m-d')) ?>" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="category_id">Category</label>
          <select id="category_id" name="category_id" required>
            <option value="">— Select category —</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>" <?= ($expense['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="budget_id">Budget <span class="label-hint">(optional)</span></label>
          <select id="budget_id" name="budget_id">
            <option value="">— No budget —</option>
            <?php foreach ($budgets as $b): ?>
              <option value="<?= $b['id'] ?>"
                <?= ($expense['budget_id'] ?? $budget['id'] ?? '') == $b['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['title']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group form-check">
          <label><input type="checkbox" name="is_paid" value="1" <?= !empty($expense['is_paid']) ? 'checked' : '' ?>> Mark as paid</label>
        </div>
        <div class="form-group form-check">
          <label><input type="checkbox" name="is_recurring" value="1" <?= !empty($expense['is_recurring']) ? 'checked' : '' ?>> Recurring expense</label>
        </div>
      </div>

      <div class="form-group">
        <label for="notes">Notes <span class="label-hint">(optional)</span></label>
        <textarea id="notes" name="notes" rows="2" placeholder="Any additional details..."><?= htmlspecialchars($expense['notes'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="form-actions">
      <a href="<?= BASE_URL ?>/expenses" class="btn">Cancel</a>
      <button type="submit" class="btn btn-primary">Save expense</button>
    </div>
  </form>
</div>
