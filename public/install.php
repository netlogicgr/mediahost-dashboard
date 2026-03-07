<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/helpers.php';

if (is_installed()) {
    redirect(public_url('admin/login.php'));
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbPort = (int) ($_POST['db_port'] ?? 3306);
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string) ($_POST['db_pass'] ?? '');

    $adminUser = trim($_POST['admin_user'] ?? '');
    $adminPass = (string) ($_POST['admin_pass'] ?? '');

    try {
        if ($dbName === '' || $dbUser === '' || $adminUser === '' || $adminPass === '') {
            throw new RuntimeException('Please fill all required fields.');
        }

        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName),
            $dbUser,
            $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $pdo->exec('CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $pdo->exec('CREATE TABLE IF NOT EXISTS servers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            host VARCHAR(255) NOT NULL,
            auth_type ENUM("whm","cpanel") NOT NULL DEFAULT "whm",
            username VARCHAR(100) NOT NULL DEFAULT "root",
            api_token TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $pdo->exec('CREATE TABLE IF NOT EXISTS server_stats (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            server_id INT NOT NULL,
            cpu_usage DECIMAL(8,2) NULL,
            ram_usage DECIMAL(8,2) NULL,
            disk_usage DECIMAL(8,2) NULL,
            io_usage DECIMAL(8,2) NULL,
            fetched_at DATETIME NOT NULL,
            INDEX idx_server_time (server_id, fetched_at),
            CONSTRAINT fk_server_stats_server FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (:u, :p)');
        $stmt->execute([
            'u' => $adminUser,
            'p' => password_hash($adminPass, PASSWORD_DEFAULT),
        ]);

        $config = "<?php\n\nreturn " . var_export([
            'db' => [
                'host' => $dbHost,
                'port' => $dbPort,
                'database' => $dbName,
                'username' => $dbUser,
                'password' => $dbPass,
            ],
        ], true) . ";\n";

        file_put_contents(config_path('config.php'), $config);
        $success = 'Installation completed. You can now login.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 720px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h4 mb-3">Installation Wizard</h1>
            <p class="text-muted">Configure database and create your admin account.</p>
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?> <a href="<?= e(public_url('admin/login.php')) ?>">Login</a></div><?php endif; ?>
            <form method="post">
                <h2 class="h6 mt-3">Database</h2>
                <div class="row g-2">
                    <div class="col-md-6"><input name="db_host" class="form-control" placeholder="DB Host" value="localhost" required></div>
                    <div class="col-md-6"><input name="db_port" class="form-control" placeholder="DB Port" value="3306" required></div>
                    <div class="col-md-6"><input name="db_name" class="form-control" placeholder="DB Name" required></div>
                    <div class="col-md-6"><input name="db_user" class="form-control" placeholder="DB User" required></div>
                    <div class="col-12"><input type="password" name="db_pass" class="form-control" placeholder="DB Password"></div>
                </div>

                <h2 class="h6 mt-4">Admin</h2>
                <div class="row g-2">
                    <div class="col-md-6"><input name="admin_user" class="form-control" placeholder="Admin username" required></div>
                    <div class="col-md-6"><input type="password" name="admin_pass" class="form-control" placeholder="Admin password" required></div>
                </div>

                <button class="btn btn-primary mt-4">Install</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
