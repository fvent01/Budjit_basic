<?php $pageTitle = 'Edit Income'; ?>
<div class="page-header">
  <div><h1 class="page-title">Edit income</h1></div>
  <a href="<?= BASE_URL ?>/income" class="btn">← Back</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="form-layout form-layout-narrow">
  <form method="POST" action="<?= BASE_URL ?>/income/<?= $entry['id'] ?>/update">
    <?= Auth::csrfField() ?>
    <div class="card form-card">

      <div class="form-group">
        <label for="description">Description</label>
        <input type="text" id="description" name="description" value="<?= htmlspecialchars($entry['description']) ?>" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="amount">Amount ($)</label>
          <input type="number" id="amount" name="amount" step="0.01" min="0.01" value="<?= htmlspecialchars($entry['amount']) ?>" required>
        </div>
        <div class="form-group">
          <label for="received_date">Date received</label>
          <input type="date" id="received_date" name="received_date" value="<?= htmlspecialchars($entry['received_date']) ?>" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="income_source_id">Source</label>
          <select id="income_source_id" name="income_source_id">
            <option value="">— Select source —</option>
            <?php foreach ($sources as $s): ?>
              <option value="<?= $s['id'] ?>" <?= $entry['income_source_id'] == $s['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="budget_id">Budget</label>
          <select id="budget_id" name="budget_id">
            <option value="">— No budget —</option>
            <?php foreach ($budgets as $b): ?>
              <option value="<?= $b['id'] ?>" <?= $entry['budget_id'] == $b['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['title']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group form-check">
        <label><input type="checkbox" name="is_recurring" value="1" <?= $entry['is_recurring'] ? 'checked' : '' ?>> Recurring income</label>
      </div>

      <div class="form-group">
        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" rows="2"><?= htmlspecialchars($entry['notes'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="form-actions">
      <form method="POST" action="<?= BASE_URL ?>/income/<?= $entry['id'] ?>/delete" style="display:inline;"
            onsubmit="return confirm('Delete this income entry?')">
        <?= Auth::csrfField() ?>
        <button type="submit" class="btn btn-danger-outline">Delete</button>
      </form>
      <a href="<?= BASE_URL ?>/income" class="btn">Cancel</a>
      <button type="submit" class="btn btn-primary">Update income</button>
    </div>
  </form>
</div>
