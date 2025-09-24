<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $mode = (string)($_GET['mode'] ?? 'challenge');
        if ($mode === 'status') {
            $meta = nkGetAuthTokenMeta();
            $expiresAt = $meta['expires_at'] ?? null;
            $response = [
                'active' => $meta !== null,
                'expiresAt' => $expiresAt,
            ];
            if ($expiresAt !== null) {
                $response['expiresIn'] = max(0, $expiresAt - time());
            }
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        if ($mode !== 'challenge') {
            throw new InvalidArgumentException('Неизвестный режим запроса');
        }

        $challenge = trueApiRequest('GET', '/auth/key');
        if (!isset($challenge['uuid'], $challenge['data'])) {
            throw new RuntimeException('Некорректный ответ True API');
        }

        nkLog('NK auth challenge issued');

        echo json_encode([
            'uuid' => $challenge['uuid'],
            'data' => $challenge['data'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    if ($method === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Некорректный JSON');
        }

        $uuid = trim((string)($payload['uuid'] ?? ''));
        $signature = trim((string)($payload['signature'] ?? $payload['data'] ?? ''));
        if ($uuid === '' || $signature === '') {
            throw new InvalidArgumentException('Не указаны uuid или подпись');
        }

        $request = [
            'uuid' => $uuid,
            'data' => $signature,
        ];

        $inn = isset($payload['inn']) ? preg_replace('/\D+/', '', (string)$payload['inn']) : '';
        if ($inn !== '') {
            $request['inn'] = $inn;
        }

        if (!empty($payload['details']) && is_array($payload['details'])) {
            $request['details'] = $payload['details'];
        }

        $response = trueApiRequest('POST', '/auth/simpleSignIn', [], $request);
        $token = $response['token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('True API не вернул токен авторизации');
        }

        $expiresAt = nkGuessTokenExpiration($token);
        nkStoreAuthToken($token, $expiresAt);
        nkLog('NK auth token stored (expires: ' . ($expiresAt ? date('c', $expiresAt) : 'unknown') . ')');

        echo json_encode([
            'status'    => 'ok',
            'expiresAt' => $expiresAt,
            'expiresIn' => $expiresAt ? max(0, $expiresAt - time()) : null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    if ($method === 'DELETE') {
        nkForgetAuthToken();
        nkLog('NK auth token cleared');
        echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
        return;
    }

    http_response_code(405);
    header('Allow: GET, POST, DELETE');
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
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
