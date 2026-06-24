<?php $pageTitle = 'Review Import'; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Review transactions</h1>
    <p class="page-sub"><?= count($transactions) ?> transactions from <?= htmlspecialchars($session['filename'] ?? $session['source']) ?></p>
  </div>
  <a href="<?= BASE_URL ?>/bank-import" class="btn">← Cancel</a>
</div>

<p style="font-size:13px; color:var(--text-secondary); margin-bottom:16px;">
  Review each transaction below. Set the category, budget, and whether to import as an expense, income, or skip entirely. Then click <strong>Import all</strong>.
</p>

<form method="POST" action="<?= BASE_URL ?>/bank-import/confirm/<?= $session['id'] ?>">
  <?= Auth::csrfField() ?>

  <div class="card">
    <table class="data-table" style="font-size:12px;">
      <thead>
        <tr>
          <th>Date</th>
          <th>Description</th>
          <th>Amount</th>
          <th>Import as</th>
          <th>Category</th>
          <th>Budget</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($transactions as $t): ?>
          <tr>
            <td style="white-space:nowrap;"><?= date('M j, Y', strtotime($t['raw_date'])) ?></td>
            <td><?= htmlspecialchars($t['raw_description']) ?></td>
            <td style="font-weight:500; color:<?= $t['raw_type'] === 'credit' ? 'var(--green-dark)' : 'var(--text-primary)' ?>">
              <?= $t['raw_type'] === 'credit' ? '+' : '-' ?>$<?= number_format($t['raw_amount'], 2) ?>
            </td>
            <td>
              <select name="mapped_as[<?= $t['id'] ?>]" style="font-size:11px; padding:3px 6px;">
                <option value="expense" <?= $t['mapped_as'] === 'expense' ? 'selected' : '' ?>>Expense</option>
                <option value="income"  <?= $t['mapped_as'] === 'income'  ? 'selected' : '' ?>>Income</option>
                <option value="skip"    <?= $t['mapped_as'] === 'skip'    ? 'selected' : '' ?>>Skip</option>
              </select>
            </td>
            <td>
              <select name="category_id[<?= $t['id'] ?>]" style="font-size:11px; padding:3px 6px;">
                <option value="">— Uncategorised —</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>" <?= $t['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <select name="budget_id[<?= $t['id'] ?>]" style="font-size:11px; padding:3px 6px;">
                <option value="">— None —</option>
                <?php foreach ($budgets as $b): ?>
                  <option value="<?= $b['id'] ?>" <?= $t['budget_id'] == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="form-actions" style="margin-top:14px;">
    <a href="<?= BASE_URL ?>/bank-import" class="btn">Cancel</a>
    <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Import all</button>
  </div>
</form>
