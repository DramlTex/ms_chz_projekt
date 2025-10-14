<?php
/**
 * API Backend для интеграции МойСклад ↔ НК ↔ Честный знак
 */

session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/api_ms/ms_api.php';

header('Content-Type: application/json; charset=utf-8');

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
$action = $data['action'] ?? '';

try {
    switch ($action) {
        case 'login_moysklad':
            handleLoginMoySklad($data);
            break;
            
        case 'get_products':
            handleGetProducts($data);
            break;
            
        case 'update_gtin':
            handleUpdateGtin($data);
            break;
            
        case 'nk_challenge':
            handleNKChallenge();
            break;
            
        case 'nk_signin':
            handleNKSignIn($data);
            break;
            
        case 'suz_challenge':
            handleSUZChallenge($data);
            break;
            
        case 'suz_signin':
            handleSUZSignIn($data);
            break;
            
        case 'create_nk_card':
            handleCreateNKCard($data);
            break;
            
        case 'create_km_order':
            handleCreateKMOrder($data);
            break;
            
        case 'check_order_status':
            handleCheckOrderStatus($data);
            break;
            
        default:
            throw new Exception('Unknown action: ' . $action);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Login to МойСклад with Basic Auth
 */
function handleLoginMoySklad($data) {
    $login = $data['login'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($login) || empty($password)) {
        throw new Exception('Логин и пароль обязательны');
    }
    
    // Test connection with Basic Auth
    $authHeader = 'Basic ' . base64_encode($login . ':' . $password);
    
    $response = apiRequest(MS_API_URL . '/entity/product?limit=1', 'GET', null, [
        'Authorization: ' . $authHeader
    ]);
    
    if ($response['code'] === 200) {
        // Save credentials in session
        set_user($login, $password);
        $_SESSION['ms_auth'] = get_auth_header();
        $_SESSION['ms_login'] = $login;
        
        echo json_encode([
            'success' => true,
            'message' => 'Успешный вход в МойСклад'
        ]);
    } else {
        throw new Exception('Неверный логин или пароль');
    }
}

/**
 * Get products from МойСклад
 */
function handleGetProducts($data) {
    if (!is_authenticated()) {
        throw new Exception('Не авторизован в МойСклад');
    }

    try {
        $body = ms_api_request(MS_API_URL . '/entity/product?limit=100');
    } catch (RuntimeException $e) {
        throw new Exception('Ошибка загрузки товаров: ' . $e->getMessage());
    }

    if (!isset($body['rows']) || !is_array($body['rows'])) {
        throw new Exception('Ошибка загрузки товаров: некорректный ответ сервера');
    }

    $products = [];

    foreach ($body['rows'] as $row) {
        $products[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'article' => $row['article'] ?? null,
            'code' => $row['code'] ?? null,
            'meta' => $row['meta']
        ];
    }

    echo json_encode([
        'success' => true,
        'products' => $products
    ]);
}

/**
 * Update product GTIN in МойСклад
 */
function handleUpdateGtin($data) {
    $authHeader = get_auth_header() ?? ($_SESSION['ms_auth'] ?? '');
    $productId = $data['productId'] ?? '';
    $gtin = $data['gtin'] ?? '';
    
    if (empty($authHeader) || empty($productId) || empty($gtin)) {
        throw new Exception('Отсутствуют необходимые параметры');
    }
    
    // Get current product data
    $getResponse = apiRequest(
        MS_API_URL . '/entity/product/' . $productId,
        'GET',
        null,
        ['Authorization: ' . $authHeader]
    );
    
    if ($getResponse['code'] !== 200) {
        throw new Exception('Ошибка получения данных товара');
    }
    
    $product = json_decode($getResponse['body'], true);
    
    // Add GTIN to barcodes
    $barcodes = $product['barcodes'] ?? [];
    
    // Format GTIN to 14 digits
    $formattedGtin = str_pad($gtin, 14, '0', STR_PAD_LEFT);
    
    // Check if GTIN already exists
    $exists = false;
    foreach ($barcodes as $barcode) {
        if ($barcode['ean13'] === $formattedGtin) {
            $exists = true;
            break;
        }
    }
    
    if (!$exists) {
        $barcodes[] = [
            'ean13' => $formattedGtin
        ];
    }
    
    // Update product
    $updateResponse = apiRequest(
        MS_API_URL . '/entity/product/' . $productId,
        'PUT',
        json_encode(['barcodes' => $barcodes]),
        [
            'Authorization: ' . $authHeader,
            'Content-Type: application/json'
        ]
    );
    
    if ($updateResponse['code'] === 200) {
        echo json_encode([
            'success' => true,
            'message' => 'GTIN успешно добавлен'
        ]);
    } else {
        throw new Exception('Ошибка обновления GTIN');
    }
}

/**
 * Get НК challenge
 */
function handleNKChallenge() {
    $response = apiRequest(
        NK_API_URL . '/true-api/auth/key',
        'GET'
    );
    
    if ($response['code'] !== 200) {
        throw new Exception('Failed to get НК challenge');
    }
    
    $body = json_decode($response['body'], true);
    
    echo json_encode([
        'success' => true,
        'uuid' => $body['uuid'],
        'data' => $body['data']
    ]);
}

/**
 * Sign in to НК
 */
function handleNKSignIn($data) {
    $uuid = $data['uuid'] ?? '';
    $signature = $data['signature'] ?? '';
    
    if (empty($uuid) || empty($signature)) {
        throw new Exception('Missing parameters');
    }
    
    $response = apiRequest(
        NK_API_URL . '/true-api/auth/simpleSignIn',
        'POST',
        json_encode([
            'uuid' => $uuid,
            'data' => $signature,
            'unitedToken' => true
        ]),
        ['Content-Type: application/json']
    );
    
    if ($response['code'] !== 200) {
        throw new Exception('Failed to sign in to НК');
    }
    
    $body = json_decode($response['body'], true);
    $_SESSION['nk_token'] = $body['token'];
    
    echo json_encode([
        'success' => true,
        'token' => $body['token']
    ]);
}

/**
 * Get СУЗ challenge
 */
function handleSUZChallenge($data) {
    $omsConnection = $data['omsConnection'] ?? '';
    
    $response = apiRequest(
        SUZ_API_URL . '/auth/key?omsId=' . urlencode($omsConnection),
        'GET'
    );
    
    if ($response['code'] !== 200) {
        throw new Exception('Failed to get СУЗ challenge');
    }
    
    $body = json_decode($response['body'], true);
    
    echo json_encode([
        'success' => true,
        'uuid' => $body['uuid'],
        'data' => $body['data']
    ]);
}

/**
 * Sign in to СУЗ
 */
function handleSUZSignIn($data) {
    $uuid = $data['uuid'] ?? '';
    $signature = $data['signature'] ?? '';
    $omsConnection = $data['omsConnection'] ?? '';
    $omsId = $data['omsId'] ?? '';
    
    if (empty($uuid) || empty($signature) || empty($omsConnection)) {
        throw new Exception('Missing parameters');
    }
    
    $response = apiRequest(
        SUZ_API_URL . '/auth/simpleSignIn/' . $omsConnection,
        'POST',
        json_encode([
            'uuid' => $uuid,
            'data' => $signature
        ]),
        ['Content-Type: application/json']
    );
    
    if ($response['code'] !== 200) {
        throw new Exception('Failed to sign in to СУЗ');
    }
    
    $body = json_decode($response['body'], true);
    $_SESSION['suz_token'] = $body['token'];
    $_SESSION['oms_connection'] = $omsConnection;
    $_SESSION['oms_id'] = $omsId;
    
    echo json_encode([
        'success' => true,
        'token' => $body['token']
    ]);
}

/**
 * Create НК card
 */
function handleCreateNKCard($data) {
    $nkToken = $_SESSION['nk_token'] ?? '';
    if (empty($nkToken)) {
        throw new Exception('Not authorized in НК');
    }
    
    $product = $data['product'] ?? [];
    $options = $data['options'] ?? [];
    
    // Build card data (simplified version - you'll need to adapt from your Flask logic)
    $cardData = [
        'is_tech_gtin' => $options['isTechGtin'] ?? true,
        'good_name' => $product['name'],
        'moderation' => $options['moderation'] ? 1 : 0
    ];
    
    // Send to НК feed
    $response = apiRequest(
        NK_API_URL . '/feed',
        'POST',
        json_encode($cardData),
        [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $nkToken
        ]
    );
    
    if ($response['code'] !== 200) {
        throw new Exception('Failed to create НК card');
    }
    
    $body = json_decode($response['body'], true);
    $feedId = $body['result']['feed_id'] ?? null;
    
    if (!$feedId) {
        throw new Exception('No feed_id in response');
    }
    
    // Poll for status
    $gtin = null;
    for ($i = 0; $i < 30; $i++) {
        sleep(2);
        
        $statusResponse = apiRequest(
            NK_API_URL . '/feed-status?feed_id=' . $feedId,
            'GET',
            null,
            ['Authorization: Bearer ' . $nkToken]
        );
        
        if ($statusResponse['code'] === 200) {
            $status = json_decode($statusResponse['body'], true);
            
            if ($status['status'] === 'Processed') {
                $gtin = $status['item']['gtin'] ?? null;
                break;
            } elseif ($status['status'] === 'Rejected') {
                throw new Exception('Card rejected: ' . json_encode($status['errors']));
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'feedId' => $feedId,
        'gtin' => $gtin
    ]);
}

/**
 * Create КМ order
 */
function handleCreateKMOrder($data) {
    $suzToken = $_SESSION['suz_token'] ?? '';
    $omsId = $data['omsId'] ?? $_SESSION['oms_id'] ?? '';
    
    if (empty($suzToken) || empty($omsId)) {
        throw new Exception('Not authorized in СУЗ');
    }
    
    $orderData = $data['orderData'] ?? '';
    $signature = $data['signature'] ?? '';
    
    $response = apiRequest(
        SUZ_API_URL . '/order?omsId=' . urlencode($omsId),
        'POST',
        $orderData,
        [
            'Content-Type: application/json',
            'clientToken: ' . $suzToken,
            'X-Signature: ' . $signature
        ]
    );
    
    if ($response['code'] !== 200) {
        throw new Exception('Failed to create order');
    }
    
    $body = json_decode($response['body'], true);
    
    echo json_encode([
        'success' => true,
        'orderId' => $body['orderId'] ?? null
    ]);
}

/**
 * Check order status
 */
function handleCheckOrderStatus($data) {
    $suzToken = $_SESSION['suz_token'] ?? '';
    $omsId = $data['omsId'] ?? $_SESSION['oms_id'] ?? '';
    $orderId = $data['orderId'] ?? '';
    
    if (empty($suzToken) || empty($omsId) || empty($orderId)) {
        throw new Exception('Missing parameters');
    }
    
    $response = apiRequest(
        SUZ_API_URL . '/order/status?omsId=' . urlencode($omsId) . '&orderId=' . urlencode($orderId),
        'GET',
        null,
        ['clientToken: ' . $suzToken]
    );
    
    if ($response['code'] !== 200) {
        throw new Exception('Failed to check status');
    }
    
    $body = json_decode($response['body'], true);
    
    echo json_encode([
        'success' => true,
        'status' => $body['orderStatus'] ?? 'Unknown'
    ]);
}

/**
 * Make API request
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