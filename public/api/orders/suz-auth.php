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
            $meta = orderGetSuzTokenMeta();
            $response = [
                'active'       => $meta !== null,
                'expiresAt'    => $meta['expires_at'] ?? null,
                'omsId'        => $meta['oms_id'] ?? null,
                'omsConnection'=> $meta['oms_connect'] ?? null,
                'context'      => orderGetSuzContext(),
            ];
            if (!empty($meta['expires_at'])) {
                $response['expiresIn'] = $meta['expires_at'] - time();
            }
            ordersLog('suz-auth status check: ' . ($response['active'] ? 'active' : 'inactive'));
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        ordersLog('suz-auth challenge requested');
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
        $omsConnection = trim((string)($payload['omsConnection'] ?? ''));
        $omsId = trim((string)($payload['omsId'] ?? ''));
        if ($uuid === '' || $signature === '' || $omsConnection === '' || $omsId === '') {
            throw new InvalidArgumentException('Не переданы uuid, подпись, omsConnection или omsId');
        }

        ordersLog(sprintf('suz-auth token request: omsConnection=%s, omsId=%s', $omsConnection, $omsId));
        $result = TrueApiClient::exchangeTokenForConnection($omsConnection, $uuid, $signature);
        orderStoreSuzToken($result['token'], $omsId, $result['expires_at'], $omsConnection);
        ordersLog('suz-auth token received' . (!empty($result['expires_at']) ? ' до ' . date('c', (int)$result['expires_at']) : ''));

        echo json_encode([
            'status'    => 'ok',
            'expiresAt' => $result['expires_at'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    if ($method === 'DELETE') {
        orderForgetSuzToken();
        ordersLog('suz-auth token cleared');
        echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
        return;
    }

    http_response_code(405);
    header('Allow: GET, POST, DELETE');
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
} catch (JsonException $e) {
    http_response_code(400);
    ordersLog('suz-auth JSON error: ' . $e->getMessage());
    echo json_encode(['error' => 'Некорректный JSON: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    ordersLog('suz-auth invalid request: ' . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    http_response_code(502);
    ordersLog('suz-auth runtime error: ' . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    ordersLog('suz-auth unexpected error: ' . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
