<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $mode = (string)($_GET['mode'] ?? 'challenge');
        if ($mode === 'status') {
            $meta = orderGetTrueApiTokenMeta();
            $response = [
                'active'    => $meta !== null,
                'expiresAt' => $meta['expires_at'] ?? null,
            ];
            if (isset($meta['expires_at'])) {
                $response['expiresIn'] = $meta['expires_at'] - time();
            }
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $challenge = TrueApiClient::getChallenge();
        echo json_encode($challenge, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    if ($method === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Некорректный JSON');
        }

        $uuid = trim((string)($payload['uuid'] ?? ''));
        $signature = trim((string)($payload['signature'] ?? $payload['data'] ?? ''));
        if ($uuid === '' || $signature === '') {
            throw new InvalidArgumentException('Не переданы uuid или подпись');
        }

        $details = [];
        if (!empty($payload['details']) && is_array($payload['details'])) {
            $details['details'] = $payload['details'];
        }

        $result = TrueApiClient::exchangeToken($uuid, $signature, $details['details'] ?? []);
        echo json_encode([
            'status'    => 'ok',
            'expiresAt' => $result['expires_at'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    if ($method === 'DELETE') {
        orderForgetTrueApiToken();
        echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
        return;
    }

    http_response_code(405);
    header('Allow: GET, POST, DELETE');
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
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
