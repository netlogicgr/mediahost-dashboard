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

            if (!empty($_FILES['zip_file']['name'] ?? '')) {
                $message = $updater->updateFromUploadedZip($_FILES['zip_file']);
            } else {
                $message = $updater->updateFromZipUrl(trim($_POST['zip_url'] ?? ''));
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<div class="card shadow-sm">
    <div class="card-body">
        <h1 class="h5">Update Application</h1>
        <p class="text-muted">Install a ZIP package via URL or direct upload.</p>
        <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

            <label class="form-label">Upload ZIP file</label>
            <input type="file" name="zip_file" class="form-control" accept=".zip,application/zip">

            <div class="text-center text-muted my-2">or</div>

            <label class="form-label">ZIP URL</label>
            <input name="zip_url" class="form-control" placeholder="https://github.com/user/repo/archive/refs/heads/main.zip">

            <button class="btn btn-primary mt-3">Run Update</button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
