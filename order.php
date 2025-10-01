<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['order']) || empty($input['signature'])) {
        throw new Exception('Не указаны order или signature');
    }
    
    $token = getToken('suz_token');
    if (!$token) {
        http_response_code(401);
        throw new Exception('clientToken отсутствует');
    }
    
    // Получаем OMS ID из сессии
    $oms = getOmsSettings();
    if (!$oms['id']) {
        throw new Exception('OMS ID не сохранен. Заполните и сохраните настройки OMS.');
    }
    
    // Отправка заказа в СУЗ
    $response = apiRequest(
        SUZ_API_URL . '/order?omsId=' . urlencode($oms['id']),
        'POST',
        [
            'Content-Type: application/json',
            'clientToken: ' . $token,
            'X-Signature: ' . $input['signature'],
        ],
        $input['order']
    );
    
    echo json_encode(['ok' => true, 'response' => $response]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}