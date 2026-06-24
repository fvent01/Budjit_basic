<?php $pageTitle = 'User Management'; ?>
<?php require APP_PATH . '/views/settings/_tabs.php'; ?>

<div class="settings-wrap" style="max-width:860px;">
  
  <!-- Create New User -->
  <div class="card" style="margin-bottom:14px;">
    <div class="card-header"><span class="card-title">Create New User</span></div>
    
    <?php if (!empty($create_errors)): ?>
      <div style="background:var(--red-light); border:0.5px solid var(--red); border-radius:var(--radius-md); padding:10px 14px; margin-bottom:12px; font-size:12px; color:var(--red-dark);">
        <strong>Errors:</strong>
        <ul style="margin:4px 0 0 20px; padding:0;">
          <?php foreach ($create_errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/settings/users/create">
      <?= Auth::csrfField() ?>
      <div class="form-row">
        <div class="form-group">
          <label>First Name</label>
          <input type="text" name="first_name" required>
        </div>
        <div class="form-group">
          <label>Last Name</label>
          <input type="text" name="last_name" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" required>
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Min 8 characters" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Role</label>
          <select name="role_id" required>
            <option value="1">Admin</option>
            <option value="2" selected>Parent / User</option>
            <option value="3">Viewer</option>
          </select>
        </div>
        <div class="form-group"></div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="ti ti-plus"></i> Create User</button>
    </form>
  </div>

  <!-- Users List -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">All Users</span>
      <span style="font-size:11px; color:var(--text-tertiary);"><?= count($users) ?> registered</span>
    </div>
    <table class="data-table">
      <thead>
        <tr><th>User</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div style="display:flex; align-items:center; gap:9px;">
                <div style="width:28px; height:28px; border-radius:50%; background:var(--blue-light); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:600; color:var(--blue-dark); flex-shrink:0;">
                  <?= strtoupper(substr($u['first_name'],0,1) . substr($u['last_name'],0,1)) ?>
                </div>
                <div>
                  <div style="font-size:13px; font-weight:500; color:var(--text-primary);"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></div>
                  <?php if ($u['id'] === Auth::id()): ?>
                    <div style="font-size:10px; color:var(--text-tertiary);">You</div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td style="font-size:12px;"><?= htmlspecialchars($u['email']) ?></td>
            <td>
              <?php if ($u['id'] === Auth::id()): ?>
                <span class="pill pill-green"><?= htmlspecialchars($u['role_label']) ?></span>
              <?php else: ?>
                <form method="POST" action="<?= BASE_URL ?>/settings/users/role" style="display:inline;">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <select name="role_id" onchange="this.form.submit()" style="font-size:11px; padding:3px 6px;">
                    <option value="1" <?= $u['role_id']==1 ? 'selected':'' ?>>Admin</option>
                    <option value="2" <?= $u['role_id']==2 ? 'selected':'' ?>>Parent / User</option>
                    <option value="3" <?= $u['role_id']==3 ? 'selected':'' ?>>Viewer</option>
                  </select>
                </form>
              <?php endif; ?>
            </td>
            <td>
              <span class="pill <?= $u['is_active'] ? 'pill-green' : 'pill-red' ?>">
                <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
            </td>
            <td style="font-size:11px; color:var(--text-tertiary);"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
            <td class="actions-cell">
              <?php if ($u['id'] !== Auth::id()): ?>
                <button class="action-link" onclick="toggleEditUser(<?= $u['id'] ?>)" title="Edit user">
                  <i class="ti ti-pencil"></i>
                </button>
                <form method="POST" action="<?= BASE_URL ?>/settings/users/toggle" style="display:inline;"
                      onsubmit="return confirm('<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> this user?')">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button class="action-link <?= $u['is_active'] ? 'text-red' : 'text-green' ?>" title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>">
                    <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                  </button>
                </form>
                <form method="POST" action="<?= BASE_URL ?>/settings/users/<?= $u['id'] ?>/delete" style="display:inline;"
                      onsubmit="return confirm('Delete this user permanently?')">
                  <?= Auth::csrfField() ?>
                  <button class="action-link text-red" title="Delete user">
                    <i class="ti ti-trash"></i>
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
          
          <!-- Edit User Row (hidden by default) -->
          <?php if (($edit_user_id ?? 0) === $u['id']): ?>
            <tr style="background:var(--bg-secondary);">
              <td colspan="6">
                <div style="padding:14px; border:0.5px solid var(--border); border-radius:var(--radius-md); background:var(--bg-primary);">
                  <div style="font-size:13px; font-weight:600; margin-bottom:12px;">Edit User: <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></div>
                  
                  <?php if (!empty($edit_errors)): ?>
                    <div style="background:var(--red-light); border:0.5px solid var(--red); border-radius:var(--radius-md); padding:10px 14px; margin-bottom:12px; font-size:12px; color:var(--red-dark);">
                      <strong>Errors:</strong>
                      <ul style="margin:4px 0 0 20px; padding:0;">
                        <?php foreach ($edit_errors as $err): ?>
                          <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  <?php endif; ?>
                  
                  <form method="POST" action="<?= BASE_URL ?>/settings/users/<?= $u['id'] ?>/update">
                    <?= Auth::csrfField() ?>
                    <div class="form-row">
                      <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($u['first_name']) ?>" required>
                      </div>
                      <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($u['last_name']) ?>" required>
                      </div>
                    </div>
                    <div class="form-group">
                      <label>Email</label>
                      <input type="email" name="email" value="<?= htmlspecialchars($u['email']) ?>" required>
                    </div>
                    <div style="display:flex; gap:8px; margin-top:12px;">
                      <button type="submit" class="btn btn-primary" style="font-size:12px; padding:6px 12px;"><i class="ti ti-check"></i> Save</button>
                      <button type="button" class="btn" style="font-size:12px; padding:6px 12px; background:var(--bg-secondary); color:var(--text-secondary);" onclick="toggleEditUser(<?= $u['id'] ?>)"><i class="ti ti-x"></i> Cancel</button>
                    </div>
                  </form>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Info -->
  <div class="card" style="margin-top:14px;">
    <div style="font-size:11px; color:var(--text-tertiary); line-height:1.6;">
      <strong>Roles:</strong><br>
      <strong>Admin</strong> — full access including settings.<br>
      <strong>Parent/User</strong> — can create and edit all budget data.<br>
      <strong>Viewer</strong> — read-only, cannot add or change anything.
    </div>
  </div>
</div>

<script>
function toggleEditUser(userId) {
  // Simple toggle - in a real app you might use a modal
  document.querySelectorAll('tr[style*="bg-secondary"]').forEach(row => {
    if (!row.textContent.includes('Edit User: ') || !row.innerHTML.includes('value="' + userId + '"')) {
      row.style.display = 'none';
    }
  });
  
  const editRow = document.querySelector(`tr[style*="bg-secondary"]`);
  if (editRow) {
    editRow.style.display = editRow.style.display === 'none' ? '' : 'none';
  }
}
</script>