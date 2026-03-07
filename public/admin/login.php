<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/helpers.php';
require_once dirname(__DIR__, 2) . '/app/Auth.php';

if (!is_installed()) {
    redirect(public_url('install.php'));
}

if (Auth::check()) {
    redirect(public_url('admin/dashboard.php'));
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $ok = false;

    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        $error = 'Invalid CSRF token.';
    } else {
        $lockoutSeconds = Auth::lockoutSecondsRemaining($username);

        if ($lockoutSeconds > 0) {
            $error = sprintf('Too many failed attempts. Try again in %d minute(s).', (int) ceil($lockoutSeconds / 60));
        } else {
            $ok = Auth::attempt($username, (string) ($_POST['password'] ?? ''));
        }

        if ($ok) {
            redirect(public_url('admin/dashboard.php'));
        }

        if ($error === null) {
            $remainingAfterFail = Auth::lockoutSecondsRemaining($username);
            if ($remainingAfterFail > 0) {
                $error = sprintf('Too many failed attempts. You are temporarily blocked for %d minute(s).', (int) ceil($remainingAfterFail / 60));
            } else {
                $error = 'Invalid credentials.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 420px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h5 mb-3">Admin Login</h1>
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input name="username" class="form-control mb-2" placeholder="Username" required>
                <input type="password" name="password" class="form-control mb-3" placeholder="Password" required>
                <button class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
