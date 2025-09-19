<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Method not allowed');
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload) || !isset($payload['items']) || !is_array($payload['items'])) {
        throw new RuntimeException('Bad payload');
    }

    $results = [];
    foreach ($payload['items'] as $item) {
        $gtin    = trim($item['gtin']    ?? '');
        $name    = trim($item['name']    ?? '');
        $tnved   = trim($item['tnved']   ?? '');
        $article = trim($item['article'] ?? '');

        if ($gtin === '' || $name === '') {
            $results[] = ['gtin' => $gtin, 'error' => 'Недостаточно данных'];
            continue;
        }

        $paddedGtin = str_pad($gtin, 14, '0', STR_PAD_LEFT);
        $body = [
            'name'         => $name,
            'article'      => $article,
            'trackingType' => 'LP_CLOTHES',
            'tnved'        => $tnved,
            'barcodes'     => [['gtin' => $paddedGtin]],
        ];

        try {
            msRequest('POST', '/entity/product', $body);
            $results[] = ['gtin' => $gtin, 'status' => 'ok'];
        } catch (Throwable $e) {
            $results[] = ['gtin' => $gtin, 'error' => $e->getMessage()];
        }
    }

    echo json_encode(['results' => $results]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
