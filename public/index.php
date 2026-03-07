<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/helpers.php';

if (!is_installed()) {
    redirect(public_url('install.php'));
}

$publicAccessError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        $publicAccessError = 'Invalid request, please try again.';
    } elseif (!public_access_attempt((string) ($_POST['access_code'] ?? ''))) {
        $publicAccessError = 'Wrong code. Please try again.';
    } else {
        redirect(public_url());
    }
}

if (!public_access_granted()):
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Protected Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height: 100dvh;">
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-3">Enter access code</h1>
                    <p class="text-muted">This public page is protected. Please enter the code to continue.</p>
                    <?php if ($publicAccessError): ?>
                        <div class="alert alert-danger"><?= e($publicAccessError) ?></div>
                    <?php endif; ?>
                    <form method="post" action="<?= e(public_url()) ?>">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <div class="mb-3">
                            <label for="access_code" class="form-label">Code</label>
                            <input
                                type="password"
                                class="form-control"
                                id="access_code"
                                name="access_code"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                required
                            >
                        </div>
                        <button type="submit" class="btn btn-primary w-100">View dashboard</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php
exit;
endif;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Server Metrics Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cards-columns: 3;
            --cards-rows: 1;
            --card-scale: 1;
        }

        html,
        body {
            height: 100%;
            overflow: hidden;
        }

        .dashboard-shell {
            height: 100dvh;
            overflow: hidden;
        }

        .cards-grid {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(var(--cards-columns), minmax(0, 1fr));
            grid-template-rows: repeat(var(--cards-rows), minmax(0, 1fr));
            min-height: 0;
            overflow: hidden;
        }

        .server-card {
            width: 100%;
            height: 100%;
            min-height: 0;
        }

        .server-card .card-body {
            padding: clamp(0.5rem, calc(0.8rem * var(--card-scale)), 2rem);
            gap: clamp(0.25rem, calc(0.4rem * var(--card-scale)), 0.75rem);
        }

        .server-card .card-title {
            font-size: clamp(1rem, calc(1.15rem * var(--card-scale)), 2.2rem);
            margin-bottom: 0;
        }

        .server-card .server-host,
        .server-card .server-label,
        .server-card .server-error {
            font-size: clamp(0.75rem, calc(0.95rem * var(--card-scale)), 1.35rem);
        }

        .server-card .server-value {
            font-size: clamp(2.4rem, calc(3.45rem * var(--card-scale)), 16.03125rem);
            line-height: 1.1;
        }

        .server-card .server-error {
            max-width: 100%;
            overflow: hidden;
        }

        @media (max-width: 1199.98px) {
            :root {
                --cards-columns: 2;
            }
        }

        @media (max-width: 767.98px) {
            :root {
                --cards-columns: 1;
                --cards-rows: auto;
                --card-scale: 1;
            }

            html,
            body {
                overflow: auto;
            }

            .dashboard-shell {
                height: auto;
                min-height: 100dvh;
                overflow: visible;
            }

            .cards-grid {
                display: flex;
                flex-direction: column;
                overflow: visible;
                gap: 1rem;
            }

            .server-card {
                height: auto;
            }

            .server-card .card-body {
                padding: 1.25rem;
            }
        }
    </style>
</head>
<body class="bg-light">
<div class="container-fluid py-4 px-4 d-flex flex-column dashboard-shell">
    <div id="alerts"></div>
    <div id="cards" class="row g-3 cards-grid"></div>
</div>
<script>
async function loadStats() {
    try {
        const res = await fetch('<?= e(public_url('api/stats.php')) ?>');

        if (res.status === 401) {
            window.location.reload();
            return;
        }

        const data = await res.json();

        const cards = document.getElementById('cards');
        cards.innerHTML = '';

        if (!data.servers || data.servers.length === 0) {
            cards.innerHTML = `<div class="col-12"><div class="alert alert-info">No servers configured yet.</div></div>`;
            return;
        }

        for (const srv of data.servers) {
            const stateClass = getLoadStateClass(srv.metrics?.cpu);

            cards.innerHTML += `
                <div>
                    <div class="card shadow-sm h-100 server-card ${stateClass}">
                        <div class="card-body d-flex flex-column justify-content-center text-center">
                            <h5 class="card-title">${srv.name}</h5>
                            <div class="text-muted server-label mb-0">CPU Load Average</div>
                            <div class="fw-bold mb-0 server-value">${formatLoadAverage(srv.metrics.cpu)}</div>
                            ${srv.error ? `<div class="alert alert-warning mb-0 server-error text-start">${srv.error}</div>` : ''}
                        </div>
                    </div>
                </div>`;
        }

        updateCardsLayout(data.servers.length);
    } catch (e) {
        document.getElementById('alerts').innerHTML = '<div class="alert alert-danger">Failed to load stats.</div>';
    }
}

function getCardsColumns() {
    if (window.innerWidth < 768) {
        return 1;
    }

    if (window.innerWidth < 1200) {
        return 2;
    }

    return 3;
}

function updateCardsLayout(totalCards) {
    const cards = document.getElementById('cards');

    if (window.innerWidth < 768) {
        cards.style.setProperty('--cards-rows', 'auto');
        cards.style.setProperty('--card-scale', '1');
        return;
    }

    const columns = getCardsColumns();
    const rows = Math.max(1, Math.ceil(totalCards / columns));
    const viewportHeight = window.innerHeight;
    const cardsTopOffset = cards.getBoundingClientRect().top;
    const availableHeight = Math.max(320, viewportHeight - cardsTopOffset - 16);
    const widthScale = window.innerWidth / (columns * 420);
    const heightScale = availableHeight / (rows * 230);
    const cardScale = Math.max(0.45, Math.min(1.35, Math.min(widthScale, heightScale)));

    cards.style.setProperty('--cards-rows', String(rows));
    cards.style.setProperty('--card-scale', cardScale.toFixed(2));
}

function getLoadStateClass(value) {
    const parsedValue = Number(value);

    if (Number.isNaN(parsedValue)) {
        return '';
    }

    if (parsedValue > 18) {
        return 'bg-danger-subtle border-danger-subtle';
    }

    return 'bg-success-subtle border-success-subtle';
}

function formatLoadAverage(value) {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return 'N/A';
    }

    return Number(value).toFixed(2);
}

loadStats();
setInterval(loadStats, 10000);
window.addEventListener('resize', () => updateCardsLayout(document.getElementById('cards').children.length));
</script>
</body>
</html>
