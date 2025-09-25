<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $meta = orderGetSuzTokenMeta();
    if ($meta === null || ($meta['token'] ?? '') === '') {
        throw new RuntimeException('Не получен clientToken СУЗ');
    }

    $payload = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Некорректный JSON');
    }

    $signature = trim((string)($payload['signature'] ?? ''));
    $orderPayload = $payload['payload'] ?? null;
    if ($signature === '' || !is_array($orderPayload)) {
        throw new InvalidArgumentException('Не переданы подпись или тело заказа');
    }

    $response = SuzClient::createOrder(
        (string)$meta['oms_id'],
        (string)$meta['token'],
        $signature,
        $orderPayload
    );

    echo json_encode(['status' => 'ok', 'response' => $response], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (JsonException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Некорректный JSON: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    http_response_code(502);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
