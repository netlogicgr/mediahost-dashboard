<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

final class Auth
{
    private const MAX_FAILED_ATTEMPTS = 3;
    private const BAN_SECONDS = 600;

    public static function attempt(string $username, string $password): bool
    {
        self::ensureLoginAttemptsTable();

        if (self::isLockedOut($username)) {
            return false;
        }

        $stmt = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, (string) $admin['password_hash'])) {
            self::recordFailedAttempt($username);
            return false;
        }

        self::clearFailedAttempts($username);

        session_start_safe();
        session_regenerate_id(true);
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

    public static function lockoutSecondsRemaining(string $username): int
    {
        self::ensureLoginAttemptsTable();

        $stmt = db()->prepare('SELECT MAX(attempted_at) AS last_attempt, COUNT(*) AS failures
            FROM admin_login_attempts
            WHERE username = :username AND ip_address = :ip AND success = 0 AND attempted_at >= (NOW() - INTERVAL :ban SECOND)');
        $stmt->bindValue(':username', $username);
        $stmt->bindValue(':ip', self::clientIp());
        $stmt->bindValue(':ban', self::BAN_SECONDS, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        $failures = (int) ($row['failures'] ?? 0);
        if ($failures <= self::MAX_FAILED_ATTEMPTS) {
            return 0;
        }

        $lastAttempt = strtotime((string) ($row['last_attempt'] ?? ''));
        if ($lastAttempt === false) {
            return 0;
        }

        $remaining = ($lastAttempt + self::BAN_SECONDS) - time();
        return max(0, $remaining);
    }

    private static function isLockedOut(string $username): bool
    {
        return self::lockoutSecondsRemaining($username) > 0;
    }

    private static function recordFailedAttempt(string $username): void
    {
        $stmt = db()->prepare('INSERT INTO admin_login_attempts (username, ip_address, success) VALUES (:username, :ip, 0)');
        $stmt->execute([
            'username' => $username,
            'ip' => self::clientIp(),
        ]);
    }

    private static function clearFailedAttempts(string $username): void
    {
        $stmt = db()->prepare('DELETE FROM admin_login_attempts WHERE username = :username AND ip_address = :ip');
        $stmt->execute([
            'username' => $username,
            'ip' => self::clientIp(),
        ]);
    }

    private static function clientIp(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
    }

    private static function ensureLoginAttemptsTable(): void
    {
        static $ready = false;

        if ($ready) {
            return;
        }

        db()->exec('CREATE TABLE IF NOT EXISTS admin_login_attempts (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_login_attempts_username_ip_time (username, ip_address, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $ready = true;
    }
}
