<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/OrderAuth.php';

final class TrueApiClient
{
    public static function getChallenge(): array
    {
        $response = trueApiRequest('GET', '/auth/key');
        $uuid = $response['uuid'] ?? null;
        $data = $response['data'] ?? null;
        if (!is_string($uuid) || $uuid === '' || !is_string($data) || $data === '') {
            throw new RuntimeException('Некорректный ответ True API при запросе challenge');
        }

        return [
            'uuid' => $uuid,
            'data' => $data,
        ];
    }

    public static function exchangeToken(string $uuid, string $signature, array $details = []): array
    {
        $payload = array_merge([
            'uuid' => $uuid,
            'data' => $signature,
        ], $details ? ['details' => $details] : []);

        $response = trueApiRequest('POST', '/auth/simpleSignIn', [], $payload);
        if (empty($response['token']) || !is_string($response['token'])) {
            throw new RuntimeException('True API не вернул token');
        }

        $expiresAt = isset($response['expiresIn']) && is_numeric($response['expiresIn'])
            ? time() + (int)$response['expiresIn']
            : nkGuessTokenExpiration($response['token']);

        orderStoreTrueApiToken($response['token'], $expiresAt);

        return [
            'token'      => $response['token'],
            'expires_at' => $expiresAt,
        ];
    }

    public static function exchangeTokenForConnection(string $omsConnection, string $uuid, string $signature, array $details = []): array
    {
        $payload = array_merge([
            'uuid' => $uuid,
            'data' => $signature,
        ], $details ? ['details' => $details] : []);

        $response = trueApiRequest('POST', '/auth/simpleSignIn/' . rawurlencode($omsConnection), [], $payload);

        $token = $response['client_token'] ?? ($response['clientToken'] ?? null);
        if (!is_string($token) || $token === '') {
            $fallbackToken = $response['token'] ?? null;
            if (is_string($fallbackToken) && $fallbackToken !== '') {
                $token = $fallbackToken;
            }
        }

        if (!is_string($token) || $token === '') {
            $responseDump = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($responseDump === false) {
                $responseDump = var_export($response, true);
            }
            ordersLog('suz-auth token exchange response without clientToken: ' . $responseDump);
            throw new RuntimeException('True API не вернул clientToken СУЗ');
        }

        $expiresAt = isset($response['expires_in']) && is_numeric($response['expires_in'])
            ? time() + (int)$response['expires_in']
            : null;

        return [
            'token'      => $token,
            'expires_at' => $expiresAt,
        ];
    }

    public static function request(string $method, string $uri, ?array $body = null, ?string $token = null): array
    {
        $headers = [];
        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        return trueApiRequest($method, $uri, $headers, $body);
    }

    public static function createDocument(string $token, string $productGroup, array $payload): array
    {
        $uri = '/lk/documents/create?pg=' . rawurlencode($productGroup);
        return self::request('POST', $uri, $payload, $token);
    }

    public static function listDocuments(string $token, string $productGroup, array $params = []): array
    {
        $uri = '/doc/list';
        if ($params) {
            $uri .= '?' . http_build_query(array_merge(['pg' => $productGroup], $params), '', '&', PHP_QUERY_RFC3986);
        } else {
            $uri .= '?pg=' . rawurlencode($productGroup);
        }

        return self::request('GET', $uri, null, $token);
    }

    public static function documentInfo(string $token, string $productGroup, string $documentId): array
    {
        $uri = '/doc/' . rawurlencode($documentId) . '/info?pg=' . rawurlencode($productGroup);
        return self::request('GET', $uri, null, $token);
    }
}
