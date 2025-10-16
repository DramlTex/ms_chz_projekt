<?php
/**
 * API Backend для интеграции МойСклад ↔ НК ↔ Честный знак
 * ИСПРАВЛЕНО: Использует существующую систему авторизации + проксирование для обхода CORS
 */

// Подключаем существующие модули
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/api_ms/ms_api.php';
require_once __DIR__ . '/crpt_api.php';

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true);
$action = $data['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        // МойСклад API (используем существующие функции из api_ms/)
        // Авторизация через login.html → api_ms/login.php!
        
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
            
        // НК API - Проксирование запросов
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

// ============================================
// МойСклад функции
// ============================================

/**
 * Получить товары из МойСклад
 * Использует существующую систему авторизации из auth.php
 */
function handleGetProducts($data) {
    // Проверяем авторизацию через существующую функцию
    if (!is_authenticated()) {
        throw new Exception('Не авторизован в МойСклад');
    }
    
    try {
        // Используем ms_api_request из api_ms/ms_api.php
        $limit = $data['limit'] ?? 100;
        $offset = $data['offset'] ?? 0;
        
        $url = 'https://api.moysklad.ru/api/remap/1.2/entity/assortment';
        $url .= '?limit=' . $limit;
        $url .= '&offset=' . $offset;
        $url .= '&expand=product,product.attributes,attributes';
        
        // ms_api_request автоматически берёт credentials из сессии!
        $body = ms_api_request($url);
        
        if (!isset($body['rows']) || !is_array($body['rows'])) {
            throw new Exception('Некорректный ответ сервера');
        }
        
        $products = [];
        
        foreach ($body['rows'] as $row) {
            $product = normalize_assortment_row($row);
            
            if ($product !== null) {
                $products[] = $product;
            }
        }
        
        echo json_encode([
            'success' => true,
            'products' => $products
        ]);
        
    } catch (RuntimeException $e) {
        throw new Exception('Ошибка загрузки товаров: ' . $e->getMessage());
    }
}

/**
 * Привести товар/вариант МойСклад к единому формату
 */
function normalize_assortment_row(array $row): ?array {
    $type = $row['meta']['type'] ?? '';
    
    if (!in_array($type, ['product', 'variant'], true)) {
        return null;
    }
    
    $parent = null;
    
    if ($type === 'variant' && isset($row['product']) && is_array($row['product'])) {
        $parent = $row['product'];
    }
    
    $attributes = [];

    if ($parent && isset($parent['attributes']) && is_array($parent['attributes'])) {
        $attributes = array_merge($attributes, $parent['attributes']);
    }

    if (isset($row['attributes']) && is_array($row['attributes'])) {
        $attributes = array_merge($attributes, $row['attributes']);
    }

    $barcodes = [];

    if ($parent && isset($parent['barcodes']) && is_array($parent['barcodes'])) {
        $barcodes = array_merge($barcodes, $parent['barcodes']);
    }

    if (isset($row['barcodes']) && is_array($row['barcodes'])) {
        $barcodes = array_merge($barcodes, $row['barcodes']);
    }

    $barcodes = array_values(array_filter($barcodes, function ($barcode) {
        return is_array($barcode) || is_string($barcode);
    }));

    $characteristics = [];

    if ($parent && isset($parent['characteristics']) && is_array($parent['characteristics'])) {
        $characteristics = array_merge($characteristics, $parent['characteristics']);
    }

    if (isset($row['characteristics']) && is_array($row['characteristics'])) {
        $characteristics = array_merge($characteristics, $row['characteristics']);
    }

    $tnved = $row['tnved'] ?? null;

    if (!$tnved && $parent) {
        $tnved = $parent['tnved'] ?? null;
    }

    return [
        'id' => $row['id'] ?? null,
        'name' => $row['name'] ?? ($parent['name'] ?? ''),
        'code' => $row['code'] ?? ($parent['code'] ?? ''),
        'article' => $row['article'] ?? ($parent['article'] ?? ''),
        'description' => $row['description'] ?? ($parent['description'] ?? ''),
        'attributes' => $attributes,
        'type' => $type,
        'parent' => $parent,
        'tnved' => $tnved,
        'barcodes' => $barcodes,
        'characteristics' => array_values($characteristics)
    ];
}

/**
 * Обновить GTIN товара в МойСклад
 */
function handleUpdateGtin($data) {
    if (!is_authenticated()) {
        throw new Exception('Не авторизован в МойСклад');
    }
    
    $productId = $data['productId'] ?? '';
    $gtin = $data['gtin'] ?? '';
    
    if (empty($productId) || empty($gtin)) {
        throw new Exception('Не указан ID товара или GTIN');
    }
    
    try {
        // Получаем текущий товар
        $url = 'https://api.moysklad.ru/api/remap/1.2/entity/product/' . $productId;
        $product = ms_api_request($url);
        
        // Добавляем GTIN в штрихкоды
        $barcodes = $product['barcodes'] ?? [];
        $barcodes[] = ['ean13' => $gtin];
        
        // Обновляем товар
        $updateData = ['barcodes' => $barcodes];
        $result = ms_api_request($url, null, null, 'PUT', $updateData);
        
        echo json_encode([
            'success' => true,
            'message' => 'GTIN успешно добавлен',
            'product' => $result
        ]);
        
    } catch (RuntimeException $e) {
        throw new Exception('Ошибка обновления GTIN: ' . $e->getMessage());
    }
}

// ============================================
// НК Авторизация
// ============================================

/**
 * НК - Получить challenge для авторизации
 */
function handleNKChallenge() {
    $response = apiRequest(TRUE_API_URL . '/auth/key');

    if (empty($response['uuid']) || empty($response['data'])) {
        throw new Exception('Ошибка получения challenge');
    }

    echo json_encode([
        'success' => true,
        'uuid' => $response['uuid'],
        'data' => $response['data']
    ], JSON_UNESCAPED_UNICODE);
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

    $response = apiRequest(
        TRUE_API_URL . '/auth/simpleSignIn',
        'POST',
        null,
        [
            'uuid' => $uuid,
            'data' => $signature,
            'unitedToken' => true
        ]
    );

    $token = $response['uuidToken'] ?? $response['token'] ?? null;

    if (!$token) {
        throw new Exception('Токен НК не получен');
    }

    $expiresIn = $response['expiresIn'] ?? $response['expires_in'] ?? null;
    $expiresAt = $expiresIn ? time() + (int)$expiresIn : null;

    setToken('nk_token', $token, $expiresAt);

    echo json_encode([
        'success' => true,
        'token' => $token,
        'expiresAt' => $expiresAt
    ], JSON_UNESCAPED_UNICODE);
}

// ============================================
// СУЗ Авторизация
// ============================================

/**
 * СУЗ - Получить challenge
 */
function handleSUZChallenge($data) {
    $response = apiRequest(TRUE_API_URL . '/auth/key');

    if (empty($response['uuid']) || empty($response['data'])) {
        throw new Exception('Ошибка получения challenge для СУЗ');
    }

    echo json_encode([
        'success' => true,
        'uuid' => $response['uuid'],
        'data' => $response['data']
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * СУЗ - Авторизация
 */
function handleSUZSignIn($data) {
    $uuid = $data['uuid'] ?? '';
    $signature = $data['signature'] ?? '';
    $omsSettings = getOmsSettings();
    $omsConnection = $data['omsConnection'] ?? ($omsSettings['connection'] ?? '');
    $omsId = $data['omsId'] ?? ($omsSettings['id'] ?? '');

    if (empty($uuid) || empty($signature) || empty($omsConnection)) {
        throw new Exception('Не все данные для авторизации СУЗ указаны');
    }

    if (empty($omsId)) {
        throw new Exception('OMS ID не указан');
    }

    $response = apiRequest(
        TRUE_API_URL . '/auth/simpleSignIn/' . urlencode($omsConnection),
        'POST',
        null,
        [
            'uuid' => $uuid,
            'data' => $signature,
            'unitedToken' => true
        ]
    );

    $token = $response['client_token'] ?? $response['clientToken'] ?? $response['uuidToken'] ?? $response['token'] ?? null;

    if (!$token) {
        throw new Exception('clientToken не получен');
    }

    $expiresIn = $response['expires_in'] ?? $response['expiresIn'] ?? null;
    $expiresAt = $expiresIn ? time() + (int)$expiresIn : null;

    setToken('suz_token', $token, $expiresAt);
    setOmsSettings($omsConnection, $omsId);

    echo json_encode([
        'success' => true,
        'token' => $token,
        'expiresAt' => $expiresAt
    ], JSON_UNESCAPED_UNICODE);
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

    setOmsSettings($omsConnection, $omsId);

    echo json_encode([
        'success' => true,
        'message' => 'OMS настройки сохранены'
    ], JSON_UNESCAPED_UNICODE);
}

// ============================================
// НК API - Проксирование
// ============================================

/**
 * НК - Проверить ТН ВЭД (маркируемость)
 */
function handleNKCheckFeacn($data) {
    $tnved = $data['tnved'] ?? '';
    $token = getToken('nk_token');

    if (empty($tnved)) {
        throw new Exception('ТН ВЭД код не указан');
    }

    if (empty($token)) {
        throw new Exception('Не авторизован в НК');
    }

    $response = apiRequest(
        NK_API_URL . '/check/feacn',
        'POST',
        [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ],
        [
            'feacn' => [substr($tnved, 0, 4)]
        ]
    );

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

/**
 * НК - Получить категорию по ТН ВЭД
 */
function handleNKGetCategory($data) {
    $tnved = $data['tnved'] ?? '';
    $token = getToken('nk_token');

    if (empty($tnved)) {
        throw new Exception('ТН ВЭД код не указан');
    }

    if (empty($token)) {
        throw new Exception('Не авторизован в НК');
    }

    $response = apiRequest(
        NK_API_URL . '/categories/by-feacn?feacn=' . substr($tnved, 0, 4),
        'GET',
        [
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ]
    );

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

/**
 * НК - Создать карточку
 */
function handleNKCreateCard($data) {
    $cardData = $data['cardData'] ?? null;
    $token = getToken('nk_token');

    if (empty($cardData)) {
        throw new Exception('Данные карточки не указаны');
    }

    if (empty($token)) {
        throw new Exception('Не авторизован в НК');
    }

    $response = apiRequest(
        NK_API_URL . '/feed',
        'POST',
        [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ],
        $cardData
    );

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

/**
 * НК - Получить статус обработки карточки
 */
function handleNKFeedStatus($data) {
    $feedId = $data['feedId'] ?? '';
    $token = getToken('nk_token');

    if (empty($feedId)) {
        throw new Exception('Feed ID не указан');
    }

    if (empty($token)) {
        throw new Exception('Не авторизован в НК');
    }

    $response = apiRequest(
        NK_API_URL . '/feed-status?feed_id=' . $feedId,
        'GET',
        [
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ]
    );

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

/**
 * НК - Получить технический GTIN
 */
function handleNKGetGtin($data) {
    $quantity = $data['quantity'] ?? 1;
    $token = getToken('nk_token');

    if (empty($token)) {
        throw new Exception('Не авторизован в НК');
    }

    $response = apiRequest(
        NK_API_URL . '/gtin/retrieve?quantity=' . $quantity,
        'GET',
        [
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ]
    );

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

// ============================================
// СУЗ API
// ============================================

/**
 * СУЗ - Создать заказ КМ
 */
function handleSUZCreateOrder($data) {
    $orderData = $data['orderData'] ?? null;
    $signature = $data['signature'] ?? '';
    $token = getToken('suz_token');
    $omsSettings = getOmsSettings();
    $omsId = $omsSettings['id'] ?? '';

    if (empty($orderData) || empty($signature)) {
        throw new Exception('Данные заказа или подпись не указаны');
    }

    $cleanSignature = preg_replace('/[\r\n\s]+/', '', $signature);

    if (empty($cleanSignature)) {
        throw new Exception('Подпись не может быть пустой');
    }

    if (empty($token) || empty($omsId)) {
        throw new Exception('Не авторизован в СУЗ');
    }

    $response = apiRequest(
        SUZ_API_URL . '/order?omsId=' . $omsId,
        'POST',
        [
            'Content-Type: application/json',
            'Accept: application/json',
            'clientToken: ' . $token,
            'OMS-Id: ' . $omsId,
            'X-Signature: ' . $cleanSignature
        ],
        $orderData
    );

    $orderId = $response['orderId'] ?? $response['omsOrderId'] ?? $response['id'] ?? null;
    $status = $response['status'] ?? $response['state'] ?? null;

    echo json_encode([
        'success' => true,
        'orderId' => $orderId,
        'status' => $status,
        'raw' => $response
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * СУЗ - Проверить статус заказа
 */
function handleSUZCheckStatus($data) {
    $omsSettings = getOmsSettings();
    $omsId = $omsSettings['id'] ?? '';
    $omsConnection = $omsSettings['connection'] ?? '';
    $token = getToken('suz_token');

    if (empty($omsId) || empty($token) || empty($omsConnection)) {
        throw new Exception('Нет данных для проверки статуса');
    }

    $orderId = $data['orderId'] ?? '';
    if (empty($orderId)) {
        throw new Exception('Не указан номер заказа');
    }

    $url = SUZ_API_URL . '/order/status?omsId=' . $omsId;
    $url .= '&omsConnection=' . urlencode($omsConnection);
    $url .= '&orderId=' . urlencode($orderId);

    $response = apiRequest(
        $url,
        'GET',
        [
            'Accept: application/json',
            'clientToken: ' . $token,
            'OMS-Id: ' . $omsId
        ]
    );

    $orderId = $response['orderId'] ?? $response['omsOrderId'] ?? $response['id'] ?? $orderId;
    $status = $response['status'] ?? $response['state'] ?? null;
    $buffers = $response['buffers'] ?? [];

    echo json_encode([
        'success' => true,
        'orderId' => $orderId,
        'status' => $status,
        'buffers' => is_array($buffers) ? $buffers : [],
        'raw' => $response
    ], JSON_UNESCAPED_UNICODE);
}

// ============================================
// Вспомогательные функции
// ============================================