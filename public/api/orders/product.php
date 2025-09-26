<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $gtin = trim((string)($_GET['gtin'] ?? ''));
    if ($gtin === '') {
        throw new InvalidArgumentException('Не указан GTIN');
    }

    $normalized = NkApi::normalizeGtin($gtin);
    $lookupGtin = $normalized !== '' ? $normalized : $gtin;

    $card = NkApi::cardByGtin($lookupGtin);
    if (!$card) {
        throw new RuntimeException('Карточка не найдена в НК');
    }

    echo json_encode([
        'status' => 'ok',
        'card' => $card,
        'gtin' => $lookupGtin,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    http_response_code(404);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
