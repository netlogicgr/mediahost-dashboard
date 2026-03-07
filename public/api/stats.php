<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/helpers.php';
require_once dirname(__DIR__, 2) . '/app/CpanelApiService.php';

header('Content-Type: application/json');

if (!is_installed()) {
    echo json_encode(['servers' => []]);
    exit;
}

if (!public_access_granted()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
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
        'metrics' => ['cpu' => null],
        'error' => null,
    ];

    try {
        $stats = $service->fetchServerStats($server);
        $row['metrics'] = [
            'cpu' => $stats['cpu'],
        ];

        $pdo = db();
        $insert = $pdo->prepare('INSERT INTO server_stats (server_id, cpu_usage, ram_usage, disk_usage, io_usage, fetched_at) VALUES (:server_id,:cpu,:ram,:disk,:io,NOW())');
        $insert->execute([
            'server_id' => $server['id'],
            'cpu' => $stats['cpu'],
            'ram' => null,
            'disk' => null,
            'io' => null,
        ]);

        $deleteOld = $pdo->prepare('DELETE FROM server_stats WHERE server_id = :server_id AND id <> :latest_id');
        $deleteOld->execute([
            'server_id' => $server['id'],
            'latest_id' => (int) $pdo->lastInsertId(),
        ]);
    } catch (Throwable $e) {
        $row['error'] = $e->getMessage();
    }

    $out[] = $row;
}

echo json_encode(['servers' => $out]);
