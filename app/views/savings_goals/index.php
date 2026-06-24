<?php $pageTitle = 'Savings Goals'; ?>
<div class="page-header">
  <div><h1 class="page-title">Savings Goals</h1><p class="page-sub">Track your progress toward every financial target.</p></div>
  <a href="<?= BASE_URL ?>/savings-goals/create" class="btn btn-primary"><i class="ti ti-plus"></i> New goal</a>
</div>

<?php if (empty($goals)): ?>
  <div class="empty-card">
    <i class="ti ti-piggy-bank" style="font-size:44px; color:var(--text-tertiary);"></i>
    <p>No savings goals yet. Create your first one to start tracking progress.</p>
    <a href="<?= BASE_URL ?>/savings-goals/create" class="btn btn-primary">Create goal</a>
  </div>
<?php else: ?>
  <div class="goals-grid" id="goals-sortable">
    <?php foreach ($goals as $goal):
      $pct = $goal['target_amount'] > 0 ? min(100, round(($goal['current_amount'] / $goal['target_amount']) * 100, 1)) : 0;
      $remaining = max(0, $goal['target_amount'] - $goal['current_amount']);
    ?>
      <div class="goal-card <?= $goal['is_completed'] ? 'goal-completed' : '' ?>" data-id="<?= $goal['id'] ?>">
        <div class="goal-card-top">
          <div class="goal-icon" style="background:<?= htmlspecialchars($goal['color']) ?>22; color:<?= htmlspecialchars($goal['color']) ?>">
            <i class="ti <?= htmlspecialchars($goal['icon']) ?>"></i>
          </div>
          <div class="goal-info">
            <div class="goal-name"><?= htmlspecialchars($goal['name']) ?></div>
            <?php if ($goal['target_date']): ?>
              <div class="goal-date"><i class="ti ti-calendar" style="font-size:11px;"></i> <?= date('M j, Y', strtotime($goal['target_date'])) ?></div>
            <?php endif; ?>
          </div>
          <?php if ($goal['is_completed']): ?>
            <span class="pill pill-green" style="margin-left:auto;">Complete!</span>
          <?php else: ?>
            <span class="goal-drag-handle" title="Drag to reorder"><i class="ti ti-grip-vertical" style="color:var(--text-tertiary); font-size:16px; cursor:grab;"></i></span>
          <?php endif; ?>
        </div>

        <div class="goal-amounts">
          <span class="goal-current">$<?= number_format($goal['current_amount'], 2) ?></span>
          <span class="goal-sep">of</span>
          <span class="goal-target">$<?= number_format($goal['target_amount'], 2) ?></span>
        </div>

        <div class="progress-bg" style="height:8px; margin:8px 0;">
          <div class="progress-fill" style="width:<?= $pct ?>%; height:8px; background:<?= htmlspecialchars($goal['color']) ?>; border-radius:4px;"></div>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:11px; color:var(--text-secondary);">
          <span><?= $pct ?>% funded</span>
          <span>$<?= number_format($remaining, 2) ?> to go</span>
        </div>

        <?php if ($goal['auto_allocate']): ?>
          <div class="goal-auto-tag"><i class="ti ti-refresh" style="font-size:11px;"></i> Auto <?= $goal['auto_percent'] ?>% of income</div>
        <?php endif; ?>

        <!-- Quick contribute form -->
        <?php if (!$goal['is_completed']): ?>
          <form method="POST" action="<?= BASE_URL ?>/savings-goals/<?= $goal['id'] ?>/contribute" class="contribute-form">
            <?= Auth::csrfField() ?>
            <input type="number" name="amount" step="0.01" min="0.01" placeholder="Amount" class="contribute-input" required>
            <input type="text"   name="note"   placeholder="Note (optional)" class="contribute-note">
            <button type="submit" class="btn btn-sm btn-primary">Add</button>
          </form>
        <?php endif; ?>

        <div class="goal-card-actions">
          <a href="<?= BASE_URL ?>/savings-goals/<?= $goal['id'] ?>/edit" class="action-link">Edit</a>
          <form method="POST" action="<?= BASE_URL ?>/savings-goals/<?= $goal['id'] ?>/delete" style="display:inline;"
                onsubmit="return confirm('Delete this goal?')">
            <?= Auth::csrfField() ?>
            <button class="action-link text-red">Delete</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<style>
.goals-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px,1fr)); gap: 14px; }
.goal-card { background: var(--bg-primary); border: 0.5px solid var(--border); border-radius: var(--radius-lg); padding: 15px; display: flex; flex-direction: column; gap: 8px; }
.goal-completed { opacity: 0.7; }
.goal-card-top { display: flex; align-items: flex-start; gap: 10px; }
.goal-icon { width: 36px; height: 36px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.goal-name { font-size: 14px; font-weight: 500; color: var(--text-primary); }
.goal-date { font-size: 11px; color: var(--text-tertiary); margin-top: 1px; }
.goal-amounts { display: flex; align-items: baseline; gap: 5px; }
.goal-current { font-size: 20px; font-weight: 600; color: var(--text-primary); }
.goal-sep { font-size: 12px; color: var(--text-tertiary); }
.goal-target { font-size: 13px; color: var(--text-secondary); }
.goal-auto-tag { font-size: 11px; color: var(--green-dark); background: var(--green-light); padding: 2px 8px; border-radius: 99px; display: inline-flex; align-items: center; gap: 4px; width: fit-content; }
.contribute-form { display: flex; gap: 6px; align-items: center; }
.contribute-input { width: 90px !important; padding: 5px 7px !important; font-size: 12px !important; }
.contribute-note  { flex: 1; padding: 5px 7px !important; font-size: 12px !important; }
.goal-card-actions { display: flex; gap: 10px; padding-top: 4px; border-top: 0.5px solid var(--border); }
.goal-drag-handle { margin-left: auto; cursor: grab; }
</style>

<script>
// Drag-to-reorder (lightweight — no library needed for small lists)
(function() {
  const grid = document.getElementById('goals-sortable');
  if (!grid) return;

  let dragging = null;

  grid.querySelectorAll('.goal-card').forEach(card => {
    card.setAttribute('draggable', 'true');
    card.addEventListener('dragstart', () => { dragging = card; card.style.opacity = '0.5'; });
    card.addEventListener('dragend',   () => { dragging = null; card.style.opacity = '1'; saveOrder(); });
    card.addEventListener('dragover',  e => {
      e.preventDefault();
      const after = getDragAfter(grid, e.clientY);
      if (after == null) grid.appendChild(dragging);
      else grid.insertBefore(dragging, after);
    });
  });

  function getDragAfter(container, y) {
    const cards = [...container.querySelectorAll('.goal-card:not([style*="opacity: 0.5"])')];
    return cards.reduce((closest, child) => {
      const box = child.getBoundingClientRect();
      const offset = y - box.top - box.height / 2;
      return offset < 0 && offset > closest.offset ? { offset, element: child } : closest;
    }, { offset: Number.NEGATIVE_INFINITY }).element;
  }

  function saveOrder() {
    const order = {};
    grid.querySelectorAll('.goal-card').forEach((card, i) => order[i] = card.dataset.id);
    const form = new FormData();
    Object.entries(order).forEach(([k, v]) => form.append('order[' + k + ']', v));
    form.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fetch('<?= BASE_URL ?>/savings-goals/reorder', { method: 'POST', body: form });
  }
})();
</script>
