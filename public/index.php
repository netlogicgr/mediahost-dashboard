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
            border-radius: clamp(0.65rem, calc(0.8rem * var(--card-scale)), 1.1rem);
            overflow: hidden;
        }

        .server-card .card-body {
            padding: 0;
            gap: 0;
            overflow: hidden;
            height: 100%;
            align-items: center;
        }

        .server-card .server-content {
            width: 100%;
            flex: 1;
            justify-content: center;
            padding: clamp(0.8rem, calc(1rem * var(--card-scale)), 2.2rem) clamp(0.8rem, calc(1rem * var(--card-scale)), 2.2rem) clamp(0.6rem, calc(0.75rem * var(--card-scale)), 1.3rem);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: clamp(0.25rem, calc(0.4rem * var(--card-scale)), 0.75rem);
            text-align: center;
        }

        .server-card .card-title {
            font-size: clamp(0.95rem, calc(1.08rem * var(--card-scale)), 1.9rem);
            margin-bottom: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .server-card .server-head {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: clamp(0.05rem, calc(0.15rem * var(--card-scale)), 0.3rem);
            min-height: 0;
        }

        .server-card .server-host,
        .server-card .server-label,
        .server-card .server-error {
            font-size: clamp(0.8rem, calc(1rem * var(--card-scale)), 1.5rem);
        }

        .server-card .server-value {
            font-size: clamp(2.7rem, calc(4.2rem * var(--card-scale)), 9.75rem);
            line-height: 1.1;
            margin: clamp(0.15rem, calc(0.35rem * var(--card-scale)), 0.8rem) 0 0;
            overflow-wrap: anywhere;
        }

        .server-card .server-error {
            max-width: 100%;
            overflow: hidden;
            margin: 0;
            font-size: clamp(0.65rem, calc(0.85rem * var(--card-scale)), 1.1rem);
            padding: 0.45rem 0.6rem;
            border-radius: 0.55rem;
        }

        .server-card .history-chart {
            position: relative;
            width: 100%;
            height: clamp(3.25rem, calc(5.8rem * var(--card-scale)), 8rem);
            margin-top: 0;
            border-radius: 0;
            overflow: visible;
            background: transparent;
            border-top: 0;
            flex-shrink: 0;
        }

        .server-card .history-chart svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .server-card .history-tooltip {
            position: absolute;
            transform: translate(-50%, -100%);
            pointer-events: none;
            background: rgba(15, 23, 42, 0.93);
            color: #fff;
            border-radius: 0.4rem;
            padding: 0.32rem 0.45rem;
            font-size: clamp(0.6rem, calc(0.76rem * var(--card-scale)), 0.9rem);
            line-height: 1.2;
            white-space: nowrap;
            z-index: 2;
            opacity: 0;
            transition: opacity 120ms ease-in;
        }

        .server-card .history-tooltip.active {
            opacity: 1;
        }

        .rolling-number {
            display: inline-flex;
            align-items: flex-end;
            gap: 0.03em;
            overflow: hidden;
        }

        .rolling-number__digit {
            display: inline-block;
            min-width: 0.58em;
            text-align: center;
            transform: translateY(-120%);
            opacity: 0;
            animation: rollDigit 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        .rolling-number__separator {
            min-width: 0.28em;
            opacity: 0;
            transform: translateY(-50%);
            animation: fadeSeparator 0.35s ease-out forwards;
        }

        @keyframes rollDigit {
            0% {
                transform: translateY(-120%);
                opacity: 0;
            }

            70% {
                opacity: 1;
            }

            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeSeparator {
            from {
                opacity: 0;
                transform: translateY(-50%);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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
                min-height: 14.5rem;
            }

            .server-card .server-value {
                font-size: clamp(3rem, 16vw, 4.5rem);
            }

            .server-card .server-content {
                padding: 1.25rem 1.25rem 0.7rem;
            }

            .server-card .history-chart {
                height: 4.8rem;
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
            const historyChart = renderHistoryChart(srv.history || []);

            cards.innerHTML += `
                <div>
                    <div class="card shadow-sm h-100 server-card ${stateClass}">
                        <div class="card-body d-flex flex-column text-center">
                            <div class="server-content">
                                <div class="server-head">
                                    <h5 class="card-title">${srv.name}</h5>
                                    <div class="text-muted server-label mb-0">CPU Load Average</div>
                                </div>
                                <div class="fw-bold mb-0 server-value">${renderLoadAverage(srv.metrics.cpu)}</div>
                                ${srv.error ? `<div class="alert alert-warning server-error text-start">${srv.error}</div>` : ''}
                            </div>
                            <div class="history-chart">${historyChart}</div>
                        </div>
                    </div>
                </div>`;
        }

        updateCardsLayout(data.servers.length);
        bindHistoryTooltips();
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
    const heightScale = availableHeight / (rows * 300);
    const baseScale = Math.min(widthScale, heightScale);
    const largeScreenBoost = window.innerWidth >= 1600 ? 1.15 : 1;
    const cardScale = Math.max(0.35, Math.min(1.6, baseScale * largeScreenBoost));

    cards.style.setProperty('--cards-rows', String(rows));
    cards.style.setProperty('--card-scale', cardScale.toFixed(2));
}

function getLoadStateClass(value) {
    const parsedValue = Number(value);

    if (Number.isNaN(parsedValue)) {
        return '';
    }

    if (parsedValue >= 18) {
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

function renderLoadAverage(value) {
    const formatted = formatLoadAverage(value);

    if (formatted === 'N/A') {
        return formatted;
    }

    const frame = formatted
        .split('')
        .map((char, index) => {
            const delay = (index * 70) + 70;

            if (/\d/.test(char)) {
                return `<span class="rolling-number__digit" style="animation-delay:${delay}ms">${char}</span>`;
            }

            return `<span class="rolling-number__separator" style="animation-delay:${delay}ms">${char}</span>`;
        })
        .join('');

    return `<span class="rolling-number" aria-label="Load average ${formatted}">${frame}</span>`;
}

function renderHistoryChart(history) {
    const highLoadThreshold = 18;
    const points = Array.isArray(history) ? history.filter((item) => item && item.cpu !== null && !Number.isNaN(Number(item.cpu))) : [];

    if (points.length < 2) {
        return '<div class="text-muted small">No 1-hour history yet.</div>';
    }

    const width = 320;
    const height = 108;
    const paddingX = 6;
    const topPadding = 12;
    const bottomPadding = 0;
    const values = points.map((item) => Number(item.cpu));
    const rawMinValue = Math.min(...values);
    const rawMaxValue = Math.max(...values);
    const rawRange = rawMaxValue - rawMinValue;
    const minVisibleRange = Math.max(rawMaxValue * 0.08, 0.4);
    const paddedRange = Math.max(rawRange, minVisibleRange);
    const rangePadding = paddedRange * 0.16;
    const minValue = rawMinValue - rangePadding;
    const maxValue = rawMaxValue + rangePadding;
    const range = maxValue - minValue || 1;

    const plottedPoints = points
        .map((item, index) => {
            const x = paddingX + (index * (width - paddingX * 2)) / (points.length - 1);
            const y = topPadding + (height - topPadding - bottomPadding) * (1 - ((Number(item.cpu) - minValue) / range));
            return { x, y, value: Number(item.cpu), at: item.at || null };
        });

    const createSegment = (start, end) => {
        const parts = [];
        const startHigh = start.value >= highLoadThreshold;
        const endHigh = end.value >= highLoadThreshold;

        if (startHigh === endHigh) {
            parts.push({ start, end, high: startHigh });
            return parts;
        }

        const delta = end.value - start.value;
        if (delta === 0) {
            parts.push({ start, end, high: startHigh });
            return parts;
        }

        const ratio = (highLoadThreshold - start.value) / delta;
        const crossingX = start.x + ((end.x - start.x) * ratio);
        const crossingY = start.y + ((end.y - start.y) * ratio);
        const crossingPoint = {
            x: crossingX,
            y: crossingY,
            value: highLoadThreshold,
        };

        parts.push({ start, end: crossingPoint, high: startHigh });
        parts.push({ start: crossingPoint, end, high: endHigh });

        return parts;
    };

    const segments = [];
    plottedPoints.forEach((point, index) => {
        if (index === 0) {
            return;
        }

        const prevPoint = plottedPoints[index - 1];
        const start = { ...prevPoint, value: Number(points[index - 1].cpu) };
        const end = { ...point, value: Number(points[index].cpu) };
        segments.push(...createSegment(start, end));
    });

    const redPath = segments
        .filter((segment) => segment.high)
        .map((segment) => `M ${segment.start.x.toFixed(2)} ${segment.start.y.toFixed(2)} L ${segment.end.x.toFixed(2)} ${segment.end.y.toFixed(2)}`)
        .join(' ');

    const latestLoad = Number(points[points.length - 1].cpu);
    const isHighLoad = latestLoad >= highLoadThreshold;
    const baseColor = '#16a34a';
    const alertColor = '#dc2626';
    const finalPoint = plottedPoints[plottedPoints.length - 1];
    const smoothPath = plottedPoints
        .map((point, index, allPoints) => {
            if (index === 0) {
                return `M ${point.x.toFixed(2)} ${point.y.toFixed(2)}`;
            }

            const prev = allPoints[index - 1];
            const controlX = ((prev.x + point.x) / 2).toFixed(2);

            return `C ${controlX} ${prev.y.toFixed(2)} ${controlX} ${point.y.toFixed(2)} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`;
        })
        .join(' ');

    const tooltipPoints = plottedPoints
        .map((point) => {
            const formattedTime = formatHistoryTime(point.at);
            const label = `Load: ${point.value.toFixed(2)}${formattedTime ? ` (${formattedTime})` : ''}`;

            return `<circle class="history-hover-point" data-value="${point.value.toFixed(2)}" data-time="${formattedTime}" cx="${point.x.toFixed(2)}" cy="${point.y.toFixed(2)}" r="9" fill="transparent" aria-label="${label}"><title>${label}</title></circle>`;
        })
        .join('');

    return `
        <div class="history-tooltip" aria-hidden="true"></div>
        <svg viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" role="img" aria-label="CPU load history for last 1 hour">
            <defs>
                <linearGradient id="history-gradient" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0%" stop-color="#86efac"></stop>
                    <stop offset="100%" stop-color="${baseColor}"></stop>
                </linearGradient>
            </defs>
            <path d="${smoothPath}" fill="none" stroke="url(#history-gradient)" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9"></path>
            ${redPath ? `<path d="${redPath}" fill="none" stroke="${alertColor}" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9"></path>` : ''}
            ${tooltipPoints}
            <circle cx="${finalPoint.x.toFixed(2)}" cy="${finalPoint.y.toFixed(2)}" r="3.2" fill="${isHighLoad ? alertColor : baseColor}" opacity="0.2"></circle>
            <circle cx="${finalPoint.x.toFixed(2)}" cy="${finalPoint.y.toFixed(2)}" r="1.9" fill="${isHighLoad ? alertColor : baseColor}"></circle>
        </svg>
    `;
}

function formatHistoryTime(timestamp) {
    if (!timestamp) {
        return '';
    }

    const parsed = new Date(timestamp.replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat('el-GR', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(parsed);
}

function bindHistoryTooltips() {
    const charts = document.querySelectorAll('.history-chart');
    const horizontalPadding = 8;

    charts.forEach((chart) => {
        const tooltip = chart.querySelector('.history-tooltip');
        if (!tooltip) {
            return;
        }

        const points = chart.querySelectorAll('.history-hover-point');

        points.forEach((point) => {
            const showTooltip = (event) => {
                const value = point.getAttribute('data-value') || 'N/A';
                const time = point.getAttribute('data-time') || '';
                const chartRect = chart.getBoundingClientRect();
                const pointerX = event ? (event.clientX - chartRect.left) : Number(point.getAttribute('cx'));
                const pointerY = event ? (event.clientY - chartRect.top) : Number(point.getAttribute('cy'));

                tooltip.textContent = time ? `${time} • ${value}` : value;

                const tooltipWidth = tooltip.offsetWidth;
                const leftEdge = horizontalPadding;
                const rightEdge = Math.max(leftEdge, chart.clientWidth - tooltipWidth - horizontalPadding);
                const centeredLeft = pointerX - (tooltipWidth / 2);
                const clampedLeft = Math.min(Math.max(centeredLeft, leftEdge), rightEdge);

                tooltip.style.transform = 'translate(0, -100%)';
                tooltip.style.left = `${clampedLeft}px`;
                tooltip.style.top = `${pointerY - 4}px`;
                tooltip.classList.add('active');
            };

            point.addEventListener('mouseenter', showTooltip);
            point.addEventListener('mousemove', showTooltip);

            point.addEventListener('mouseleave', () => {
                tooltip.classList.remove('active');
            });
        });
    });
}

loadStats();
setInterval(loadStats, 10000);
window.addEventListener('resize', () => updateCardsLayout(document.getElementById('cards').children.length));
</script>
</body>
</html>
