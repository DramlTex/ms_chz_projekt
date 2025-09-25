<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $result = [
        'document' => null,
        'orders'   => null,
    ];

    $docId = trim((string)($_GET['docId'] ?? ''));
    $pg = trim((string)($_GET['pg'] ?? 'lp'));
    $orderId = trim((string)($_GET['orderId'] ?? ''));

    $trueMeta = orderGetTrueApiTokenMeta();
    if ($docId !== '' && $trueMeta !== null) {
        try {
            $result['document'] = TrueApiClient::documentInfo($trueMeta['token'], $pg, $docId);
        } catch (Throwable $e) {
            $result['documentError'] = $e->getMessage();
        }
    }

    $suzMeta = orderGetSuzTokenMeta();
    if ($suzMeta !== null) {
        try {
            $params = [];
            if ($orderId !== '') {
                $params['orderId'] = $orderId;
            }
            $result['orders'] = SuzClient::list((string)$suzMeta['oms_id'], (string)$suzMeta['token'], $params);
        } catch (Throwable $e) {
            $result['ordersError'] = $e->getMessage();
        }
    }

    echo json_encode(['status' => 'ok', 'data' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
