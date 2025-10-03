<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['payload']) || empty($input['signature'])) {
        throw new Exception('Не указаны payload или signature');
    }

    // КРИТИЧНО: Очистка подписи
    $signature = preg_replace('/[\r\n\s]/', '', $input['signature']);
    
    error_log('=== ORDER DEBUG ===');
    error_log('Payload length: ' . strlen($input['payload']));
    error_log('Payload: ' . $input['payload']);
    error_log('Signature length (original): ' . strlen($input['signature']));
    error_log('Signature length (cleaned): ' . strlen($signature));
    error_log('Signature prefix: ' . substr($signature, 0, 100));

    $token = getToken('suz_token');
    if (!$token) {
        http_response_code(401);
        throw new Exception('clientToken отсутствует');
    }
    
    $oms = getOmsSettings();
    if (!$oms['id']) {
        throw new Exception('OMS ID не сохранен. Заполните и сохраните настройки OMS.');
    }
    
    // ИСПРАВЛЕНО: Используем очищенную подпись
    $response = apiRequest(
        SUZ_API_URL . '/order?omsId=' . urlencode($oms['id']),
        'POST',
        [
            'Content-Type: application/json',
            'Accept: application/json',
            'clientToken: ' . $token,
            'X-Signature: ' . $signature,  // ← Используем очищенную
        ],
        $input['payload']
    );

    echo json_encode(['ok' => true, 'response' => $response]);
    
} catch (Exception $e) {
    error_log('Order error: ' . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}