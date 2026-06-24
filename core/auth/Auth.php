<?php
// core/auth/Auth.php

class Auth
{
    // ── Session bootstrap ────────────────────────────────────

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    // ── Login / Logout ────────────────────────────────────────

//    Francois Venter 06-15-2024:
//    Added explicit type casting to ensure that user_id and user_role are stored as integers in the session, 
//    preventing potential type-related bugs in permission checks and other logic that relies on these values.

    public static function login(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int)$user['id'];        // ← cast
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_role'] = (int)$user['role_id'];   // ← cast
    $_SESSION['logged_in'] = true;
    $_SESSION['login_at']  = time();
}

//    old method without type casting, which could lead to issues if the database returns strings for numeric fields
//
//    public static function login(array $user): void
//    {
//        session_regenerate_id(true);
//        $_SESSION['user_id']   = $user['id'];
//        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
//        $_SESSION['user_role'] = $user['role_id'];
//        $_SESSION['logged_in'] = true;
//        $_SESSION['login_at']  = time();
//    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    // ── Checks ────────────────────────────────────────────────

    public static function check(): bool
    {
        return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function user(): ?array
    {
        if (!self::check()) return null;
        return [
            'id'   => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'role' => $_SESSION['user_role'],
        ];
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['user_role'] ?? 0) === 1;
    }

    public static function isViewer(): bool
    {
        return ($_SESSION['user_role'] ?? 0) === 3;
    }

    // ── Guards ────────────────────────────────────────────────

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . BASE_URL . '/auth/login');
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            die('Access denied.');
        }
    }

    public static function requireWriteAccess(): void
    {
        self::requireLogin();
        if (self::isViewer()) {
            http_response_code(403);
            die('Read-only access — cannot modify data.');
        }
    }

    // ── CSRF ─────────────────────────────────────────────────

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        }
        return $_SESSION['csrf_token'];
    }

    public static function csrfField(): string
    {
        $token = self::csrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    public static function verifyCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals(self::csrfToken(), $token)) {
            http_response_code(419);
            die('CSRF token mismatch. Please go back and try again.');
        }
    }
}
