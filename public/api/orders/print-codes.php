<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

header('Content-Type: application/pdf');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $payload = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || empty($payload['codes']) || !is_array($payload['codes'])) {
        throw new InvalidArgumentException('Передайте массив codes для печати');
    }

    $filename = 'codes-' . date('Ymd-His') . '.pdf';
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $pdf = PdfGenerator::codesToPdf($payload['codes']);
    echo $pdf;
} catch (JsonException $e) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Некорректный JSON: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
