<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $meta = orderGetTrueApiTokenMeta();
    if ($meta === null) {
        throw new RuntimeException('Токен True API не получен. Выполните авторизацию.');
    }

    $payload = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Некорректный JSON');
    }

    $type = trim((string)($payload['type'] ?? ''));
    $productGroup = trim((string)($payload['productGroup'] ?? 'lp'));
    $documentFormat = trim((string)($payload['documentFormat'] ?? 'MANUAL'));
    $productDocument = trim((string)($payload['productDocument'] ?? ''));
    $signature = trim((string)($payload['signature'] ?? ''));
    $secondProduct = isset($payload['secondProductDocument']) ? trim((string)$payload['secondProductDocument']) : '';
    $secondSignature = isset($payload['secondSignature']) ? trim((string)$payload['secondSignature']) : '';

    if ($type === '' || $productDocument === '' || $signature === '') {
        throw new InvalidArgumentException('Не заполнены обязательные поля документа');
    }

    $body = [
        'type'            => $type,
        'document_format' => $documentFormat,
        'product_document'=> $productDocument,
        'signature'       => $signature,
    ];
    if ($secondProduct !== '') {
        $body['second_product_document'] = $secondProduct;
    }
    if ($secondSignature !== '') {
        $body['second_signature'] = $secondSignature;
    }

    $response = TrueApiClient::createDocument($meta['token'], $productGroup, $body);
    echo json_encode(['status' => 'ok', 'response' => $response], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (JsonException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Некорректный JSON: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    http_response_code(502);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
