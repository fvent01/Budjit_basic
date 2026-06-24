<?php
// plugins/savings-goals/views/widget.php
if (!Auth::check()) return;
$goalModel = new SavingsGoalModel();
$goals = $goalModel->getForUser(Auth::id());
$active = array_filter($goals, fn($g) => !$g['is_completed']);
if (empty($active)) return;
?>
<div class="card" style="margin-bottom:14px;">
  <div class="card-header">
    <span class="card-title"><i class="ti ti-piggy-bank" style="color:var(--green); margin-right:5px;"></i>Savings Goals</span>
    <a href="<?= BASE_URL ?>/savings-goals" class="card-link">All goals →</a>
  </div>
  <div style="display:flex; flex-direction:column; gap:10px;">
    <?php foreach (array_slice($active, 0, 3) as $goal):
      $pct = $goal['target_amount'] > 0 ? min(100, round(($goal['current_amount'] / $goal['target_amount']) * 100, 1)) : 0;
    ?>
      <div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
          <span style="font-size:12px; font-weight:500; color:var(--text-primary);"><?= htmlspecialchars($goal['name']) ?></span>
          <span style="font-size:11px; color:var(--text-secondary);">$<?= number_format($goal['current_amount'],2) ?> / $<?= number_format($goal['target_amount'],2) ?></span>
        </div>
        <div class="progress-bg" style="height:6px;">
          <div class="progress-fill" style="width:<?= $pct ?>%; height:6px; background:<?= htmlspecialchars($goal['color']) ?>;"></div>
        </div>
        <div style="font-size:10px; color:var(--text-tertiary); margin-top:2px;"><?= $pct ?>% funded</div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
