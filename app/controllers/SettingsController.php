<?php
// app/controllers/SettingsController.php

class SettingsController extends Controller
{
    private SettingsModel $settings;
    private UserModel     $users;

    public function __construct()
    {
        $this->settings = new SettingsModel();
        $this->users    = new UserModel();
    }

    // ── GET /settings ─────────────────────────────────────────
    public function index(): void
    {
        Auth::requireLogin();
        // Admin sees app settings, others see their profile
        $target = Auth::isAdmin() ? 'settings/general' : 'settings/profile';
        $this->redirect($target);
    }

    // ── GET /settings/general ─────────────────────────────────
    public function general(): void
    {
        Auth::requireAdmin();
        $global     = $this->settings->getAll();
        $timezones  = $this->settings->getTimezones();
        $dateFormats= $this->settings->getDateFormats();
        $currencies = $this->settings->getCurrencies();
        $months     = $this->settings->getMonths();
        $this->view('settings.general', compact(
            'global', 'timezones', 'dateFormats', 'currencies', 'months'
        ));
    }

    // ── POST /settings/general ────────────────────────────────
    public function saveGeneral(): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();

        $currency = $this->input('currency', 'USD');
        $currencies = $this->settings->getCurrencies();
        $symbol   = $currencies[$currency]['symbol'] ?? '$';

        $fiscalMonth = $this->input('fiscal_year_month', '01');
        $fiscalDay   = $this->input('fiscal_year_day',   '01');
        $fiscalStart = str_pad($fiscalMonth, 2, '0', STR_PAD_LEFT) . '-' . str_pad($fiscalDay, 2, '0', STR_PAD_LEFT);

        $this->settings->setMany([
            'app_name'            => $this->sanitize($this->input('app_name', 'Budjit')),
            'app_slogan'          => $this->sanitize($this->input('app_slogan', 'Family Plan')),
            'sidebar_footer_text' => $this->sanitize($this->input('sidebar_footer_text', '')),
            'app_icon'            => $this->sanitize($this->input('app_icon', 'ti-home-dollar')),
            'currency'            => $currency,
            'currency_symbol'     => $symbol,
            'currency_position'   => in_array($this->input('currency_position'), ['before','after']) ? $this->input('currency_position') : 'before',
            'week_start_day'      => in_array($this->input('week_start_day'), ['monday','sunday','saturday']) ? $this->input('week_start_day') : 'monday',
            'date_format'         => $this->input('date_format', 'M j, Y'),
            'timezone'            => $this->input('timezone', 'America/Chicago'),
            'fiscal_year_start'   => $fiscalStart,
        ]);

