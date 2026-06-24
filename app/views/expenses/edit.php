<?php $pageTitle = 'Edit Expense'; ?>
<div class="page-header">
  <div><h1 class="page-title">Edit expense</h1></div>
  <a href="<?= BASE_URL ?>/expenses" class="btn">← Back</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="form-layout form-layout-narrow">
  <form method="POST" action="<?= BASE_URL ?>/expenses/<?= $expense['id'] ?>/update">
    <?= Auth::csrfField() ?>

    <div class="card form-card">
      <div class="form-group">
        <label for="description">Description</label>
        <input type="text" id="description" name="description" value="<?= htmlspecialchars($expense['description']) ?>" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="amount">Amount ($)</label>
          <input type="number" id="amount" name="amount" step="0.01" min="0.01" value="<?= htmlspecialchars($expense['amount']) ?>" required>
        </div>
        <div class="form-group">
          <label for="expense_date">Date</label>
          <input type="date" id="expense_date" name="expense_date" value="<?= htmlspecialchars($expense['expense_date']) ?>" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="category_id">Category</label>
          <select id="category_id" name="category_id" required>
            <option value="">— Select category —</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>" <?= $expense['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="budget_id">Budget</label>
          <select id="budget_id" name="budget_id">
            <option value="">— No budget —</option>
            <?php foreach ($budgets as $b): ?>
              <option value="<?= $b['id'] ?>" <?= $expense['budget_id'] == $b['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['title']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group form-check">
          <label><input type="checkbox" name="is_paid" value="1" <?= $expense['is_paid'] ? 'checked' : '' ?>> Mark as paid</label>
        </div>
        <div class="form-group form-check">
          <label><input type="checkbox" name="is_recurring" value="1" <?= $expense['is_recurring'] ? 'checked' : '' ?>> Recurring expense</label>
        </div>
      </div>

      <div class="form-group">
        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" rows="2"><?= htmlspecialchars($expense['notes'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="form-actions">
      <form method="POST" action="<?= BASE_URL ?>/expenses/<?= $expense['id'] ?>/delete" style="display:inline;"
            onsubmit="return confirm('Permanently delete this expense?')">
        <?= Auth::csrfField() ?>
        <button type="submit" class="btn btn-danger-outline">Delete</button>
      </form>
      <a href="<?= BASE_URL ?>/expenses" class="btn">Cancel</a>
      <button type="submit" class="btn btn-primary">Update expense</button>
    </div>
  </form>
</div>
