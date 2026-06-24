<?php $pageTitle = 'Recurring Bills'; ?>
<div class="page-header">
  <div><h1 class="page-title">Recurring Bills & Subscriptions</h1><p class="page-sub">Monthly cost: <strong>$<?= number_format($monthly, 2) ?></strong></p></div>
  <a href="<?= BASE_URL ?>/recurring-bills/create" class="btn btn-primary"><i class="ti ti-plus"></i> Add bill</a>
</div>

<?php if (!empty($unpaid)): ?>
  <div class="card" style="margin-bottom:16px; border-color:var(--amber);">
    <div class="card-header"><span class="card-title" style="color:var(--amber-dark);"><i class="ti ti-alert-triangle"></i> Unpaid in next 30 days</span></div>
    <div style="display:flex; flex-direction:column; gap:8px;">
      <?php foreach ($unpaid as $log): ?>
        <div style="display:flex; align-items:center; gap:10px;">
          <div style="width:8px; height:8px; border-radius:2px; background:<?= htmlspecialchars($log['color']) ?>; flex-shrink:0;"></div>
          <span style="flex:1; font-size:13px; color:var(--text-primary); font-weight:500;"><?= htmlspecialchars($log['name']) ?></span>
          <span style="font-size:12px; color:var(--text-secondary);"><?= date('M j', strtotime($log['due_date'])) ?></span>
          <span style="font-size:13px; font-weight:500;">$<?= number_format($log['amount'],2) ?></span>
          <form method="POST" action="<?= BASE_URL ?>/recurring-bills/<?= $log['id'] ?>/pay">
            <?= Auth::csrfField() ?><button class="btn btn-sm">Mark paid</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php if (empty($bills)): ?>
  <div class="empty-card"><i class="ti ti-refresh" style="font-size:40px;color:var(--text-tertiary);"></i><p>No recurring bills yet.</p><a href="<?= BASE_URL ?>/recurring-bills/create" class="btn btn-primary">Add first bill</a></div>
<?php else: ?>
  <div class="card">
    <table class="data-table">
      <thead><tr><th>Name</th><th>Category</th><th>Amount</th><th>Frequency</th><th>Due Day</th><th>Monthly Cost</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($bills as $b):
          $monthlyCost = match($b['frequency']) {
            'weekly'    => $b['amount'] * 4.33,
            'biweekly'  => $b['amount'] * 2.17,
            'quarterly' => $b['amount'] / 3,
            'annually'  => $b['amount'] / 12,
            default     => $b['amount'],
          };
        ?>
          <tr>
            <td>
              <span style="display:flex; align-items:center; gap:7px;">
                <i class="ti <?= htmlspecialchars($b['icon']) ?>" style="color:<?= htmlspecialchars($b['color']) ?>; font-size:16px;"></i>
                <strong><?= htmlspecialchars($b['name']) ?></strong>
              </span>
            </td>
            <td><?= htmlspecialchars($b['category']) ?></td>
            <td>$<?= number_format($b['amount'],2) ?></td>
            <td><?= ucfirst($b['frequency']) ?></td>
            <td><?= $b['due_day'] ?><?= date('S', mktime(0,0,0,1,$b['due_day'])) ?> of month</td>
            <td>$<?= number_format($monthlyCost,2) ?></td>
            <td class="actions-cell">
              <a href="<?= BASE_URL ?>/recurring-bills/<?= $b['id'] ?>/edit" class="action-link">Edit</a>
              <form method="POST" action="<?= BASE_URL ?>/recurring-bills/<?= $b['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Delete this bill?')">
                <?= Auth::csrfField() ?><button class="action-link text-red">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
