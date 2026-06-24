<?php $pageTitle = 'Analytics'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="page-header">
  <div>
    <h1 class="page-title">Analytics</h1>
    <p class="page-sub">
      <?= date('M j, Y', strtotime($from)) ?> — <?= date('M j, Y', strtotime($to)) ?>
      &nbsp;·&nbsp;
      <span style="color:var(--text-tertiary);"><?= $kpis['period_days'] ?> days</span>
    </p>
  </div>
  <div class="header-actions">
    <!-- Export dropdown -->
    <div style="position:relative;" id="export-wrap">
      <button onclick="document.getElementById('export-menu').classList.toggle('open')" class="btn">
        <i class="ti ti-download"></i> Export CSV <i class="ti ti-chevron-down" style="font-size:11px;"></i>
      </button>
      <div id="export-menu" class="dropdown-menu">
        <?php
        $exports = [
          'income_vs_expenses' => 'Income vs Expenses',
          'by_category'        => 'Spending by Category',
          'month_over_month'   => 'Month-over-Month',
          'debt_progress'      => 'Debt Progress',
          'transactions'       => 'All Transactions',
        ];
        foreach ($exports as $type => $label):
        ?>
          <a href="<?= BASE_URL ?>/analytics/export?type=<?= $type ?>&period=<?= $period ?>&from=<?= $from ?>&to=<?= $to ?>" class="dropdown-item">
            <i class="ti ti-file-text"></i> <?= $label ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Period selector -->
<div class="period-bar">
  <?php foreach (['week'=>'This Week','month'=>'This Month','year'=>'This Year','custom'=>'Custom'] as $p => $label): ?>
    <a href="?period=<?= $p ?>"
       class="period-btn <?= $period === $p ? 'period-active' : '' ?>"><?= $label ?></a>
  <?php endforeach; ?>

  <?php if ($period === 'custom'): ?>
    <form method="GET" style="display:flex; gap:6px; align-items:center; margin-left:8px;">
      <input type="hidden" name="period" value="custom">
      <input type="date" name="from" value="<?= $from ?>" style="font-size:12px; padding:5px 8px;">
      <span style="color:var(--text-tertiary); font-size:12px;">to</span>
      <input type="date" name="to"   value="<?= $to ?>"   style="font-size:12px; padding:5px 8px;">
      <button type="submit" class="btn btn-sm btn-primary">Apply</button>
    </form>
  <?php endif; ?>
</div>

<!-- ── KPI cards ───────────────────────────────────────────── -->
<div class="metrics-grid" style="margin-bottom:20px;">
  <?php
  $kpiCards = [
    ['Income',       '$' . number_format($kpis['income'],2),   $kpis['inc_vs_prev'], 'ti-cash',          'var(--green)'],
    ['Expenses',     '$' . number_format($kpis['expenses'],2), $kpis['exp_vs_prev'], 'ti-credit-card',   'var(--red)'],
    ['Net',          '$' . number_format(abs($kpis['net']),2) . ($kpis['net'] < 0 ? ' deficit' : ''), null, 'ti-trending-up', $kpis['net'] >= 0 ? 'var(--green)' : 'var(--red)'],
    ['Savings Rate', $kpis['savings_rate'] . '%',              null,                 'ti-piggy-bank',    'var(--blue)'],
  ];
  foreach ($kpiCards as [$label, $val, $change, $icon, $color]):
  ?>
    <div class="metric-card">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
        <div class="metric-label"><?= $label ?></div>
        <i class="ti <?= $icon ?>" style="font-size:18px; color:<?= $color ?>; opacity:0.7;"></i>
      </div>
      <div class="metric-value"><?= $val ?></div>
      <?php if ($change !== null): ?>
        <div class="metric-delta <?= $change >= 0 ? 'delta-pos' : 'delta-neg' ?>">
          <i class="ti <?= $change >= 0 ? 'ti-trending-up' : 'ti-trending-down' ?>"></i>
          <?= abs($change) ?>% vs prev period
        </div>
      <?php else: ?>
        <div class="metric-delta" style="color:var(--text-tertiary);">
          <?= $label === 'Savings Rate' ? ($kpis['savings_rate'] >= 20 ? '✓ Above 20% goal' : 'Below 20% goal') : ($kpis['txn_count'] . ' transactions') ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<!-- ── Section 1: Income vs Expenses ────────────────────────── -->
