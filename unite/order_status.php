<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $token = getToken('suz_token');
    if (!$token) {
        http_response_code(401);
        throw new Exception('clientToken отсутствует');
    }

    $oms = getOmsSettings();
    if (empty($oms['id'])) {
        throw new Exception('OMS ID не сохранен');
    }

    $params = [
        'omsId' => $oms['id'],
    ];

    $orderId = trim($_GET['orderId'] ?? '');
    if ($orderId !== '') {
        $params['orderId'] = $orderId;
    }

    $gtin = trim($_GET['gtin'] ?? '');
    if ($gtin !== '') {
        $params['gtin'] = $gtin;
    }

    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    // ИСПРАВЛЕНО: /order/status (не просто /order)
    $response = apiRequest(
        SUZ_API_URL . '/order/status?' . $query,
        'GET',
        [
            'Accept: application/json',
            'clientToken: ' . $token,
        ]
    );

    echo json_encode(['ok' => true, 'orders' => $response]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}