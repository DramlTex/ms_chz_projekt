<?php
/**
 * API Backend для интеграции МойСклад ↔ НК ↔ Честный знак
 * ИСПРАВЛЕНО: Полное проксирование всех запросов для обхода CORS
 */

session_start();

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// CORS headers - обязательно для работы из браузера
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuration
define('NK_API_URL', 'https://markirovka.crpt.ru/api/v3');
define('SUZ_API_URL', 'https://suzgrid.crpt.ru/api/v3');
define('MS_API_URL', 'https://api.moysklad.ru/api/remap/1.2');

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true);
$action = $data['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        // МойСклад
        case 'login_moysklad':
            handleLoginMoySklad($data);
            break;
            
        case 'get_products':
            handleGetProducts($data);
            break;
            
        case 'update_gtin':
            handleUpdateGtin($data);
            break;
            
        // НК Авторизация
        case 'nk_challenge':
            handleNKChallenge();
            break;
            
        case 'nk_signin':
            handleNKSignIn($data);
            break;
            
        // СУЗ Авторизация
        case 'suz_challenge':
            handleSUZChallenge($data);
            break;
            
        case 'suz_signin':
            handleSUZSignIn($data);
            break;
            
        case 'save_oms':
            handleSaveOMS($data);
            break;
            
        // НК API - НОВОЕ! Проксирование запросов
        case 'nk_check_feacn':
            handleNKCheckFeacn($data);
            break;
            
        case 'nk_get_category':
            handleNKGetCategory($data);
            break;
            
        case 'nk_create_card':
            handleNKCreateCard($data);
            break;
            
        case 'nk_feed_status':
            handleNKFeedStatus($data);
            break;
            
        case 'nk_get_gtin':
            handleNKGetGtin($data);
            break;
            
        // СУЗ API
        case 'suz_create_order':
            handleSUZCreateOrder($data);
            break;
            
        case 'suz_check_status':
            handleSUZCheckStatus($data);
            break;
            
        default:
            throw new Exception('Unknown action: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * МойСклад - Авторизация
 */
function handleLoginMoySklad($data) {
    $login = $data['login'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($login) || empty($password)) {
        throw new Exception('Логин и пароль обязательны');
    }
    
    $authHeader = 'Basic ' . base64_encode($login . ':' . $password);
    
    $response = apiRequest(MS_API_URL . '/entity/product?limit=1', 'GET', null, [
        'Authorization: ' . $authHeader
    ]);
    
    if ($response['code'] === 200) {
        $_SESSION['ms_login'] = $login;
        $_SESSION['ms_password'] = $password;
        
        echo json_encode([
            'success' => true,
            'message' => 'Авторизация успешна'
        ]);
    } else {
        throw new Exception('Неверный логин или пароль');
    }
}

/**
 * МойСклад - Получить товары
 */
function handleGetProducts($data) {
    if (!isset($_SESSION['ms_login']) || !isset($_SESSION['ms_password'])) {
        throw new Exception('Не авторизован в МойСклад');
    }
    
    $authHeader = 'Basic ' . base64_encode($_SESSION['ms_login'] . ':' . $_SESSION['ms_password']);
    $limit = $data['limit'] ?? 100;
    $offset = $data['offset'] ?? 0;
    
    $response = apiRequest(
        MS_API_URL . '/entity/product?limit=' . $limit . '&offset=' . $offset,
        'GET',
        null,
        ['Authorization: ' . $authHeader]
    );
    
    echo $response['body'];
}

/**
 * МойСклад - Обновить GTIN
 */
function handleUpdateGtin($data) {
    if (!isset($_SESSION['ms_login']) || !isset($_SESSION['ms_password'])) {
        throw new Exception('Не авторизован в МойСклад');
    }
    
    $productId = $data['productId'] ?? '';
    $gtin = $data['gtin'] ?? '';
    
    if (empty($productId) || empty($gtin)) {
        throw new Exception('Не указан ID товара или GTIN');
    }
    
    $authHeader = 'Basic ' . base64_encode($_SESSION['ms_login'] . ':' . $_SESSION['ms_password']);
    
    // Получаем текущий товар
    $response = apiRequest(
        MS_API_URL . '/entity/product/' . $productId,
        'GET',
        null,
        ['Authorization: ' . $authHeader]
    );
    
    $product = json_decode($response['body'], true);
    
    // Добавляем GTIN в штрихкоды
    $barcodes = $product['barcodes'] ?? [];
    $barcodes[] = ['ean13' => $gtin];
    
    // Обновляем товар
    $updateData = ['barcodes' => $barcodes];
    
    $updateResponse = apiRequest(
        MS_API_URL . '/entity/product/' . $productId,
        'PUT',
        json_encode($updateData),
        [
            'Authorization: ' . $authHeader,
            'Content-Type: application/json'
        ]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'GTIN успешно добавлен'
    ]);
}

/**
 * НК - Получить challenge для авторизации
 */
function handleNKChallenge() {
    $response = apiRequest(NK_API_URL . '/true-api/auth/key', 'GET');
    
    if ($response['code'] === 200) {
        $body = json_decode($response['body'], true);
        echo json_encode([
            'success' => true,
            'uuid' => $body['uuid'],
            'data' => $body['data']
        ]);
    } else {
        throw new Exception('Ошибка получения challenge');
    }
}

/**
 * НК - Авторизация с подписью
 */
function handleNKSignIn($data) {
    $uuid = $data['uuid'] ?? '';
    $signature = $data['signature'] ?? '';
    
    if (empty($uuid) || empty($signature)) {
        throw new Exception('UUID или подпись не указаны');
    }
    
    $body = json_encode([
        'uuid' => $uuid,
        'data' => $signature
    ]);
    
    $response = apiRequest(
        NK_API_URL . '/true-api/auth/simpleSignIn',
        'POST',
        $body,
        ['Content-Type: application/json']
    );
    
    if ($response['code'] === 200) {
        $responseData = json_decode($response['body'], true);
        $_SESSION['nk_token'] = $responseData['token'];
        
        echo json_encode([
            'success' => true,
            'token' => $responseData['token']
        ]);
    } else {
        throw new Exception('Ошибка авторизации в НК: ' . $response['body']);
    }
}

/**
 * СУЗ - Получить challenge
 */
function handleSUZChallenge($data) {
    $response = apiRequest(NK_API_URL . '/true-api/auth/key', 'GET');
    
    if ($response['code'] === 200) {
        $body = json_decode($response['body'], true);
        echo json_encode([
            'success' => true,
            'uuid' => $body['uuid'],
            'data' => $body['data']
        ]);
    } else {
        throw new Exception('Ошибка получения challenge для СУЗ');
    }
}

/**
 * СУЗ - Авторизация
 */
function handleSUZSignIn($data) {
    $uuid = $data['uuid'] ?? '';
    $signature = $data['signature'] ?? '';
    $omsConnection = $data['omsConnection'] ?? $_SESSION['oms_connection'] ?? '';
    $omsId = $data['omsId'] ?? $_SESSION['oms_id'] ?? '';
    
    if (empty($uuid) || empty($signature) || empty($omsConnection)) {
        throw new Exception('Не все данные для авторизации СУЗ указаны');
    }
    
    $body = json_encode([
        'uuid' => $uuid,
        'data' => $signature
    ]);
    
    $response = apiRequest(
        NK_API_URL . '/auth/simpleSignIn/' . $omsConnection,
        'POST',
        $body,
        ['Content-Type: application/json']
    );
    
    if ($response['code'] === 200) {
        $responseData = json_decode($response['body'], true);
        $_SESSION['suz_token'] = $responseData['token'];
        $_SESSION['oms_id'] = $omsId;
        
        echo json_encode([
            'success' => true,
            'token' => $responseData['token']
        ]);
    } else {
        throw new Exception('Ошибка авторизации в СУЗ: ' . $response['body']);
    }
}

/**
 * Сохранить OMS настройки
 */
function handleSaveOMS($data) {
    $omsConnection = $data['omsConnection'] ?? '';
    $omsId = $data['omsId'] ?? '';
    
    if (empty($omsConnection) || empty($omsId)) {
        throw new Exception('OMS Connection и OMS ID обязательны');
    }
    
    $_SESSION['oms_connection'] = $omsConnection;
    $_SESSION['oms_id'] = $omsId;
    
    echo json_encode([
        'success' => true,
        'message' => 'OMS настройки сохранены'
    ]);
}

/**
 * НК - Проверить ТН ВЭД (маркируемость)
 * НОВОЕ! Проксирование для обхода CORS
 */
function handleNKCheckFeacn($data) {
    $tnved = $data['tnved'] ?? '';
    $token = $_SESSION['nk_token'] ?? '';
    
    if (empty($tnved)) {
        throw new Exception('ТН ВЭД код не указан');
    }
    
    if (empty($token)) {
        throw new Exception('Не авторизован в НК');
    }
    
    $body = json_encode([
        'feacn' => [substr($tnved, 0, 4)]
    ]);
    
    $response = apiRequest(
        NK_API_URL . '/check/feacn',
        'POST',
        $body,
        [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]
    );
    
    if ($response['code'] === 200) {
        echo $response['body'];
    } else {
        throw new Exception('Ошибка проверки ТН ВЭД: ' . $response['body']);
    }
}

/**
 * НК - Получить категорию по ТН ВЭД
 * НОВОЕ! Проксирование для обхода CORS
 */
function handleNKGetCategory($data) {
    $tnved = $data['tnved'] ?? '';
    $token = $_SESSION['nk_token'] ?? '';
    
    if (empty($tnved)) {
        throw new Exception('ТН ВЭД код не указан');
    }
    
    if (empty($token)) {
        throw new Exception('Не авторизован в НК');
    }
    
    $response = apiRequest(
        NK_API_URL . '/categories/by-feacn?feacn=' . substr($tnved, 0, 4),
        'GET',
        null,
        ['Authorization: Bearer ' . $token]
    );
    
    if ($response['code'] === 200) {
        echo $response['body'];
    } else {
        throw new Exception('Ошибка получения категории: ' . $response['body']);
    }
}

/**
 * НК - Создать карточку
 * НОВОЕ! Проксирование для обхода CORS
 */
function handleNKCreateCard($data) {
    $cardData = $data['cardData'] ?? null;
    $token = $_SESSION['nk_token'] ?? '';
    
    if (empty($cardData)) {
        throw new Exception('Данные карточки не указаны');
    }
    
    if (empty($token)) {
        throw new Exception('Не авторизован в НК');
    }
    
    $response = apiRequest(
        NK_API_URL . '/feed',
        'POST',
        json_encode($cardData),
        [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]
    );
    
    if ($response['code'] === 200) {
        echo $response['body'];
    } else {
        throw new Exception('Ошибка создания карточки: ' . $response['body']);
    }
}

/**
 * НК - Получить статус обработки карточки
 * НОВОЕ! Проксирование для обхода CORS
 */
function handleNKFeedStatus($data) {
    $feedId = $data['feedId'] ?? '';
    $token = $_SESSION['nk_token'] ?? '';
    
    if (empty($feedId)) {
        throw new Exception('Feed ID не указан');
    }
    
    if (empty($token)) {
        throw new Exception('Не авторизован в НК');
    }
    
    $response = apiRequest(
        NK_API_URL . '/feed-status?feed_id=' . $feedId,
        'GET',
        null,
        ['Authorization: Bearer ' . $token]
    );
    
    if ($response['code'] === 200) {
        echo $response['body'];
    } else {
        throw new Exception('Ошибка получения статуса: ' . $response['body']);
    }
}

/**
 * НК - Получить технический GTIN
 * НОВОЕ! Проксирование для обхода CORS
 */
function handleNKGetGtin($data) {
    $quantity = $data['quantity'] ?? 1;
    $token = $_SESSION['nk_token'] ?? '';
    
    if (empty($token)) {
        throw new Exception('Не авторизован в НК');
    }
    
    $response = apiRequest(
        NK_API_URL . '/gtin/retrieve?quantity=' . $quantity,
        'GET',
        null,
        ['Authorization: Bearer ' . $token]
    );
    
    if ($response['code'] === 200) {
        echo $response['body'];
    } else {
        throw new Exception('Ошибка получения GTIN: ' . $response['body']);
    }
}

/**
 * СУЗ - Создать заказ КМ
 */
function handleSUZCreateOrder($data) {
    $orderData = $data['orderData'] ?? null;
    $signature = $data['signature'] ?? '';
    $token = $_SESSION['suz_token'] ?? '';
    $omsId = $_SESSION['oms_id'] ?? '';
    
    if (empty($orderData) || empty($signature)) {
        throw new Exception('Данные заказа или подпись не указаны');
    }
    
    if (empty($token) || empty($omsId)) {
        throw new Exception('Не авторизован в СУЗ');
    }
    
    $response = apiRequest(
        SUZ_API_URL . '/order?omsId=' . $omsId,
        'POST',
        json_encode($orderData),
        [
            'Content-Type: application/json',
            'clientToken: ' . $token,
            'X-Signature: ' . $signature
        ]
    );
    
    if ($response['code'] === 200) {
        echo $response['body'];
    } else {
        throw new Exception('Ошибка создания заказа: ' . $response['body']);
    }
}

/**
 * СУЗ - Проверить статус заказа
 */
function handleSUZCheckStatus($data) {
    $orderId = $data['orderId'] ?? '';
    $token = $_SESSION['suz_token'] ?? '';
    $omsId = $_SESSION['oms_id'] ?? '';
    
    if (empty($token) || empty($omsId)) {
        throw new Exception('Не авторизован в СУЗ');
    }
    
    $url = SUZ_API_URL . '/order/status?omsId=' . $omsId;
    if (!empty($orderId)) {
        $url .= '&orderId=' . $orderId;
    }
    
    $response = apiRequest(
        $url,
        'GET',
        null,
        ['clientToken: ' . $token]
    );
    
    if ($response['code'] === 200) {
        echo $response['body'];
    } else {
        throw new Exception('Ошибка получения статуса: ' . $response['body']);
    }
}

/**
 * Выполнить API запрос
 */
function apiRequest($url, $method = 'GET', $body = null, $headers = []) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        throw new Exception('CURL error: ' . $error);
    }
    
    return [
        'code' => $httpCode,
        'body' => $response
    ];
}