<div class="card analytics-card">
  <div class="card-header">
    <span class="card-title">Income vs Expenses
      <span class="granularity-tag"><?= ucfirst($iveGranularity) ?></span>
    </span>
    <a href="<?= BASE_URL ?>/analytics/export?type=income_vs_expenses&period=<?= $period ?>&from=<?= $from ?>&to=<?= $to ?>" class="card-link"><i class="ti ti-download"></i> CSV</a>
  </div>
  <?php if (empty($iveSeries)): ?>
    <p class="empty-state">No data for this period.</p>
  <?php else: ?>
    <canvas id="ive-chart" height="90"></canvas>
    <?php
      $iveLabels  = json_encode(array_column($iveSeries, 'label'));
      $iveIncome  = json_encode(array_column($iveSeries, 'income'));
      $iveExpense = json_encode(array_column($iveSeries, 'expense'));
      $iveNet     = json_encode(array_column($iveSeries, 'net'));
    ?>
    <script>
    new Chart(document.getElementById('ive-chart'), {
      type: 'bar',
      data: {
        labels: <?= $iveLabels ?>,
        datasets: [
          { label:'Income',   data: <?= $iveIncome ?>,  backgroundColor:'rgba(29,158,117,0.75)', borderRadius:4, order:2 },
          { label:'Expenses', data: <?= $iveExpense ?>, backgroundColor:'rgba(226,75,74,0.65)',  borderRadius:4, order:2 },
          { label:'Net',      data: <?= $iveNet ?>,     type:'line', borderColor:'#378ADD', backgroundColor:'rgba(55,138,221,0.1)', tension:0.3, fill:true, pointRadius:3, order:1 },
        ]
      },
      options: {
        responsive:true,
        interaction:{ mode:'index', intersect:false },
        plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12, font:{size:11} } } },
        scales:{
          x:{ grid:{ display:false }, ticks:{ font:{size:10} } },
          y:{ ticks:{ callback: v => '$'+v.toLocaleString(), font:{size:10} }, grid:{ color:'rgba(0,0,0,0.05)' } }
        }
      }
    });
    </script>
  <?php endif; ?>
</div>

