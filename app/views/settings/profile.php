<?php $pageTitle = 'Profile Settings'; ?>
<?php require APP_PATH . '/views/settings/_tabs.php'; ?>

<div class="settings-wrap">

  <!-- Profile info -->
  <div class="card" style="margin-bottom:16px;">
    <div class="settings-section-title">Personal Information</div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error" style="margin-bottom:14px;">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Avatar display -->
    <div style="display:flex; align-items:center; gap:16px; margin-bottom:20px; padding:14px; background:var(--bg-secondary); border-radius:var(--radius-lg);">
      <div style="width:64px; height:64px; border-radius:50%; background:var(--blue-light); display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:700; color:var(--blue-dark); flex-shrink:0;">
        <?= strtoupper(substr($user['first_name'],0,1) . substr($user['last_name'],0,1)) ?>
      </div>
      <div>
        <div style="font-size:16px; font-weight:600; color:var(--text-primary);"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
        <div style="font-size:12px; color:var(--text-secondary);"><?= htmlspecialchars($user['email']) ?></div>
        <div style="margin-top:4px;">
          <?php $roleLabels = [1=>'Administrator', 2=>'Parent / User', 3=>'Read-only Viewer']; ?>
          <span class="pill <?= $user['role_id']==1 ? 'pill-green' : ($user['role_id']==3 ? '' : 'pill-amber') ?>">
            <?= $roleLabels[$user['role_id']] ?? 'User' ?>
          </span>
        </div>
      </div>
      <div style="margin-left:auto; font-size:11px; color:var(--text-tertiary); text-align:right;">
        Avatar auto-generated<br>from your initials
      </div>
    </div>

    <form method="POST" action="<?= BASE_URL ?>/settings/profile">
      <?= Auth::csrfField() ?>
      <div class="form-row">
        <div class="form-group">
          <label>First name</label>
          <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
        </div>
        <div class="form-group">
          <label>Last name</label>
          <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label>Email address</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save profile</button>
      </div>
    </form>
  </div>

  <!-- Change password -->
  <div class="card">
    <div class="settings-section-title">Change Password</div>

    <?php if (!empty($password_errors)): ?>
      <div class="alert alert-error" style="margin-bottom:14px;">
        <?php foreach ($password_errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/settings/password">
      <?= Auth::csrfField() ?>
      <div class="form-group">
        <label>Current password</label>
        <input type="password" name="current_password" autocomplete="current-password" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>New password <span class="label-hint">(min 8 chars)</span></label>
          <input type="password" name="new_password" autocomplete="new-password" required>
        </div>
        <div class="form-group">
          <label>Confirm new password</label>
          <input type="password" name="confirm_password" autocomplete="new-password" required>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Change password</button>
      </div>
    </form>
  </div>

</div>