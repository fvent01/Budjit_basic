<?php
if (!Auth::check()) return;
$reminderModel = new ReminderModel();
$upcoming      = $reminderModel->getUpcoming(Auth::id(), 7);
if (empty($upcoming)) return;
?>
<div class="card" style="margin-bottom:14px;">
  <div class="card-header">
    <span class="card-title"><i class="ti ti-calendar-event" style="color:var(--amber); margin-right:5px;"></i>Upcoming Reminders</span>
    <a href="<?= BASE_URL ?>/calendar" class="card-link">Calendar →</a>
  </div>
  <?php foreach (array_slice($upcoming, 0, 4) as $r): ?>
    <div style="display:flex; align-items:center; gap:9px; margin-bottom:8px;">
      <div style="width:32px; text-align:center; flex-shrink:0;">
        <div style="font-size:14px; font-weight:600; color:var(--text-primary); line-height:1;"><?= date('d', strtotime($r['remind_date'])) ?></div>
        <div style="font-size:9px; color:var(--text-tertiary);"><?= date('M', strtotime($r['remind_date'])) ?></div>
      </div>
      <div style="flex:1; font-size:12px; color:var(--text-primary);"><?= htmlspecialchars($r['title']) ?></div>
      <span style="font-size:10px; color:var(--text-tertiary);"><?= htmlspecialchars($r['channels']) ?></span>
    </div>
  <?php endforeach; ?>
</div>
