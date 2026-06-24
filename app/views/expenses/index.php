<?php $pageTitle = 'Expenses'; ?>
<div class="page-header">
  <div><h1 class="page-title">Expenses</h1><p class="page-sub"><?= $total ?> total · Page <?= $page ?> of <?= max(1,$pages) ?></p></div>
  <a href="<?= BASE_URL ?>/expenses/create" class="btn btn-primary"><i class="ti ti-plus"></i> Add expense</a>
</div>

<?php if (empty($expenses)): ?>
  <div class="empty-card">
    <i class="ti ti-credit-card" style="font-size:40px; color:var(--text-tertiary);"></i>
    <p>No expenses yet.</p>
    <a href="<?= BASE_URL ?>/expenses/create" class="btn btn-primary">Add first expense</a>
  </div>
<?php else: ?>
  <div class="card">
    <table class="data-table">
      <thead>
        <tr><th>Date</th><th>Description</th><th>Category</th><th>Amount</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($expenses as $e): ?>
          <tr>
            <td><?= date('M j, Y', strtotime($e['expense_date'])) ?></td>
            <td>
              <?= htmlspecialchars($e['description']) ?>
              <?php if ($e['is_recurring']): ?><span class="pill" style="background:var(--blue-light); color:var(--blue-dark); font-size:10px; margin-left:4px;">Recurring</span><?php endif; ?>
            </td>
            <td>
              <span class="cat-badge" style="background:<?= htmlspecialchars($e['color']) ?>22; color:<?= htmlspecialchars($e['color']) ?>">
                <i class="ti <?= htmlspecialchars($e['icon']) ?>" style="font-size:12px;"></i>
                <?= htmlspecialchars($e['category_name']) ?>
              </span>
            </td>
            <td><strong>$<?= number_format($e['amount'], 2) ?></strong></td>
            <td><span class="pill <?= $e['is_paid'] ? 'pill-green' : 'pill-amber' ?>"><?= $e['is_paid'] ? 'Paid' : 'Due' ?></span></td>
            <td class="actions-cell">
              <a href="<?= BASE_URL ?>/expenses/<?= $e['id'] ?>/edit" class="action-link">Edit</a>
              <?php if (!$e['is_paid']): ?>
                <form method="POST" action="<?= BASE_URL ?>/expenses/<?= $e['id'] ?>/pay" style="display:inline;">
                  <?= Auth::csrfField() ?>
                  <button class="action-link">Pay</button>
                </form>
              <?php endif; ?>
              <form method="POST" action="<?= BASE_URL ?>/expenses/<?= $e['id'] ?>/delete" style="display:inline;"
                    onsubmit="return confirm('Delete this expense?')">
                <?= Auth::csrfField() ?>
                <button class="action-link text-red">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
          <a href="?page=<?= $p ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
