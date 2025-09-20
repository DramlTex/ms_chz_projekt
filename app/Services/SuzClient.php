<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\HttpClient;
use InvalidArgumentException;

final class SuzClient
{
    private HttpClient $http;
    private string $baseUrl;
    private string $authBaseUrl;

    /**
     * @param array{base_url:string,auth_base_url?:string,oms_id?:string,oms_connection?:string} $config
     */
    public function __construct(HttpClient $http, array $config)
    {
        $this->http = $http;
        $this->baseUrl = rtrim($config['base_url'] ?? '', '/');
        $this->authBaseUrl = rtrim($config['auth_base_url'] ?? $this->baseUrl, '/');
    }

    public function requestAuthKey(?string $omsId = null): array
    {
        $response = $this->http->request('GET', $this->authBaseUrl . '/auth/key', [
            'query' => array_filter([
                'omsId' => $omsId !== null ? trim($omsId) : null,
            ]),
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        return $this->extractChallenge($response['json'] ?? []);
    }

    public function signIn(string $omsConnection, string $signature, ?string $uuid = null, ?string $inn = null): array
    {
        if (trim($omsConnection) === '') {
            throw new InvalidArgumentException('omsConnection обязателен.');
        }

        $payload = [
            'data' => $signature,
        ];
        if ($uuid !== null && trim($uuid) !== '') {
            $payload['uuid'] = trim($uuid);
        }
        if ($inn !== null && trim($inn) !== '') {
            $payload['inn'] = trim($inn);
        }

        $response = $this->http->request('POST', $this->authBaseUrl . '/auth/simpleSignIn/' . rawurlencode($omsConnection), [
            'json' => $payload,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $data = $this->unwrap($response['json']);

        return [
            'clientToken' => $this->extractClientToken($data),
            'expiresAt' => $this->extractExpiration($data),
            'raw' => $data,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createOrder(string $omsId, string $clientToken, string $signature, array $payload): array
    {
        $response = $this->http->request('POST', $this->baseUrl . '/order', [
            'query' => ['omsId' => $omsId],
            'json' => $payload,
            'headers' => $this->authHeaders($clientToken, $signature),
        ]);

        return $this->unwrap($response['json']);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function listOrders(string $omsId, string $clientToken, array $filters = []): array
    {
        $response = $this->http->request('GET', $this->baseUrl . '/order/list', [
            'query' => array_merge(['omsId' => $omsId], $filters),
            'headers' => [
                'clientToken' => $clientToken,
                'Accept' => 'application/json',
            ],
        ]);

        return $this->unwrap($response['json']);
    }

    public function closeOrder(string $omsId, string $clientToken, string $signature, string $orderId): array
    {
        $response = $this->http->request('POST', $this->baseUrl . '/order/close', [
            'query' => ['omsId' => $omsId],
            'json' => ['orderId' => $orderId],
            'headers' => $this->authHeaders($clientToken, $signature),
        ]);

        return $this->unwrap($response['json']);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function createDropout(string $omsId, string $clientToken, string $signature, array $payload): array
    {
        $response = $this->http->request('POST', $this->baseUrl . '/dropout', [
            'query' => ['omsId' => $omsId],
            'json' => $payload,
            'headers' => $this->authHeaders($clientToken, $signature),
        ]);

        return $this->unwrap($response['json']);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function submitUtilisation(string $omsId, string $clientToken, string $signature, array $payload): array
    {
        $response = $this->http->request('POST', $this->baseUrl . '/utilisation', [
            'query' => ['omsId' => $omsId],
            'json' => $payload,
            'headers' => $this->authHeaders($clientToken, $signature),
        ]);

        return $this->unwrap($response['json']);
    }

    /**
     * @return array<string,string>
     */
    private function authHeaders(string $clientToken, string $signature): array
    {
        return [
            'clientToken' => $clientToken,
            'X-Signature' => $signature,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * @param array<string,mixed>|null $payload
     */
    private function unwrap(?array $payload): array
    {
        if ($payload === null) {
            return [];
        }
        if (isset($payload['result']) && is_array($payload['result'])) {
            return $payload['result'];
        }
        return $payload;
    }

    /**
     * @param array<string,mixed> $response
     */
    private function extractChallenge(array $response): array
    {
        $data = $this->unwrap($response);
        return [
            'uuid' => isset($data['uuid']) ? (string) $data['uuid'] : null,
            'data' => isset($data['data']) ? (string) $data['data'] : null,
        ];
    }

    /**
     * @param array<string,mixed> $response
     */
    private function extractClientToken(array $response): ?string
    {
        foreach (['clientToken', 'token'] as $key) {
            if (isset($response[$key]) && is_string($response[$key]) && trim($response[$key]) !== '') {
                return trim($response[$key]);
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $response
     */
    private function extractExpiration(array $response): ?string
    {
        foreach (['expireDateTime', 'expireDate', 'expiresAt', 'expireAt', 'tokenExpireAt', 'tokenExpireDate'] as $key) {
            if (!isset($response[$key])) {
                continue;
            }
            $value = $response[$key];
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }
        return null;
    }
}
