<?php

declare(strict_types=1);

function config_path(string $path = ''): string
{
    $base = dirname(__DIR__) . '/config';
    return $path ? $base . '/' . ltrim($path, '/') : $base;
}

function storage_path(string $path = ''): string
{
    $base = dirname(__DIR__) . '/storage';
    return $path ? $base . '/' . ltrim($path, '/') : $base;
}

function is_installed(): bool
{
    return file_exists(config_path('config.php'));
}

function app_config(): array
{
    static $config;

    if ($config !== null) {
        return $config;
    }

    if (!is_installed()) {
        return $config = [];
    }

    /** @var array $loaded */
    $loaded = require config_path('config.php');
    return $config = $loaded;
}

function db(): PDO
{
    static $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = app_config();

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['db']['host'],
        (int) ($config['db']['port'] ?? 3306),
        $config['db']['database']
    );

    $pdo = new PDO($dsn, $config['db']['username'], $config['db']['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function public_base_path(): string
{
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

    $publicPos = strpos($scriptName, '/public/');
    if ($publicPos !== false) {
        return rtrim(substr($scriptName, 0, $publicPos + 7), '/');
    }

    if (str_ends_with($scriptName, '/public')) {
        return rtrim($scriptName, '/');
    }

    return '';
}

function public_url(string $path = ''): string
{
    $base = public_base_path();
    if ($path === '') {
        return $base !== '' ? $base : '/';
    }

    $cleanPath = ltrim($path, '/');
    return ($base !== '' ? $base : '') . '/' . $cleanPath;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function session_start_safe(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function apply_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
}

apply_security_headers();

function csrf_token(): string
{
    session_start_safe();

    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['_csrf'];
}

function csrf_validate(?string $token): bool
{
    session_start_safe();
    return isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], (string) $token);
}
