<?php $pageTitle = 'Dashboard'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-sub">
      Week of <?= date('M j', strtotime($weekStart)) ?> – <?= date('M j, Y', strtotime($weekEnd)) ?>
    </p>
  </div>
  <div class="header-actions">
    <div class="week-pill">
      <i class="ti ti-calendar"></i>
      <?= date('M j', strtotime($weekStart)) ?> – <?= date('M j', strtotime($weekEnd)) ?>
    </div>
    <a href="<?= BASE_URL ?>/expenses/create" class="btn btn-primary"><i class="ti ti-plus"></i> Add expense</a>
  </div>
</div>

<!-- Alerts -->
<?php foreach ($alerts as $alert): ?>
  <div class="alert-bar">
    <i class="ti ti-alert-triangle"></i>
    <span><?= htmlspecialchars($alert['category']) ?> spending is <?= $alert['percent'] ?>% of budget — review your allocation.</span>
    <button onclick="this.parentElement.remove()" class="flash-close"><i class="ti ti-x"></i></button>
  </div>
<?php endforeach; ?>

<!-- Metric cards -->
<div class="metrics-grid">
  <div class="metric-card">
    <div class="metric-label">Weekly income</div>
    <div class="metric-value">$<?= number_format($totalIncome, 2) ?></div>
    <div class="metric-delta text-green"><i class="ti ti-cash"></i> This week</div>
    <div class="progress-bg"><div class="progress-fill" style="width:100%; background:var(--green);"></div></div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Total expenses</div>
    <div class="metric-value">$<?= number_format($totalExpenses, 2) ?></div>
    <div class="metric-delta <?= $totalExpenses > $totalIncome ? 'text-red' : 'text-amber' ?>">
      <i class="ti ti-credit-card"></i>
      <?= $totalIncome > 0 ? round(($totalExpenses / $totalIncome) * 100) . '% of income' : 'No income set' ?>
    </div>
    <div class="progress-bg">
      <div class="progress-fill" style="width:<?= $totalIncome > 0 ? min(100, round(($totalExpenses/$totalIncome)*100)) : 0 ?>%;
        background:<?= $totalExpenses > $totalIncome ? 'var(--red)' : 'var(--amber)' ?>;"></div>
    </div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Remaining</div>
    <div class="metric-value <?= $remaining < 0 ? 'text-red' : '' ?>">
      $<?= number_format(abs($remaining), 2) ?><?= $remaining < 0 ? ' over' : '' ?>
    </div>
    <div class="metric-delta <?= $remaining >= 0 ? 'text-green' : 'text-red' ?>">
      <i class="ti <?= $remaining >= 0 ? 'ti-check' : 'ti-alert-circle' ?>"></i>
      <?= $remaining >= 0 ? 'On track' : 'Over budget' ?>
    </div>
    <div class="progress-bg">
      <div class="progress-fill" style="width:<?= $totalIncome > 0 ? min(100, round((max(0,$remaining)/$totalIncome)*100)) : 0 ?>%; background:var(--green);"></div>
    </div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Savings rate</div>
    <div class="metric-value"><?= $savingsRate ?>%</div>
    <div class="metric-delta <?= $savingsRate >= 20 ? 'text-green' : 'text-amber' ?>">
      <i class="ti ti-piggy-bank"></i>
      <?= $savingsRate >= 20 ? 'Above 20% goal' : 'Below 20% goal' ?>
    </div>
    <div class="progress-bg">
      <div class="progress-fill" style="width:<?= min(100, $savingsRate) ?>%; background:var(--blue);"></div>
    </div>
  </div>
</div>

