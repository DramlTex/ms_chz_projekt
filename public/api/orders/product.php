<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $rawGtinParam = $_GET['gtin'] ?? null;
    $rawGtinType = gettype($rawGtinParam);
    $gtin = trim((string)($rawGtinParam ?? ''));
    if ($gtin === '') {
        throw new InvalidArgumentException('Не указан GTIN');
    }

    $normalized = NkApi::normalizeGtin($gtin);
    $lookupGtin = $normalized !== '' ? $normalized : $gtin;

    $source = isset($_GET['source']) ? (string)$_GET['source'] : 'nk';
    $hasBearer = nkGetAuthToken() !== null;
    $hasApiKey = NK_API_KEY !== '';

    ordersLog(sprintf(
        'orders.product lookup requested: raw="%s" rawType=%s normalized="%s" source="%s" bearer=%s apikey=%s',
        $gtin,
        $rawGtinType,
        $lookupGtin,
        $source !== '' ? $source : '—',
        $hasBearer ? 'yes' : 'no',
        $hasApiKey ? 'yes' : 'no'
    ));

    $card = NkApi::cardByGtin($lookupGtin);
    if (!$card) {
        ordersLog(sprintf('orders.product lookup empty result: normalized="%s"', $lookupGtin));
        throw new RuntimeException('Карточка не найдена в НК');
    }

    $goodId = $card['good_id'] ?? $card['goodId'] ?? null;
    $goodName = $card['good_name'] ?? '';
    if ($goodName !== '') {
        $goodName = function_exists('mb_substr') ? mb_substr($goodName, 0, 120) : substr($goodName, 0, 120);
    }

    ordersLog(sprintf(
        'orders.product lookup success: normalized="%s" goodId=%s goodName="%s"',
        $lookupGtin,
        $goodId !== null ? (string)$goodId : '—',
        $goodName !== '' ? $goodName : '—'
    ));

    echo json_encode([
        'status' => 'ok',
        'card' => $card,
        'gtin' => $lookupGtin,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    ordersLog(sprintf('orders.product validation error: %s', $e->getMessage()));
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    ordersLog(sprintf('orders.product runtime error: %s', $e->getMessage()));
    http_response_code(404);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    ordersLog(sprintf('orders.product unexpected error: %s (%s:%d)', $e->getMessage(), $e->getFile(), $e->getLine()));
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
