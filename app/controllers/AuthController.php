<?php
// app/controllers/AuthController.php

class AuthController extends Controller
{
    private UserModel $users;

    public function __construct()
    {
        $this->users = new UserModel();
    }

    // ── GET /auth/login ───────────────────────────────────────

    public function loginForm(): void
    {
        if (Auth::check()) {
            $this->redirect('dashboard');
        }
        $this->view('auth.login', [], 'auth');
    }

    // ── POST /auth/login ──────────────────────────────────────

    public function login(): void
    {
        Auth::verifyCsrf();

        $email    = strtolower(trim($this->input('email', '')));
        $password = $this->input('password', '');

        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
        if (strlen($password) < 1)                      $errors[] = 'Password is required.';

        if ($errors) {
            $this->view('auth.login', ['errors' => $errors, 'email' => $email], 'auth');
            return;
        }

        $user = $this->users->findByEmail($email);

        if (!$user || !$this->users->verifyPassword($password, $user['password_hash'])) {
            $this->view('auth.login', [
                'errors' => ['Invalid email or password.'],
                'email'  => $email,
            ], 'auth');
            return;
        }

        if (!$user['is_active']) {
            $this->view('auth.login', [
                'errors' => ['Your account has been deactivated. Please contact an admin.'],
            ], 'auth');
            return;
        }

        Auth::login($user);
        $this->redirect('dashboard');
    }

    // ── GET /auth/register ────────────────────────────────────

    public function registerForm(): void
    {
        if (Auth::check()) $this->redirect('dashboard');
        $this->view('auth.register', [], 'auth');
    }

    // ── POST /auth/register ───────────────────────────────────

    public function register(): void
    {
        Auth::verifyCsrf();

        $firstName = $this->sanitize($this->input('first_name', ''));
        $lastName  = $this->sanitize($this->input('last_name',  ''));
        $email     = strtolower(trim($this->input('email', '')));
        $password  = $this->input('password', '');
        $confirm   = $this->input('password_confirm', '');

        $errors = [];
        if (strlen($firstName) < 1)                             $errors[] = 'First name is required.';
        if (strlen($lastName)  < 1)                             $errors[] = 'Last name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))         $errors[] = 'A valid email address is required.';
        if (strlen($password) < 8)                              $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $confirm)                             $errors[] = 'Passwords do not match.';
        if (!$errors && $this->users->findByEmail($email))      $errors[] = 'That email address is already registered.';

        if ($errors) {
            $this->view('auth.register', [
                'errors'     => $errors,
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email,
            ], 'auth');
            return;
        }

        $id   = $this->users->createUser($firstName, $lastName, $email, $password);
        $user = $this->users->find($id);
        Auth::login($user);
        $this->flashSuccess('Welcome to Budjit, ' . $firstName . '!');
        $this->redirect('dashboard');
    }

    // ── GET /auth/logout ──────────────────────────────────────

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('auth/login');
    }
}
