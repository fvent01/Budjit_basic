<?php $pageTitle = 'Reminders'; ?>
<div class="page-header">
  <div><h1 class="page-title">Reminders</h1><p class="page-sub">All your scheduled reminders.</p></div>
  <a href="<?= BASE_URL ?>/calendar" class="btn">← Calendar</a>
</div>
<?php if (empty($reminders)): ?>
  <div class="empty-card"><i class="ti ti-bell" style="font-size:40px;color:var(--text-tertiary);"></i><p>No reminders yet.</p><a href="<?= BASE_URL ?>/calendar" class="btn btn-primary">Add one from calendar</a></div>
<?php else: ?>
  <div class="card">
    <table class="data-table">
      <thead><tr><th>Title</th><th>Date</th><th>Channels</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($reminders as $r): ?>
          <tr>
            <td><strong><?= htmlspecialchars($r['title']) ?></strong><?php if ($r['notes']): ?><div style="font-size:11px;color:var(--text-tertiary);"><?= htmlspecialchars($r['notes']) ?></div><?php endif; ?></td>
            <td><?= date('M j, Y', strtotime($r['remind_date'])) ?></td>
            <td><?= htmlspecialchars($r['channels']) ?></td>
            <td>
              <?php if ($r['is_dismissed']): ?>
                <span class="pill" style="background:var(--bg-secondary);color:var(--text-tertiary);">Dismissed</span>
              <?php elseif ($r['is_sent']): ?>
                <span class="pill pill-green">Sent</span>
              <?php else: ?>
                <span class="pill pill-amber">Pending</span>
              <?php endif; ?>
            </td>
            <td class="actions-cell">
              <form method="POST" action="<?= BASE_URL ?>/reminders/<?= $r['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Delete this reminder?')">
                <?= Auth::csrfField() ?><button class="action-link text-red">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
