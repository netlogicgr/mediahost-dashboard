<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

final class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $stmt = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, (string) $admin['password_hash'])) {
            return false;
        }

        session_start_safe();
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = (string) $admin['username'];

        return true;
    }

    public static function check(): bool
    {
        session_start_safe();
        return !empty($_SESSION['admin_id']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect(public_url('admin/login.php'));
        }
    }

    public static function logout(): void
    {
        session_start_safe();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }
}
