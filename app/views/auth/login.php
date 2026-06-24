<?php $pageTitle = 'Sign in'; ?>
<div class="auth-card">
  <h1 class="auth-title">Sign in</h1>
  <p class="auth-sub">Welcome back — enter your details below.</p>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="<?= BASE_URL ?>/auth/login" novalidate>
    <?= Auth::csrfField() ?>

    <div class="form-group">
      <label for="email">Email address</label>
      <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>"
             placeholder="you@family.com" required autocomplete="email">
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>

    <button type="submit" class="btn-full">Sign in</button>
  </form>

  <p class="auth-switch">Don't have an account? <a href="<?= BASE_URL ?>/auth/register">Create one</a></p>
</div>
