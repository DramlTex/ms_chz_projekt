<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Method not allowed');
    }

    $gtin    = trim($_POST['gtin']    ?? '');
    $name    = trim($_POST['name']    ?? '');
    $tnved   = trim($_POST['tnved']   ?? '');
    $article = trim($_POST['article'] ?? '');

    if ($gtin === '' || $name === '') {
        throw new RuntimeException('Недостаточно данных');
    }

    $paddedGtin = str_pad($gtin, 14, '0', STR_PAD_LEFT);

    $body = [
        'name'         => $name,
        'article'      => $article,
        // Флаг маркировки для одежды
        'trackingType' => 'LP_CLOTHES',
        'tnved'        => $tnved,
        // Добавляем штрихкод в формате GTIN (до 14 символов)
        'barcodes'     => [['gtin' => $paddedGtin]],
    ];

    msRequest('POST', '/entity/product', $body);

    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
