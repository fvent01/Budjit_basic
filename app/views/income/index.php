<?php $pageTitle = 'Income'; ?>
<div class="page-header">
  <div><h1 class="page-title">Income</h1><p class="page-sub">Track all your income sources and entries.</p></div>
  <a href="<?= BASE_URL ?>/income/create" class="btn btn-primary"><i class="ti ti-plus"></i> Add income</a>
</div>

<div class="two-col" style="margin-bottom:16px;">
  <!-- Income entries -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Income entries</span>
    </div>
    <?php if (empty($entries)): ?>
      <p class="empty-state">No income recorded yet. <a href="<?= BASE_URL ?>/income/create">Add your first entry →</a></p>
    <?php else: ?>
      <div class="expense-list">
        <?php foreach ($entries as $e): ?>
          <div class="expense-row">
            <div class="exp-icon" style="background:var(--green-light);">
              <i class="ti ti-cash" style="color:var(--green-dark);"></i>
            </div>
            <div class="exp-info">
              <div class="exp-name"><?= htmlspecialchars($e['description']) ?></div>
              <div class="exp-meta">
                <?= date('M j, Y', strtotime($e['received_date'])) ?>
                <?php if ($e['source_name']): ?> · <?= htmlspecialchars($e['source_name']) ?><?php endif; ?>
                <?php if ($e['is_recurring']): ?><span class="pill" style="background:var(--blue-light); color:var(--blue-dark); font-size:10px; margin-left:4px;">Recurring</span><?php endif; ?>
              </div>
            </div>
            <div class="exp-right">
              <div class="exp-amount text-green">+$<?= number_format($e['amount'], 2) ?></div>
              <div class="actions-inline">
                <a href="<?= BASE_URL ?>/income/<?= $e['id'] ?>/edit" class="action-link">Edit</a>
                <form method="POST" action="<?= BASE_URL ?>/income/<?= $e['id'] ?>/delete" style="display:inline;"
                      onsubmit="return confirm('Delete this income entry?')">
                  <?= Auth::csrfField() ?>
                  <button class="action-link text-red">Delete</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Income sources -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Income sources</span>
    </div>
    <?php if (!empty($sources)): ?>
      <div class="expense-list" style="margin-bottom:16px;">
        <?php foreach ($sources as $s): ?>
          <div class="expense-row">
            <div class="exp-icon" style="background:var(--blue-light);">
              <i class="ti ti-briefcase" style="color:var(--blue-dark);"></i>
            </div>
            <div class="exp-info">
              <div class="exp-name"><?= htmlspecialchars($s['name']) ?></div>
              <div class="exp-meta"><?= ucfirst(str_replace('_', ' ', $s['source_type'])) ?> · <?= ucfirst($s['frequency']) ?></div>
            </div>
            <div class="exp-right">
              <div class="exp-amount">$<?= number_format($s['default_amount'], 2) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="card-divider"></div>
    <p class="form-section-title" style="margin:12px 0 10px;">Add a source</p>
    <form method="POST" action="<?= BASE_URL ?>/income/sources">
      <?= Auth::csrfField() ?>
      <div class="form-group">
        <label>Source name</label>
        <input type="text" name="name" placeholder="e.g. Main job paycheck" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Type</label>
          <select name="source_type">
            <option value="salary">Salary</option>
            <option value="freelance">Freelance</option>
            <option value="side_job">Side job</option>
            <option value="benefit">Benefit</option>
            <option value="child_support">Child support</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label>Frequency</label>
          <select name="frequency">
            <option value="weekly">Weekly</option>
            <option value="biweekly">Bi-weekly</option>
            <option value="monthly">Monthly</option>
            <option value="one_time">One-time</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Default amount ($)</label>
          <input type="number" name="default_amount" step="0.01" min="0" value="0">
        </div>
        <div class="form-group form-check" style="padding-top:24px;">
          <label><input type="checkbox" name="is_recurring" value="1"> Recurring</label>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">Add source</button>
    </form>
  </div>
</div>
