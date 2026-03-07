<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/helpers.php';

if (!is_installed()) {
    redirect(public_url('install.php'));
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Server Metrics Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Public Server Metrics</h1>
        <a href="<?= e(public_url('admin/login.php')) ?>" class="btn btn-outline-primary btn-sm">Admin</a>
    </div>
    <p class="text-muted">Auto refresh every 10 seconds.</p>
    <div id="alerts"></div>
    <div id="cards" class="row g-3"></div>
</div>
<script>
async function loadStats() {
    try {
        const res = await fetch('<?= e(public_url('api/stats.php')) ?>');
        const data = await res.json();

        const cards = document.getElementById('cards');
        cards.innerHTML = '';

        if (!data.servers || data.servers.length === 0) {
            cards.innerHTML = `<div class="col-12"><div class="alert alert-info">No servers configured yet.</div></div>`;
            return;
        }

        for (const srv of data.servers) {
            cards.innerHTML += `
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title">${srv.name}</h5>
                            <p class="text-muted small mb-3">${srv.host}</p>
                            ${metric('CPU Usage', srv.metrics.cpu)}
                            ${metric('RAM Usage', srv.metrics.ram)}
                            ${metric('Disk Usage', srv.metrics.disk)}
                            ${metric('I/O Usage', srv.metrics.io)}
                            ${srv.error ? `<div class="alert alert-warning mt-3 mb-0 small">${srv.error}</div>` : ''}
                        </div>
                    </div>
                </div>`;
        }
    } catch (e) {
        document.getElementById('alerts').innerHTML = '<div class="alert alert-danger">Failed to load stats.</div>';
    }
}

function metric(label, value) {
    const display = value === null ? 'N/A' : `${value.toFixed(2)}%`;
    const width = value === null ? 0 : Math.max(0, Math.min(100, value));
    return `<div class="mb-2"><div class="d-flex justify-content-between"><small>${label}</small><small>${display}</small></div><div class="progress" style="height:8px"><div class="progress-bar" role="progressbar" style="width:${width}%"></div></div></div>`;
}

loadStats();
setInterval(loadStats, 10000);
</script>
</body>
</html>
