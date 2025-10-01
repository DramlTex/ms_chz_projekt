<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $gtin = $_GET['gtin'] ?? '';
    if (!$gtin) {
        throw new Exception('GTIN не указан');
    }
    
    // Получаем токен из сессии
    $token = getToken('nk_token');
    if (!$token) {
        http_response_code(401);
        throw new Exception('Токен НК отсутствует');
    }
    
    // ИСПРАВЛЕНО: используем /nk/feed-product
    $response = apiRequest(
        TRUE_API_URL . '/nk/feed-product?gtin=' . urlencode($gtin),
        'GET',
        ['Authorization: Bearer ' . $token]
    );
    
    // Структура ответа согласно документации
    $card = $response['result'][0] ?? null;
    if (!$card) {
        http_response_code(404);
        throw new Exception('Карточка не найдена');
    }
    
    // Извлекаем данные по новой структуре
    $output = [
        'goodId' => $card['good_id'] ?? '',
        'name' => $card['good_name'] ?? '',
        'gtin' => $card['identified_by'][0]['value'] ?? $gtin,
        'productGroup' => null,
        'tnved' => null,
        'templateId' => null,
    ];
    
    // Ищем нужные атрибуты
    foreach ($card['good_attrs'] ?? [] as $attr) {
        $name = $attr['attr_name'] ?? '';
        $value = $attr['attr_value'] ?? '';
        
        if (stripos($name, 'тнвэд') !== false || stripos($name, 'tnved') !== false) {
            $output['tnved'] = $value;
        }
        
        if (stripos($name, 'товарная группа') !== false || stripos($name, 'product group') !== false) {
            $output['productGroup'] = $value;
        }
        
        if (stripos($name, 'шаблон') !== false || stripos($name, 'template') !== false) {
            $output['templateId'] = (int)preg_replace('/\D/', '', $value);
        }
    }
    
    echo json_encode($output);
    
} catch (Exception $e) {
    // Если 401/403 - токен протух
    if (strpos($e->getMessage(), 'HTTP 401') !== false || strpos($e->getMessage(), 'HTTP 403') !== false) {
        clearToken('nk_token');
        http_response_code(401);
        echo json_encode(['error' => 'Токен НК недействителен. Получите новый.']);
        exit;
    }
    
    echo json_encode(['error' => $e->getMessage()]);
}