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

    $quantity = isset($_GET['quantity']) ? (int)$_GET['quantity'] : 1000;
    if ($quantity <= 0) {
        throw new Exception('Количество КМ должно быть положительным числом');
    }
    $quantity = min($quantity, 150000);

    $gtin = preg_replace('/\s+/', '', $_GET['gtin'] ?? '');
    if ($gtin === '') {
        throw new Exception('Не указан GTIN');
    }

    if (!preg_match('/^\d{8,14}$/', $gtin)) {
        throw new Exception('GTIN должен содержать только цифры (8-14 знаков)');
    }

    if (strlen($gtin) < 14) {
        $gtin = str_pad($gtin, 14, '0', STR_PAD_LEFT);
    }

    if (!preg_match('/^\d{14}$/', $gtin)) {
        throw new Exception('GTIN должен содержать 14 цифр после нормализации');
    }

    $params = [
        'omsId' => $oms['id'],
        'orderId' => $orderId,
        'quantity' => $quantity,
        'gtin' => $gtin,
    ];

    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    $result = apiRequest(
        SUZ_API_URL . '/codes?' . $query,
        'GET',
        [
            'clientToken: ' . $token,
            'Accept: application/json',
        ]
    );

    echo json_encode([
        'ok' => true,
        'data' => [
            'omsId' => $result['omsId'] ?? null,
            'codes' => $result['codes'] ?? [],
            'blockId' => $result['blockId'] ?? null,
            'requestedQuantity' => $quantity,
            'receivedQuantity' => isset($result['codes']) && is_array($result['codes']) ? count($result['codes']) : 0,
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}