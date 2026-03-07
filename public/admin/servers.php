<?php

declare(strict_types=1);

require __DIR__ . '/_header.php';

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        $error = 'Invalid CSRF token.';
    } else {
        try {
            if (isset($_POST['add_server'])) {
                $stmt = db()->prepare('INSERT INTO servers (name, host, auth_type, username, api_token) VALUES (:name,:host,:auth_type,:username,:api_token)');
                $stmt->execute([
                    'name' => trim($_POST['name'] ?? ''),
                    'host' => rtrim(trim($_POST['host'] ?? ''), '/'),
                    'auth_type' => ($_POST['auth_type'] ?? 'whm') === 'cpanel' ? 'cpanel' : 'whm',
                    'username' => trim($_POST['username'] ?? 'root'),
                    'api_token' => trim($_POST['api_token'] ?? ''),
                ]);
                $message = 'Server added.';
            }

            if (isset($_POST['delete_server'])) {
                $stmt = db()->prepare('DELETE FROM servers WHERE id = :id');
                $stmt->execute(['id' => (int) ($_POST['id'] ?? 0)]);
                $message = 'Server removed.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$servers = db()->query('SELECT * FROM servers ORDER BY id DESC')->fetchAll();
?>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h1 class="h5">Manage Servers</h1>
        <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

        <form method="post" class="row g-2">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="add_server" value="1">
            <div class="col-md-3"><input class="form-control" name="name" placeholder="Server name" required></div>
            <div class="col-md-3"><input class="form-control" name="host" placeholder="https://server:2087" required></div>
            <div class="col-md-2">
                <select class="form-select" name="auth_type">
                    <option value="whm">WHM</option>
                    <option value="cpanel">cPanel</option>
                </select>
            </div>
            <div class="col-md-2"><input class="form-control" name="username" placeholder="root / user" value="root" required></div>
            <div class="col-md-2"><input class="form-control" name="api_token" placeholder="API token" required></div>
            <div class="col-12"><button class="btn btn-primary">Add Server</button></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h6">Configured Servers</h2>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr><th>Name</th><th>Host</th><th>Auth</th><th>Username</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($servers as $server): ?>
                    <tr>
                        <td><?= e($server['name']) ?></td>
                        <td><?= e($server['host']) ?></td>
                        <td><?= e($server['auth_type']) ?></td>
                        <td><?= e($server['username']) ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Delete this server?');">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="delete_server" value="1">
                                <input type="hidden" name="id" value="<?= (int) $server['id'] ?>">
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
