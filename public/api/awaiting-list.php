<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $rows = nkFetchAwaitingDrafts();
    $payload = array_map(
        static fn(array $row) => [
            'goodId' => $row['good_id'],
            'name'   => $row['good_name'] ?? '',
        ],
        $rows
    );

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
