<?php

declare(strict_types=1);

require __DIR__ . '/_header.php';

$servers = db()->query('SELECT COUNT(*) as c FROM servers')->fetch();
$stats = db()->query('SELECT COUNT(*) as c FROM server_stats')->fetch();
?>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-muted">Total Servers</h2>
                <p class="display-6 mb-0"><?= (int) ($servers['c'] ?? 0) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-muted">Stats Records</h2>
                <p class="display-6 mb-0"><?= (int) ($stats['c'] ?? 0) ?></p>
            </div>
        </div>
    </div>
</div>
<div class="card shadow-sm mt-4">
    <div class="card-body">
        <h2 class="h6">Quick Links</h2>
        <a href="/admin/servers.php" class="btn btn-primary btn-sm">Manage Servers</a>
        <a href="/" class="btn btn-outline-secondary btn-sm">Open Public Page</a>
    </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