<!-- Charts row -->
<div class="two-col">
  <!-- Weekly bar chart -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Income vs expenses</span>
      <a href="<?= BASE_URL ?>/analytics" class="card-link">Full report →</a>
    </div>
    <?php
      $maxVal = 1;
      foreach ($days as $d) {
          $maxVal = max($maxVal, $d['income'], $d['expense']);
      }
    ?>
    <div class="bar-chart" aria-label="Income and expenses by day this week">
      <?php foreach ($days as $date => $d): ?>
        <div class="bar-group">
          <div class="bar-pair">
            <?php $iH = $maxVal > 0 ? round(($d['income'] / $maxVal) * 70) : 0; ?>
            <?php $eH = $maxVal > 0 ? round(($d['expense'] / $maxVal) * 70) : 0; ?>
            <div class="bar bar-income"  style="height:<?= max(2,$iH) ?>px" title="Income: $<?= number_format($d['income'],2) ?>"></div>
            <div class="bar bar-expense" style="height:<?= max(2,$eH) ?>px" title="Expenses: $<?= number_format($d['expense'],2) ?>"></div>
          </div>
          <div class="bar-label"><?= $d['label'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="chart-legend">
      <span><span class="legend-swatch" style="background:var(--green);"></span>Income</span>
      <span><span class="legend-swatch" style="background:var(--red);"></span>Expenses</span>
    </div>
  </div>

  <!-- Category donut -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Spending by category</span>
      <a href="<?= BASE_URL ?>/expenses" class="card-link">Details →</a>
    </div>
    <?php if (empty($categoryTotals)): ?>
      <p class="empty-state">No expenses recorded this week.</p>
    <?php else: ?>
      <div class="category-list">
        <?php
          $grandTotal = array_sum(array_column($categoryTotals, 'total'));
          foreach ($categoryTotals as $cat):
            $pct = $grandTotal > 0 ? round(($cat['total'] / $grandTotal) * 100) : 0;
        ?>
          <div class="cat-row">
            <div class="cat-dot" style="background:<?= htmlspecialchars($cat['color']) ?>"></div>
            <span class="cat-name"><?= htmlspecialchars($cat['name']) ?></span>
            <div class="cat-bar-bg">
              <div class="cat-bar-fill" style="width:<?= $pct ?>%; background:<?= htmlspecialchars($cat['color']) ?>"></div>
            </div>
            <span class="cat-pct"><?= $pct ?>%</span>
            <span class="cat-amount">$<?= number_format($cat['total'], 2) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Expenses + Bills row -->
<div class="two-col">
  <!-- Recent expenses -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Recent expenses</span>
      <a href="<?= BASE_URL ?>/expenses" class="card-link">All expenses →</a>
    </div>
    <?php if (empty($recentExpenses)): ?>
      <p class="empty-state">No expenses yet. <a href="<?= BASE_URL ?>/expenses/create">Add one →</a></p>
    <?php else: ?>
      <div class="expense-list">
        <?php foreach ($recentExpenses as $e): ?>
          <div class="expense-row">
            <div class="exp-icon" style="background:<?= htmlspecialchars($e['color']) ?>22;">
              <i class="ti <?= htmlspecialchars($e['icon']) ?>" style="color:<?= htmlspecialchars($e['color']) ?>"></i>
            </div>
            <div class="exp-info">
              <div class="exp-name"><?= htmlspecialchars($e['description']) ?></div>
              <div class="exp-meta"><?= date('M j', strtotime($e['expense_date'])) ?> · <?= htmlspecialchars($e['category_name']) ?></div>
            </div>
            <div class="exp-right">
              <div class="exp-amount">$<?= number_format($e['amount'], 2) ?></div>
              <span class="pill <?= $e['is_paid'] ? 'pill-green' : 'pill-amber' ?>">
                <?= $e['is_paid'] ? 'Paid' : 'Due' ?>
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Upcoming bills -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Unpaid bills</span>
      <span class="card-link"><?= count($upcomingBills) ?> pending</span>
    </div>
    <?php if (empty($upcomingBills)): ?>
      <p class="empty-state">All bills are paid.</p>
    <?php else: ?>
      <div class="bills-list">
        <?php foreach (array_slice($upcomingBills, 0, 5) as $bill): ?>
          <div class="bill-row">
            <div class="bill-day">
              <div class="bill-day-num"><?= date('d', strtotime($bill['expense_date'])) ?></div>
              <div class="bill-day-lbl"><?= date('M', strtotime($bill['expense_date'])) ?></div>
            </div>
            <div class="bill-info">
              <div class="bill-name"><?= htmlspecialchars($bill['description']) ?></div>
              <div class="bill-meta"><?= htmlspecialchars($bill['category_name']) ?></div>
            </div>
            <div class="bill-right">
              <div class="bill-amt">$<?= number_format($bill['amount'], 2) ?></div>
              <form method="POST" action="<?= BASE_URL ?>/expenses/<?= $bill['id'] ?>/pay" style="display:inline;">
                <?= Auth::csrfField() ?>
                <button type="submit" class="btn-xs">Mark paid</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Budget vs actual -->
<?php if ($budget && !empty($vsData)): ?>
<div class="card" style="margin-top:16px;">
  <div class="card-header">
    <span class="card-title">Budget: <?= htmlspecialchars($budget['title']) ?></span>
    <a href="<?= BASE_URL ?>/budgets/<?= $budget['id'] ?>" class="card-link">View budget →</a>
  </div>
  <div class="vs-grid">
    <?php foreach ($vsData as $row):
      $pct = $row['allocated'] > 0 ? min(100, round(($row['spent'] / $row['allocated']) * 100)) : 0;
      $over = $row['spent'] > $row['allocated'] && $row['allocated'] > 0;
    ?>
      <div class="vs-row">
        <span class="vs-name"><?= htmlspecialchars($row['name']) ?></span>
        <div class="vs-bar-bg">
          <div class="vs-bar-fill" style="width:<?= $pct ?>%; background:<?= $over ? 'var(--red)' : $row['color'] ?>;"></div>
        </div>
        <span class="vs-numbers">
          $<?= number_format($row['spent'], 0) ?> / $<?= number_format($row['allocated'], 0) ?>
        </span>
        <?php if ($over): ?>
          <span class="pill pill-red">Over</span>
        <?php elseif ($pct >= 90): ?>
          <span class="pill pill-amber">90%+</span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php
// Plugin dashboard widgets
$widgets = PluginLoader::collect('dashboard_widgets');
if (!empty($widgets)):
?>
<div style="margin-top:16px;">
  <div class="section-title" style="margin-bottom:12px;">Plugin Widgets</div>
  <div class="three-col">
    <?php foreach ($widgets as $widget): ?>
      <div><?= $widget ?></div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
