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

    $orderId = trim($_GET['orderId'] ?? '');
    if ($orderId === '') {
        throw new Exception('Не указан orderId');
    }

    $bufferId = trim($_GET['bufferId'] ?? '');
    $count = isset($_GET['count']) ? (int)$_GET['count'] : 1000;
    if ($count <= 0) {
        $count = 1000;
    }
    $count = min($count, 5000);

    $format = strtoupper(trim($_GET['format'] ?? 'CSV'));
    $allowedFormats = ['CSV', 'JSON'];
    if (!in_array($format, $allowedFormats, true)) {
        $format = 'CSV';
    }

    $params = [
        'omsId' => $oms['id'],
        'count' => $count,
        'documentFormat' => $format,
    ];

    if ($bufferId !== '') {
        $params['bufferId'] = $bufferId;
    }

    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    // ИСПРАВЛЕНО: Используем /order (единственное число)
    $result = apiRequestRaw(
        SUZ_API_URL . '/order/' . rawurlencode($orderId) . '/codes?' . $query,
        'GET',
        [
            'clientToken: ' . $token,
            'Accept: application/octet-stream',
        ]
    );

    echo json_encode([
        'ok' => true,
        'data' => [
            'contentType' => $result['contentType'],
            'base64' => base64_encode($result['body']),
            'size' => strlen($result['body']),
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}