        $this->flashSuccess('General settings saved.');
        $this->redirect('settings/general');
    }

    // ── GET /settings/appearance ──────────────────────────────
    public function appearance(): void
    {
        Auth::requireLogin();
        $userId  = Auth::id();
        $theme   = $this->settings->getUserPref($userId, 'theme',
                   $this->settings->get('theme', 'system'));
        $colorScheme = $this->settings->getUserPref($userId, 'color_scheme',
                       $this->settings->get('color_scheme', 'default'));
        $primaryColor = $this->settings->getUserPref($userId, 'primary_color',
                        $this->settings->get('primary_color', '#1e40af'));
        $accentColor = $this->settings->getUserPref($userId, 'accent_color',
                       $this->settings->get('accent_color', '#059669'));
        $this->view('settings.appearance', compact('theme', 'colorScheme', 'primaryColor', 'accentColor'));
    }

    // ── POST /settings/appearance ─────────────────────────────
    public function saveAppearance(): void
    {
        Auth::requireLogin();
        Auth::verifyCsrf();

        $userId = Auth::id();
        $theme = $this->input('theme', 'system');
        if (!in_array($theme, ['light', 'dark', 'system'])) $theme = 'system';

        $colorScheme = $this->input('color_scheme', 'default');
        if (!in_array($colorScheme, ['default', 'modern', 'forest', 'ocean', 'sunset'])) {
            $colorScheme = 'default';
        }

        $primaryColor = $this->input('primary_color_hex', $this->input('primary_color', '#1e40af'));
        $accentColor = $this->input('accent_color_hex', $this->input('accent_color', '#059669'));

        // Validate hex colors
        if (!preg_match('/^#[0-9a-f]{6}$/i', $primaryColor)) $primaryColor = '#1e40af';
        if (!preg_match('/^#[0-9a-f]{6}$/i', $accentColor)) $accentColor = '#059669';

        $this->settings->setUserPref($userId, 'theme', $theme);
        $this->settings->setUserPref($userId, 'color_scheme', $colorScheme);
        $this->settings->setUserPref($userId, 'primary_color', $primaryColor);
        $this->settings->setUserPref($userId, 'accent_color', $accentColor);

        // Admin can also set the global defaults
        if (Auth::isAdmin() && $this->input('set_global')) {
            $this->settings->set('theme', $theme);
            $this->settings->set('color_scheme', $colorScheme);
            $this->settings->set('primary_color', $primaryColor);
            $this->settings->set('accent_color', $accentColor);
        }

        $this->flashSuccess('Appearance preferences saved.');
        $this->redirect('settings/appearance');
    }

    // ── GET /settings/profile ─────────────────────────────────
    public function profile(): void
    {
        Auth::requireLogin();
        $user = $this->users->find(Auth::id());
        $this->view('settings.profile', compact('user'));
    }

    // ── POST /settings/profile ────────────────────────────────
    public function saveProfile(): void
    {
        Auth::requireLogin();
        Auth::verifyCsrf();

        $userId    = Auth::id();
        $firstName = $this->sanitize($this->input('first_name', ''));
        $lastName  = $this->sanitize($this->input('last_name',  ''));
        $email     = strtolower(trim($this->input('email', '')));

        $errors = [];
        if (!$firstName)                              $errors[] = 'First name is required.';
        if (!$lastName)                               $errors[] = 'Last name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

        // Check email not taken by another user
        $existing = $this->users->findByEmail($email);
        if ($existing && $existing['id'] !== $userId) {
            $errors[] = 'That email address is already in use.';
        }

        if ($errors) {
            $user = $this->users->find($userId);
            $this->view('settings.profile', ['user' => $user, 'errors' => $errors]);
            return;
        }

        $this->users->update($userId, [
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
        ]);

        // Update session name
        $_SESSION['user_name'] = $firstName . ' ' . $lastName;

        $this->flashSuccess('Profile updated successfully.');
        $this->redirect('settings/profile');
    }

    // ── POST /settings/password ───────────────────────────────
    public function savePassword(): void
    {
        Auth::requireLogin();
        Auth::verifyCsrf();

        $userId  = Auth::id();
        $current = $this->input('current_password', '');
        $new     = $this->input('new_password',     '');
        $confirm = $this->input('confirm_password', '');

        $user   = $this->users->find($userId);
        $errors = [];

        if (!$this->users->verifyPassword($current, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        }
        if (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }
        if ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        }

        if ($errors) {
            $this->view('settings.profile', ['user' => $user, 'password_errors' => $errors]);
            return;
        }

        $this->users->updatePassword($userId, $new);
        $this->flashSuccess('Password changed successfully.');
        $this->redirect('settings/profile');
    }

    // ── GET /settings/users (admin only) ─────────────────────
    public function users(): void
    {
        Auth::requireAdmin();
        $users = $this->users->getAllWithRoles();
        $this->view('settings.users', compact('users'));
    }

    // ── POST /settings/users/toggle ──────────────────────────
    public function toggleUser(): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();

        $targetId = (int)$this->input('user_id', 0);
        if ($targetId === Auth::id()) {
            $this->flashError('You cannot deactivate your own account.');
            $this->redirect('settings/users');
            return;
        }

        $user = $this->users->find($targetId);
        if (!$user) { $this->redirect('settings/users'); return; }

        $newStatus = $user['is_active'] ? 0 : 1;
        $this->users->update($targetId, ['is_active' => $newStatus]);
        $this->flashSuccess('User ' . ($newStatus ? 'activated' : 'deactivated') . '.');
        $this->redirect('settings/users');
    }

    // ── POST /settings/users/role ─────────────────────────────
    public function changeRole(): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();

        $targetId = (int)$this->input('user_id', 0);
        $roleId   = (int)$this->input('role_id', 2);

        if ($targetId === Auth::id()) {
            $this->flashError('You cannot change your own role.');
            $this->redirect('settings/users');
            return;
        }

        if (!in_array($roleId, [1, 2, 3])) {
            $this->flashError('Invalid role.');
            $this->redirect('settings/users');
            return;
        }

        $this->users->update($targetId, ['role_id' => $roleId]);
        $this->flashSuccess('Role updated.');
        $this->redirect('settings/users');
    }

    // ── POST /settings/users/create ───────────────────────────
    public function createUser(): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();

        $firstName = $this->sanitize($this->input('first_name', ''));
        $lastName  = $this->sanitize($this->input('last_name',  ''));
        $email     = strtolower(trim($this->input('email', '')));
        $password  = $this->input('password', '');
        $roleId    = (int)$this->input('role_id', 2);

        $errors = [];
        if (!$firstName)                              $errors[] = 'First name is required.';
        if (!$lastName)                               $errors[] = 'Last name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (strlen($password) < 8)                    $errors[] = 'Password must be at least 8 characters.';
        if (!in_array($roleId, [1, 2, 3]))           $errors[] = 'Invalid role.';

        $existing = $this->users->findByEmail($email);
        if ($existing) {
            $errors[] = 'That email address is already in use.';
        }

        if ($errors) {
            $users = $this->users->getAllWithRoles();
            $this->view('settings.users', ['users' => $users, 'create_errors' => $errors]);
            return;
        }

        $this->users->createUser($firstName, $lastName, $email, $password, $roleId);
        $this->flashSuccess('User created successfully.');
        $this->redirect('settings/users');
    }

    // ── POST /settings/users/{id}/update ──────────────────────
    public function updateUser(int $userId): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();

        $user = $this->users->find($userId);
        if (!$user) {
            $this->flashError('User not found.');
            $this->redirect('settings/users');
            return;
        }

        $firstName = $this->sanitize($this->input('first_name', ''));
        $lastName  = $this->sanitize($this->input('last_name',  ''));
        $email     = strtolower(trim($this->input('email', '')));

        $errors = [];
        if (!$firstName)                              $errors[] = 'First name is required.';
        if (!$lastName)                               $errors[] = 'Last name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

        // Check email not taken by another user
        $existing = $this->users->findByEmail($email);
        if ($existing && $existing['id'] !== $userId) {
            $errors[] = 'That email address is already in use.';
        }

        if ($errors) {
            $users = $this->users->getAllWithRoles();
            $this->view('settings.users', ['users' => $users, 'edit_errors' => $errors, 'edit_user_id' => $userId]);
            return;
        }

        $this->users->update($userId, [
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
        ]);

        $this->flashSuccess('User updated successfully.');
        $this->redirect('settings/users');
    }

    // ── POST /settings/users/{id}/delete ──────────────────────
    public function deleteUser(int $userId): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();

        if ($userId === Auth::id()) {
            $this->flashError('You cannot delete your own account.');
            $this->redirect('settings/users');
            return;
        }

        $user = $this->users->find($userId);
        if (!$user) {
            $this->flashError('User not found.');
            $this->redirect('settings/users');
            return;
        }

        $this->users->delete($userId);
        $this->flashSuccess('User deleted.');
        $this->redirect('settings/users');
    }
}
