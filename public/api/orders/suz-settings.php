<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $context = orderGetSuzContext();
        echo json_encode(['context' => $context], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    if ($method === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Некорректный JSON');
        }

        $context = [
            'oms_id'           => (string)($payload['omsId'] ?? ''),
            'oms_connect'      => (string)($payload['omsConnection'] ?? ''),
            'participant_inn'  => (string)($payload['participantInn'] ?? ''),
            'station_url'      => (string)($payload['stationUrl'] ?? ''),
            'location_address' => (string)($payload['locationAddress'] ?? ''),
        ];

        orderStoreSuzContext($context);
        $stored = orderGetSuzContext();
        ordersLog('suz-settings updated');

        echo json_encode(['status' => 'ok', 'context' => $stored], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    if ($method === 'DELETE') {
        orderForgetSuzContext();
        ordersLog('suz-settings cleared');
        echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
        return;
    }

    http_response_code(405);
    header('Allow: GET, POST, DELETE');
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
} catch (JsonException $e) {
    http_response_code(400);
    ordersLog('suz-settings JSON error: ' . $e->getMessage());
    echo json_encode(['error' => 'Некорректный JSON: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    ordersLog('suz-settings invalid request: ' . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    ordersLog('suz-settings unexpected error: ' . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
