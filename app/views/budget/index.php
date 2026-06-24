<?php $pageTitle = 'Budgets'; ?>
<div class="page-header">
  <div><h1 class="page-title">Budgets</h1><p class="page-sub">Manage your weekly and monthly budgets.</p></div>
  <a href="<?= BASE_URL ?>/budgets/create" class="btn btn-primary"><i class="ti ti-plus"></i> New budget</a>
</div>

<?php if (empty($active) && empty($archived)): ?>
  <div class="empty-card">
    <i class="ti ti-wallet" style="font-size:40px; color:var(--text-tertiary);"></i>
    <p>No budgets yet. Create your first one to get started.</p>
    <a href="<?= BASE_URL ?>/budgets/create" class="btn btn-primary">Create budget</a>
  </div>
<?php else: ?>

  <h2 class="section-title">Active</h2>
  <?php if (empty($active)): ?>
    <p class="empty-state">No active budgets.</p>
  <?php else: ?>
    <div class="budget-grid">
      <?php foreach ($active as $b): ?>
        <div class="budget-card">
          <div class="budget-card-header">
            <div>
              <div class="budget-card-title"><?= htmlspecialchars($b['title']) ?></div>
              <div class="budget-card-dates"><?= date('M j', strtotime($b['start_date'])) ?> – <?= date('M j, Y', strtotime($b['end_date'])) ?></div>
            </div>
            <span class="pill pill-green"><?= ucfirst($b['period_type']) ?></span>
          </div>
          <div class="budget-card-amounts">
            <div><div class="amount-label">Budget</div><div class="amount-val">$<?= number_format($b['total_budget'], 2) ?></div></div>
            <div><div class="amount-label">Income</div><div class="amount-val">$<?= number_format($b['total_income'], 2) ?></div></div>
          </div>
          <div class="budget-card-actions">
            <a href="<?= BASE_URL ?>/budgets/<?= $b['id'] ?>" class="btn btn-sm">View</a>
            <a href="<?= BASE_URL ?>/budgets/<?= $b['id'] ?>/edit" class="btn btn-sm">Edit</a>
            <form method="POST" action="<?= BASE_URL ?>/budgets/<?= $b['id'] ?>/duplicate" style="display:inline;">
              <?= Auth::csrfField() ?>
              <button class="btn btn-sm">Duplicate</button>
            </form>
            <form method="POST" action="<?= BASE_URL ?>/budgets/<?= $b['id'] ?>/archive" style="display:inline;"
                  onsubmit="return confirm('Archive this budget?')">
              <?= Auth::csrfField() ?>
              <button class="btn btn-sm btn-danger-outline">Archive</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($archived)): ?>
    <h2 class="section-title" style="margin-top:28px;">Archived</h2>
    <div class="budget-grid">
      <?php foreach ($archived as $b): ?>
        <div class="budget-card budget-card-archived">
          <div class="budget-card-header">
            <div>
              <div class="budget-card-title"><?= htmlspecialchars($b['title']) ?></div>
              <div class="budget-card-dates"><?= date('M j', strtotime($b['start_date'])) ?> – <?= date('M j, Y', strtotime($b['end_date'])) ?></div>
            </div>
            <span class="pill" style="background:var(--bg-secondary); color:var(--text-secondary);">Archived</span>
          </div>
          <div class="budget-card-actions">
            <a href="<?= BASE_URL ?>/budgets/<?= $b['id'] ?>" class="btn btn-sm">View</a>
            <form method="POST" action="<?= BASE_URL ?>/budgets/<?= $b['id'] ?>/delete" style="display:inline;"
                  onsubmit="return confirm('Permanently delete this budget?')">
              <?= Auth::csrfField() ?>
              <button class="btn btn-sm btn-danger-outline">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