<!-- ── Section 2: Spending by Category ──────────────────────── -->
<div class="two-col" style="margin-top:16px;">
  <div class="card analytics-card">
    <div class="card-header">
      <span class="card-title">Spending by Category</span>
      <a href="<?= BASE_URL ?>/analytics/export?type=by_category&period=<?= $period ?>&from=<?= $from ?>&to=<?= $to ?>" class="card-link"><i class="ti ti-download"></i> CSV</a>
    </div>
    <?php if (empty($byCategory['rows'])): ?>
      <p class="empty-state">No expenses in this period.</p>
    <?php else: ?>
      <canvas id="cat-doughnut" style="max-height:200px; margin:0 auto;"></canvas>
      <div style="display:flex; flex-direction:column; gap:8px; margin-top:16px;">
        <?php foreach ($byCategory['rows'] as $cat): ?>
          <div style="display:flex; align-items:center; gap:8px;">
            <div style="width:10px; height:10px; border-radius:2px; background:<?= htmlspecialchars($cat['color']) ?>; flex-shrink:0;"></div>
            <span style="flex:1; font-size:12px; color:var(--text-secondary);"><?= htmlspecialchars($cat['name']) ?></span>
            <div style="flex:2; height:6px; background:var(--bg-secondary); border-radius:3px;">
              <div style="width:<?= $cat['percent'] ?>%; height:6px; border-radius:3px; background:<?= htmlspecialchars($cat['color']) ?>;"></div>
            </div>
            <span style="font-size:11px; color:var(--text-primary); font-weight:500; width:38px; text-align:right;"><?= $cat['percent'] ?>%</span>
            <span style="font-size:11px; color:var(--text-secondary); width:70px; text-align:right;">$<?= number_format($cat['total'],2) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php
        $catLabels = json_encode(array_column($byCategory['rows'], 'name'));
        $catTotals = json_encode(array_column($byCategory['rows'], 'total'));
        $catColors = json_encode(array_column($byCategory['rows'], 'color'));
      ?>
      <script>
      new Chart(document.getElementById('cat-doughnut'), {
        type:'doughnut',
        data:{
          labels: <?= $catLabels ?>,
          datasets:[{ data: <?= $catTotals ?>, backgroundColor: <?= $catColors ?>, borderWidth:2 }]
        },
        options:{
          responsive:true,
          cutout:'65%',
          plugins:{ legend:{ display:false } }
        }
      });
      </script>
    <?php endif; ?>
  </div>

  <!-- Category trends over time -->
  <div class="card analytics-card">
    <div class="card-header">
      <span class="card-title">Category Trends</span>
      <span style="font-size:11px; color:var(--text-tertiary);">Top 5 categories monthly</span>
    </div>
    <?php if (empty($catTrend) || empty($catTrend['months'])): ?>
      <p class="empty-state">Not enough data for trend lines.</p>
    <?php else: ?>
      <canvas id="cat-trend-chart" height="130"></canvas>
      <?php
        $trendLabels   = json_encode($catTrend['months']);
        $trendDatasets = [];
        foreach ($catTrend['categories'] as $cat) {
            $values = [];
            foreach ($catTrend['month_keys'] as $mo) {
                $values[] = (float)($catTrend['by_month'][$mo][$cat['id']] ?? 0);
            }
            $trendDatasets[] = [
                'label'           => $cat['name'],
                'data'            => $values,
                'borderColor'     => $cat['color'],
                'backgroundColor' => $cat['color'] . '22',
                'tension'         => 0.3,
                'fill'            => false,
                'pointRadius'     => 3,
            ];
        }
      ?>
      <script>
      new Chart(document.getElementById('cat-trend-chart'), {
        type:'line',
        data:{ labels: <?= $trendLabels ?>, datasets: <?= json_encode($trendDatasets) ?> },
        options:{
          responsive:true,
          interaction:{ mode:'index', intersect:false },
          plugins:{ legend:{ position:'bottom', labels:{ boxWidth:10, font:{size:10} } } },
          scales:{
            x:{ grid:{ display:false }, ticks:{ font:{size:10} } },
            y:{ ticks:{ callback: v=>'$'+v.toLocaleString(), font:{size:10} }, grid:{ color:'rgba(0,0,0,0.05)' } }
          }
        }
      });
      </script>
    <?php endif; ?>
  </div>
</div>

