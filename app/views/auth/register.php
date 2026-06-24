<?php $pageTitle = 'Create account'; ?>
<div class="auth-card">
  <h1 class="auth-title">Create account</h1>
  <p class="auth-sub">Start managing your family budget today.</p>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="<?= BASE_URL ?>/auth/register" novalidate>
    <?= Auth::csrfField() ?>

    <div class="form-row">
      <div class="form-group">
        <label for="first_name">First name</label>
        <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($first_name ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label for="last_name">Last name</label>
        <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($last_name ?? '') ?>" required>
      </div>
    </div>

    <div class="form-group">
      <label for="email">Email address</label>
      <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required autocomplete="email">
    </div>

    <div class="form-group">
      <label for="password">Password <span class="label-hint">(min 8 characters)</span></label>
      <input type="password" id="password" name="password" required autocomplete="new-password">
    </div>

    <div class="form-group">
      <label for="password_confirm">Confirm password</label>
      <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
    </div>

    <button type="submit" class="btn-full">Create account</button>
  </form>

  <p class="auth-switch">Already have an account? <a href="<?= BASE_URL ?>/auth/login">Sign in</a></p>
</div>
