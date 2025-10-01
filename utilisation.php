<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['payload']) || empty($input['signature'])) {
        throw new Exception('Не указаны payload или signature');
    }

    $token = getToken('suz_token');
    if (!$token) {
        http_response_code(401);
        throw new Exception('clientToken отсутствует');
    }

    $oms = getOmsSettings();
    if (empty($oms['id'])) {
        throw new Exception('OMS ID не сохранен. Заполните и сохраните настройки OMS.');
    }

    $response = apiRequest(
        SUZ_API_URL . '/utilisation?omsId=' . urlencode($oms['id']),
        'POST',
        [
            'Content-Type: application/json',
            'Accept: application/json',
            'clientToken: ' . $token,
            'X-Signature: ' . $input['signature'],
        ],
        $input['payload']
    );

    echo json_encode(['ok' => true, 'response' => $response]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