<!-- ── Section 3: Budget vs Actual ──────────────────────────── -->
<div class="card analytics-card" style="margin-top:16px;">
  <div class="card-header">
    <span class="card-title">Budget vs Actual</span>
    <span style="font-size:11px; color:var(--text-tertiary);">Active budgets overlapping this period</span>
  </div>
  <?php if (empty($budgetVsActual)): ?>
    <p class="empty-state">No active budgets for this period. <a href="<?= BASE_URL ?>/budgets/create" class="action-link">Create one →</a></p>
  <?php else: ?>
    <?php foreach ($budgetVsActual as $bva): ?>
      <div style="margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
          <div>
            <div style="font-size:14px; font-weight:500; color:var(--text-primary);"><?= htmlspecialchars($bva['budget']['title']) ?></div>
            <div style="font-size:11px; color:var(--text-tertiary);">
              <?= date('M j', strtotime($bva['budget']['start_date'])) ?> – <?= date('M j, Y', strtotime($bva['budget']['end_date'])) ?>
              &nbsp;·&nbsp; Allocated: $<?= number_format($bva['total_allocated'],2) ?>
              &nbsp;·&nbsp; Spent: $<?= number_format($bva['total_spent'],2) ?>
            </div>
          </div>
          <?php
            $overallPct = $bva['total_allocated'] > 0
              ? min(100, round(($bva['total_spent'] / $bva['total_allocated']) * 100)) : 0;
          ?>
          <span class="pill <?= $overallPct >= 100 ? 'pill-red' : ($overallPct >= 80 ? 'pill-amber' : 'pill-green') ?>">
            <?= $overallPct ?>% used
          </span>
        </div>

        <div style="display:flex; flex-direction:column; gap:8px;">
          <?php foreach ($bva['items'] as $item): ?>
            <div style="display:flex; align-items:center; gap:10px;">
              <span style="width:110px; font-size:12px; color:var(--text-secondary); flex-shrink:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($item['name']) ?></span>
              <div style="flex:1; height:8px; background:var(--bg-secondary); border-radius:4px; position:relative;">
                <div style="
                  width:<?= $item['percent'] ?>%;
                  height:8px; border-radius:4px;
                  background:<?= $item['status'] === 'over' ? 'var(--red)' : ($item['status'] === 'warning' ? 'var(--amber)' : htmlspecialchars($item['color'])) ?>;
                  transition:width 0.5s ease;
                "></div>
              </div>
              <span style="font-size:11px; color:var(--text-secondary); width:115px; text-align:right; flex-shrink:0;">
                $<?= number_format($item['spent'],2) ?> / $<?= number_format($item['allocated'],2) ?>
              </span>
              <?php if ($item['status'] !== 'ok'): ?>
                <span class="pill <?= $item['status'] === 'over' ? 'pill-red' : 'pill-amber' ?>" style="flex-shrink:0;">
                  <?= $item['status'] === 'over' ? 'Over' : $item['percent'].'%' ?>
                </span>
              <?php else: ?>
                <span style="width:44px; flex-shrink:0;"></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- ── Section 4: Month-over-Month ──────────────────────────── -->
