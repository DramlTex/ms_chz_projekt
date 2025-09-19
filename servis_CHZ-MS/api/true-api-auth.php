<?php

declare(strict_types=1);

require_once __DIR__ . '/../TrueApi.php';

header('Content-Type: application/json; charset=utf-8');

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        throw new InvalidArgumentException('Пустое тело запроса.');
    }

    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Некорректный формат JSON.');
    }

    return $decoded;
}

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'GET') {
        $inn = isset($_GET['inn']) ? trim((string) $_GET['inn']) : null;
        $challenge = TrueApi::requestChallenge($inn);
        if (empty($challenge['data'])) {
            throw new RuntimeException('True API не вернуло данные для подписи.');
        }

        echo json_encode([
            'uuid' => $challenge['uuid'] ?? null,
            'data' => $challenge['data'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    if ($method === 'POST') {
        $payload = readJsonBody();
        $uuid = isset($payload['uuid']) ? trim((string) $payload['uuid']) : null;
        $signature = isset($payload['signature']) ? trim((string) $payload['signature']) : '';
        if ($signature === '' && isset($payload['data'])) {
            $signature = trim((string) $payload['data']);
        }
        if ($signature === '') {
            throw new InvalidArgumentException('Подпись не передана.');
        }

        $inn = isset($payload['inn']) ? trim((string) $payload['inn']) : '';
        $options = [];
        if ($inn !== '') {
            $options['inn'] = $inn;
        }
        if (array_key_exists('unitedToken', $payload)) {
            $options['unitedToken'] = (bool) $payload['unitedToken'];
        }

        $result = TrueApi::exchangeSignature([
            'uuid' => $uuid,
            'signature' => $signature,
        ], $options);

        if (empty($result['token'])) {
            throw new RuntimeException('True API не вернуло bearer-токен.');
        }

        echo json_encode([
            'token' => $result['token'],
            'expiresAt' => $result['expiresAt'] ?? null,
            'organization' => $result['organization'] ?? null,
            'raw' => $result['raw'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException | JsonException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(502);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
