<?php

declare(strict_types=1);

require __DIR__ . '/_header.php';
require_once dirname(__DIR__, 2) . '/app/Updater.php';

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        $error = 'Invalid CSRF token.';
    } else {
        try {
            $updater = new Updater();
            $message = $updater->updateFromZipUrl(trim($_POST['zip_url'] ?? ''));
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<div class="card shadow-sm">
    <div class="card-body">
        <h1 class="h5">Update Application</h1>
        <p class="text-muted">Download and install a ZIP package from GitHub.</p>
        <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <label class="form-label">GitHub ZIP URL</label>
            <input name="zip_url" class="form-control" placeholder="https://github.com/user/repo/archive/refs/heads/main.zip" required>
            <button class="btn btn-primary mt-3">Run Update</button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