<div class="card analytics-card" style="margin-top:16px;">
  <div class="card-header">
    <span class="card-title">Month-over-Month Trends</span>
    <a href="<?= BASE_URL ?>/analytics/export?type=month_over_month&period=<?= $period ?>&from=<?= $from ?>&to=<?= $to ?>" class="card-link"><i class="ti ti-download"></i> CSV</a>
  </div>
  <?php if (empty($momTrends)): ?>
    <p class="empty-state">No data yet.</p>
  <?php else: ?>
    <canvas id="mom-chart" height="80" style="margin-bottom:20px;"></canvas>
    <?php
      $momLabels   = json_encode(array_column($momTrends, 'label'));
      $momIncome   = json_encode(array_column($momTrends, 'income'));
      $momExpenses = json_encode(array_column($momTrends, 'expenses'));
      $momSavings  = json_encode(array_column($momTrends, 'savings_rate'));
    ?>
    <script>
    new Chart(document.getElementById('mom-chart'), {
      type:'line',
      data:{
        labels: <?= $momLabels ?>,
        datasets:[
          { label:'Income',   data: <?= $momIncome ?>,   borderColor:'#1D9E75', backgroundColor:'rgba(29,158,117,0.08)', tension:0.3, fill:true, pointRadius:3 },
          { label:'Expenses', data: <?= $momExpenses ?>, borderColor:'#E24B4A', backgroundColor:'rgba(226,75,74,0.08)',  tension:0.3, fill:true, pointRadius:3 },
        ]
      },
      options:{
        responsive:true,
        interaction:{ mode:'index', intersect:false },
        plugins:{ legend:{ position:'bottom', labels:{ boxWidth:10, font:{size:11} } } },
        scales:{
          x:{ grid:{ display:false }, ticks:{ font:{size:10} } },
          y:{ ticks:{ callback: v=>'$'+v.toLocaleString(), font:{size:10} }, grid:{ color:'rgba(0,0,0,0.05)' } }
        }
      }
    });
    </script>

    <!-- Summary table -->
    <div style="overflow-x:auto;">
      <table class="data-table" style="font-size:12px;">
        <thead>
          <tr>
            <th>Month</th><th>Income</th><th>Expenses</th><th>Net</th>
            <th>Savings %</th><th>Inc Δ</th><th>Exp Δ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_reverse($momTrends) as $row): ?>
            <tr>
              <td style="font-weight:500;"><?= $row['label'] ?></td>
              <td style="color:var(--green-dark);">$<?= number_format($row['income'],2) ?></td>
              <td>$<?= number_format($row['expenses'],2) ?></td>
              <td style="color:<?= $row['net'] >= 0 ? 'var(--green-dark)' : 'var(--red-dark)' ?>; font-weight:500;">
                <?= $row['net'] >= 0 ? '+' : '-' ?>$<?= number_format(abs($row['net']),2) ?>
              </td>
              <td>
                <span class="pill <?= $row['savings_rate'] >= 20 ? 'pill-green' : ($row['savings_rate'] >= 10 ? 'pill-amber' : 'pill-red') ?>">
                  <?= $row['savings_rate'] ?>%
                </span>
              </td>
              <td style="color:<?= ($row['inc_change_pct'] ?? 0) >= 0 ? 'var(--green-dark)' : 'var(--red-dark)' ?>">
                <?= $row['inc_change_pct'] !== null ? (($row['inc_change_pct'] >= 0 ? '+' : '') . $row['inc_change_pct'] . '%') : '—' ?>
              </td>
              <td style="color:<?= ($row['exp_change_pct'] ?? 0) <= 0 ? 'var(--green-dark)' : 'var(--red-dark)' ?>">
                <?= $row['exp_change_pct'] !== null ? (($row['exp_change_pct'] >= 0 ? '+' : '') . $row['exp_change_pct'] . '%') : '—' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ── Section 5: Debt Payoff Progress ──────────────────────── -->
