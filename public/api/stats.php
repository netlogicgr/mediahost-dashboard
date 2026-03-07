<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/helpers.php';
require_once dirname(__DIR__, 2) . '/app/CpanelApiService.php';

header('Content-Type: application/json');

if (!is_installed()) {
    echo json_encode(['servers' => []]);
    exit;
}

$stmt = db()->query('SELECT id, name, host, auth_type, username, api_token FROM servers ORDER BY id DESC');
$servers = $stmt->fetchAll();

$service = new CpanelApiService();
$out = [];

foreach ($servers as $server) {
    $row = [
        'id' => (int) $server['id'],
        'name' => $server['name'],
        'host' => $server['host'],
        'metrics' => ['cpu' => null, 'ram' => null, 'disk' => null, 'io' => null],
        'error' => null,
    ];

    try {
        $stats = $service->fetchServerStats($server);
        $row['metrics'] = [
            'cpu' => $stats['cpu'],
            'ram' => $stats['ram'],
            'disk' => $stats['disk'],
            'io' => $stats['io'],
        ];

        $insert = db()->prepare('INSERT INTO server_stats (server_id, cpu_usage, ram_usage, disk_usage, io_usage, fetched_at) VALUES (:server_id,:cpu,:ram,:disk,:io,NOW())');
        $insert->execute([
            'server_id' => $server['id'],
            'cpu' => $stats['cpu'],
            'ram' => $stats['ram'],
            'disk' => $stats['disk'],
            'io' => $stats['io'],
        ]);
    } catch (Throwable $e) {
        $row['error'] = $e->getMessage();
    }

    $out[] = $row;
}

echo json_encode(['servers' => $out]);
