<?php
require_once 'config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    // 1. Получить challenge для NK токена
    if ($action === 'nk-challenge') {
        // ИСПРАВЛЕНО: /auth/key без /cert
        $response = apiRequest(TRUE_API_URL . '/auth/key', 'GET');
        echo json_encode($response);
        exit;
    }
    
    // 2. Обменять подпись на NK токен
    if ($action === 'nk-signin') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $response = apiRequest(
        TRUE_API_URL . '/auth/simpleSignIn',
        'POST',
        null,
        [
            'uuid' => $input['uuid'],
            'data' => $input['signature'],
            'unitedToken' => true
        ]
    );
        
        // ИСПРАВЛЕНО: /auth/simpleSignIn без /cert
        $response = apiRequest(
    TRUE_API_URL . '/auth/simpleSignIn',
    'POST',
    null,
    [
        'uuid' => $input['uuid'],
        'data' => $input['signature'],
        'unitedToken' => true  // ← ДОБАВИТЬ
    ]
);
        
        $token = $response['uuidToken'] ?? $response['token'] ?? null;
    if (!$token) {
        throw new Exception('Токен не получен');
    }
    
    $expiresAt = null;
    if (!empty($response['expiresIn'])) {
        $expiresAt = time() + (int)$response['expiresIn'];
    }
    
    setToken('nk_token', $token, $expiresAt);  // ← Один раз, после определения $expiresAt
    
    echo json_encode(['ok' => true, 'expiresAt' => $expiresAt]);
    exit;
}
    
    // 3. Получить challenge для SUZ токена
    if ($action === 'suz-challenge') {
        // ИСПРАВЛЕНО: /auth/key без /cert
        $response = apiRequest(TRUE_API_URL . '/auth/key', 'GET');
        echo json_encode($response);
        exit;
    }
    
    // 4. Обменять подпись на SUZ clientToken
    if ($action === 'suz-signin') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['omsConnection']) || empty($input['omsId'])) {
            throw new Exception('Не указаны omsConnection или omsId');
        }
        
        // ИСПРАВЛЕНО: /auth/simpleSignIn/{omsConnection} без /cert
        $response = apiRequest(
    TRUE_API_URL . '/auth/simpleSignIn/' . urlencode($input['omsConnection']),
    'POST',
    null,
    [
        'uuid' => $input['uuid'],
        'data' => $input['signature'],
        'unitedToken' => true  // ← ДОБАВИТЬ
    ]
);
        
        $token = $response['client_token'] ?? $response['clientToken'] ?? $response['uuidToken'] ?? $response['token'] ?? null;
        
        if (!$token) {
            error_log('SUZ auth response: ' . json_encode($response));
            throw new Exception('clientToken не получен. Ответ: ' . json_encode($response));
        }
        
        $expiresAt = null;
        if (!empty($response['expires_in'])) {
            $expiresAt = time() + (int)$response['expires_in'];
        } elseif (!empty($response['expiresIn'])) {
            $expiresAt = time() + (int)$response['expiresIn'];
        }
        
        setToken('suz_token', $token, $expiresAt);
        
        echo json_encode(['ok' => true, 'expiresAt' => $expiresAt]);
        exit;
    }
    
    // 5. Сохранить настройки OMS
    if ($action === 'save-oms') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $connection = trim($input['omsConnection'] ?? '');
        $id = trim($input['omsId'] ?? '');
        
        if (!$connection || !$id) {
            throw new Exception('Заполните OMS Connection и OMS ID');
        }
        
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $connection)) {
            throw new Exception('OMS Connection должен быть в формате GUID');
        }
        
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            throw new Exception('OMS ID должен быть в формате GUID');
        }
        
        setOmsSettings($connection, $id);
        
        echo json_encode(['ok' => true]);
        exit;
    }
    
    // 6. Получить настройки OMS
    if ($action === 'get-oms') {
        $settings = getOmsSettings();
        echo json_encode($settings);
        exit;
    }
    
    // 7. Проверить статус токенов
    if ($action === 'status') {
        $nkToken = getToken('nk_token');
        $suzToken = getToken('suz_token');
        $oms = getOmsSettings();
        
        echo json_encode([
            'nk' => [
                'active' => $nkToken !== null,
                'expiresAt' => $_SESSION['nk_token']['expires_at'] ?? null,
            ],
            'suz' => [
                'active' => $suzToken !== null,
                'expiresAt' => $_SESSION['suz_token']['expires_at'] ?? null,
            ],
            'oms' => $oms,
        ]);
        exit;
    }
    
    // 8. Сбросить токены
    if ($action === 'reset') {
        clearToken('nk_token');
        clearToken('suz_token');
        clearOmsSettings();
        echo json_encode(['ok' => true]);
        exit;
    }
    
    throw new Exception('Неизвестное действие');
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}