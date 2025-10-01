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
        throw new Exception('OMS ID не сохранен. Заполните и сохраните настройки OMS.');
    }

    $params = [
        'omsId' => $oms['id'],
    ];

    $orderId = trim($_GET['orderId'] ?? '');
    if ($orderId !== '') {
        $params['orderIds'] = $orderId;
    }

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
    if ($limit !== null && $limit > 0) {
        $params['limit'] = min($limit, 100);
    }

    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    $response = apiRequest(
        SUZ_API_URL . '/orders?' . $query,
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
