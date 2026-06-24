<?php $pageTitle = htmlspecialchars($budget['title']); ?>
<div class="page-header">
  <div>
    <h1 class="page-title"><?= htmlspecialchars($budget['title']) ?></h1>
    <p class="page-sub"><?= date('M j', strtotime($budget['start_date'])) ?> – <?= date('M j, Y', strtotime($budget['end_date'])) ?> · <?= ucfirst($budget['period_type']) ?></p>
  </div>
  <div class="header-actions">
    <a href="<?= BASE_URL ?>/budgets/<?= $budget['id'] ?>/edit" class="btn">Edit</a>
    <form method="POST" action="<?= BASE_URL ?>/budgets/<?= $budget['id'] ?>/archive" style="display:inline;"
          onsubmit="return confirm('Archive this budget?')">
      <?= Auth::csrfField() ?>
      <button class="btn">Archive</button>
    </form>
    <a href="<?= BASE_URL ?>/budgets" class="btn">← Budgets</a>
  </div>
</div>

<!-- Summary bar -->
<div class="metrics-grid" style="margin-bottom:16px;">
  <div class="metric-card">
    <div class="metric-label">Total income</div>
    <div class="metric-value">$<?= number_format($totalIncome, 2) ?></div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Total expenses</div>
    <div class="metric-value">$<?= number_format($totalExpenses, 2) ?></div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Remaining</div>
    <div class="metric-value <?= ($totalIncome - $totalExpenses) < 0 ? 'text-red' : '' ?>">
      $<?= number_format(abs($totalIncome - $totalExpenses), 2) ?>
      <?= ($totalIncome - $totalExpenses) < 0 ? ' over' : '' ?>
    </div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Allocated budget</div>
    <div class="metric-value">$<?= number_format($budget['total_budget'], 2) ?></div>
  </div>
</div>

<div class="two-col">
  <!-- Category vs actual -->
  <div class="card">
    <div class="card-header"><span class="card-title">Budget vs actual</span></div>
    <?php if (empty($vsData)): ?>
      <p class="empty-state">No data yet.</p>
    <?php else: ?>
      <div class="vs-grid">
        <?php foreach ($vsData as $row):
          $pct  = $row['allocated'] > 0 ? min(100, round(($row['spent'] / $row['allocated']) * 100)) : 0;
          $over = $row['spent'] > $row['allocated'] && $row['allocated'] > 0;
        ?>
          <div class="vs-row">
            <span class="vs-name"><?= htmlspecialchars($row['name']) ?></span>
            <div class="vs-bar-bg">
              <div class="vs-bar-fill" style="width:<?= $pct ?>%; background:<?= $over ? 'var(--red)' : htmlspecialchars($row['color']) ?>;"></div>
            </div>
            <span class="vs-numbers">$<?= number_format($row['spent'],0) ?> / $<?= number_format($row['allocated'],0) ?></span>
            <?php if ($over): ?><span class="pill pill-red">Over</span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Income entries -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Income</span>
      <a href="<?= BASE_URL ?>/income/create" class="card-link">+ Add</a>
    </div>
    <?php if (empty($income)): ?>
      <p class="empty-state">No income recorded for this budget.</p>
    <?php else: ?>
      <div class="expense-list">
        <?php foreach ($income as $inc): ?>
          <div class="expense-row">
            <div class="exp-icon" style="background:var(--green-light);">
              <i class="ti ti-cash" style="color:var(--green-dark);"></i>
            </div>
            <div class="exp-info">
              <div class="exp-name"><?= htmlspecialchars($inc['description']) ?></div>
              <div class="exp-meta"><?= date('M j', strtotime($inc['received_date'])) ?> · <?= htmlspecialchars($inc['source_name'] ?? 'Manual') ?></div>
            </div>
            <div class="exp-right">
              <div class="exp-amount text-green">+$<?= number_format($inc['amount'], 2) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- All expenses for this budget -->
<div class="card" style="margin-top:16px;">
  <div class="card-header">
    <span class="card-title">Expenses</span>
    <a href="<?= BASE_URL ?>/expenses/create" class="card-link">+ Add expense</a>
  </div>
  <?php if (empty($expenses)): ?>
    <p class="empty-state">No expenses recorded for this budget. <a href="<?= BASE_URL ?>/expenses/create">Add one →</a></p>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr><th>Date</th><th>Description</th><th>Category</th><th>Amount</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($expenses as $e): ?>
          <tr>
            <td><?= date('M j', strtotime($e['expense_date'])) ?></td>
            <td><?= htmlspecialchars($e['description']) ?></td>
            <td>
              <span class="cat-badge" style="background:<?= htmlspecialchars($e['color']) ?>22; color:<?= htmlspecialchars($e['color']) ?>">
                <?= htmlspecialchars($e['category_name']) ?>
              </span>
            </td>
            <td>$<?= number_format($e['amount'], 2) ?></td>
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
  <?php endif; ?>
</div>