<div class="card analytics-card" style="margin-top:16px;">
  <div class="card-header">
    <span class="card-title">Debt Payoff Progress</span>
    <a href="<?= BASE_URL ?>/analytics/export?type=debt_progress&period=<?= $period ?>&from=<?= $from ?>&to=<?= $to ?>" class="card-link"><i class="ti ti-download"></i> CSV</a>
  </div>
  <?php if (empty($debtProgress['debts'])): ?>
    <p class="empty-state">No debts tracked. <a href="<?= BASE_URL ?>/debt-tracker/create" class="action-link">Add one →</a></p>
  <?php else: ?>
    <div class="metrics-grid" style="margin-bottom:16px;">
      <div class="metric-card">
        <div class="metric-label">Total original debt</div>
        <div class="metric-value">$<?= number_format($debtProgress['total_original'],2) ?></div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Total remaining</div>
        <div class="metric-value text-red">$<?= number_format($debtProgress['total_remaining'],2) ?></div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Total paid off</div>
        <div class="metric-value text-green">$<?= number_format($debtProgress['total_paid'],2) ?></div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Overall progress</div>
        <div class="metric-value"><?= $debtProgress['percent_paid'] ?>%</div>
        <div class="progress-bg" style="margin-top:8px;">
          <div class="progress-fill" style="width:<?= $debtProgress['percent_paid'] ?>%; background:var(--green);"></div>
        </div>
      </div>
    </div>

    <!-- Per-debt progress bars -->
    <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:20px;">
      <?php foreach ($debtProgress['debts'] as $debt):
        $pct = $debt['original_balance'] > 0
          ? min(100, round((1 - $debt['balance'] / $debt['original_balance']) * 100)) : 100;
      ?>
        <div>
          <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
            <div style="display:flex; align-items:center; gap:8px;">
              <span style="font-size:13px; font-weight:500; color:var(--text-primary);"><?= htmlspecialchars($debt['name']) ?></span>
              <?php if ($debt['is_paid_off']): ?>
                <span class="pill pill-green">Paid off!</span>
              <?php endif; ?>
            </div>
            <span style="font-size:12px; color:var(--text-secondary);">
              $<?= number_format($debt['balance'],2) ?> remaining of $<?= number_format($debt['original_balance'],2) ?>
            </span>
          </div>
          <div style="height:10px; background:var(--bg-secondary); border-radius:5px;">
            <div style="width:<?= $pct ?>%; height:10px; border-radius:5px; background:<?= $debt['is_paid_off'] ? 'var(--green)' : 'var(--red)' ?>; transition:width 0.5s ease;"></div>
          </div>
          <div style="font-size:10px; color:var(--text-tertiary); margin-top:2px;"><?= $pct ?>% paid · <?= $debt['interest_rate'] ?>% APR</div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Monthly payment history chart -->
    <?php if (!empty($debtProgress['monthly'])): ?>
      <canvas id="debt-chart" height="60"></canvas>
      <?php
        $debtLabels   = json_encode(array_map(fn($r) => date('M Y', strtotime($r['mo'].'-01')), $debtProgress['monthly']));
        $debtPayments = json_encode(array_column($debtProgress['monthly'], 'total'));
      ?>
      <script>
      new Chart(document.getElementById('debt-chart'), {
        type:'bar',
        data:{
          labels: <?= $debtLabels ?>,
          datasets:[{
            label:'Monthly payments',
            data: <?= $debtPayments ?>,
            backgroundColor:'rgba(226,75,74,0.65)',
            borderRadius:4
          }]
        },
        options:{
          responsive:true,
          plugins:{ legend:{ display:false } },
          scales:{
            x:{ grid:{ display:false }, ticks:{ font:{size:10} } },
            y:{ ticks:{ callback: v=>'$'+v.toLocaleString(), font:{size:10} }, grid:{ color:'rgba(0,0,0,0.05)' } }
          }
        }
      });
      </script>
    <?php endif; ?>
  <?php endif; ?>
</div>

<style>
.period-bar { display:flex; align-items:center; gap:4px; margin-bottom:18px; flex-wrap:wrap; }
.period-btn { padding:5px 12px; border-radius:99px; font-size:12px; font-weight:500; text-decoration:none; color:var(--text-secondary); background:var(--bg-primary); border:0.5px solid var(--border); transition:background 0.12s; }
.period-btn:hover { background:var(--bg-secondary); }
.period-active { background:var(--green); color:#fff; border-color:var(--green); }
.analytics-card { margin-bottom:0; }
.granularity-tag { font-size:10px; font-weight:400; color:var(--text-tertiary); background:var(--bg-secondary); padding:1px 6px; border-radius:99px; margin-left:6px; }
.dropdown-menu { display:none; position:absolute; right:0; top:calc(100% + 4px); background:var(--bg-primary); border:0.5px solid var(--border); border-radius:var(--radius-md); min-width:200px; z-index:50; box-shadow:0 4px 16px rgba(0,0,0,0.12); }
.dropdown-menu.open { display:block; }
.dropdown-item { display:flex; align-items:center; gap:8px; padding:8px 12px; font-size:13px; color:var(--text-secondary); text-decoration:none; transition:background 0.1s; }
.dropdown-item:hover { background:var(--bg-secondary); color:var(--text-primary); }
.dropdown-item i { font-size:15px; }
</style>

<script>
// Close export dropdown when clicking outside
document.addEventListener('click', function(e) {
  const wrap = document.getElementById('export-wrap');
  if (wrap && !wrap.contains(e.target)) {
    document.getElementById('export-menu').classList.remove('open');
  }
});
</script>
