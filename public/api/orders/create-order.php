<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $meta = orderGetSuzTokenMeta();
    if ($meta === null || empty($meta['token'])) {
        http_response_code(401);
        echo json_encode(['error' => 'clientToken отсутствует. Выполните авторизацию СУЗ.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $payload = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Некорректный JSON-запрос');
    }

    if (empty($payload['order']) || !is_array($payload['order'])) {
        throw new InvalidArgumentException('Поле order отсутствует или имеет неверный формат');
    }

    $signature = trim((string)($payload['signature'] ?? ''));
    if ($signature === '') {
        throw new InvalidArgumentException('Подпись не передана');
    }

    $rawBody = isset($payload['raw']) ? trim((string)$payload['raw']) : '';
    $order   = $payload['order'];

    $omsId = (string)($meta['oms_id'] ?? '');
    if ($omsId === '') {
        throw new RuntimeException('OMS ID не задан. Сохраните настройки OMS.');
    }

    $clientToken = (string)$meta['token'];
    $productGroup = isset($order['productGroup']) ? (string)$order['productGroup'] : '';

    if ($rawBody !== '') {
        $bodyForSuz = $rawBody;
    } else {
        $bodyForSuz = $order;
    }

    ordersLog(sprintf('order-create request: omsId=%s, productGroup=%s', $omsId, $productGroup));
    if (is_string($bodyForSuz)) {
        ordersLog('order-create payload (truncated): ' . substr($bodyForSuz, 0, 500));
    }

    $response = SuzClient::createOrder($omsId, $clientToken, $signature, $bodyForSuz);

    echo json_encode([
        'status'   => 'ok',
        'response' => $response,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (JsonException $e) {
    http_response_code(400);
    ordersLog('order-create JSON error: ' . $e->getMessage());
    echo json_encode(['error' => 'Некорректный JSON: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    ordersLog('order-create invalid request: ' . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    http_response_code(502);
    ordersLog('order-create runtime error: ' . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    ordersLog('order-create unexpected error: ' . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

