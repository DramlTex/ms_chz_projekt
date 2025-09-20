<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function normalizeItems(array $items): array
{
    $normalized = [];
    $total = 0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $id = (string) ($item['id'] ?? '');
        $gtin = trim((string) ($item['gtin'] ?? ''));
        $name = trim((string) ($item['name'] ?? ''));
        $quantity = (int) ($item['quantity'] ?? 0);
        if ($quantity < 1) {
            $quantity = 1;
        }
        $total += $quantity;
        $normalized[] = [
            'id' => $id,
            'gtin' => $gtin,
            'name' => $name,
            'quantity' => $quantity,
        ];
    }

    if ($normalized === []) {
        throw new InvalidArgumentException('Не найдены корректные позиции для заказа.');
    }

    return [$normalized, $total];
}

try {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        throw new InvalidArgumentException('Пустое тело запроса.');
    }

    $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Некорректный формат запроса.');
    }

    $items = $payload['items'] ?? null;
    if (!is_array($items) || $items === []) {
        throw new InvalidArgumentException('Список позиций пуст.');
    }

    [$normalizedItems, $totalCodes] = normalizeItems($items);

    $signaturePack = [];
    if (!empty($payload['signaturePack']) && is_array($payload['signaturePack'])) {
        foreach ($payload['signaturePack'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $signaturePack[] = [
                'goodId' => (string) ($entry['goodId'] ?? ''),
                'base64Xml' => trim((string) ($entry['base64Xml'] ?? '')),
                'signature' => trim((string) ($entry['signature'] ?? '')),
            ];
        }
    }

    $signatureResponse = null;
    if (array_key_exists('signatureResponse', $payload)) {
        $rawSignatureResponse = $payload['signatureResponse'];
        if (is_array($rawSignatureResponse) || is_scalar($rawSignatureResponse) || $rawSignatureResponse === null) {
            $signatureResponse = $rawSignatureResponse;
        } else {
            $encoded = json_encode($rawSignatureResponse);
            if ($encoded !== false) {
                $decoded = json_decode($encoded, true);
                if (is_array($decoded) || is_scalar($decoded) || $decoded === null) {
                    $signatureResponse = $decoded;
                }
            }
        }
    }

    $certificate = null;
    if (!empty($payload['certificate']) && is_array($payload['certificate'])) {
        $certificate = [
            'thumbprint' => $payload['certificate']['thumbprint'] ?? null,
            'subject' => $payload['certificate']['subject'] ?? null,
            'validTo' => $payload['certificate']['validTo'] ?? null,
        ];
    }

    $filters = [];
    if (!empty($payload['filters']) && is_array($payload['filters'])) {
        $filters = [
            'search' => $payload['filters']['search'] ?? null,
            'group' => $payload['filters']['group'] ?? null,
            'dateFrom' => $payload['filters']['dateFrom'] ?? null,
            'dateTo' => $payload['filters']['dateTo'] ?? null,
        ];
    }

    $orderId = str_replace('.', '', uniqid('order_', true));

    echo json_encode([
        'status' => 'ok',
        'order' => [
            'id' => $orderId,
            'createdAt' => date('c'),
            'positions' => count($normalizedItems),
            'totalCodes' => $totalCodes,
        ],
        'payload' => [
            'items' => $normalizedItems,
            'certificate' => $certificate,
            'filters' => $filters,
            'signaturePack' => $signaturePack,
            'signatureResponse' => $signatureResponse,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException | JsonException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
