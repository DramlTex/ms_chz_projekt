<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload) || !isset($payload['signPack']) || !is_array($payload['signPack'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Bad JSON'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $result = NkApi::sendSignPack($payload['signPack']);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (RuntimeException $e) {
    http_response_code(502);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
