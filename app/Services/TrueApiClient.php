<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\HttpClient;

final class TrueApiClient
{
    private HttpClient $http;
    private string $baseUrl;
    private string $docBaseUrl;

    /**
     * @param array{base_url:string,doc_base_url?:string} $config
     */
    public function __construct(HttpClient $http, array $config)
    {
        $this->http = $http;
        $this->baseUrl = rtrim($config['base_url'] ?? '', '/');
        $this->docBaseUrl = rtrim($config['doc_base_url'] ?? $this->baseUrl, '/');
    }

    public function requestAuthKey(?string $inn = null): array
    {
        $response = $this->http->request('GET', $this->baseUrl . '/auth/key', [
            'query' => array_filter([
                'inn' => $inn !== null ? trim($inn) : null,
            ]),
            'headers' => [
                'Accept' => 'application/json',
                'Cache-Control' => 'no-cache',
            ],
        ]);

        return $this->extractChallenge($response['json'] ?? []);
    }

    public function signIn(string $uuid, string $signature, ?string $inn = null, bool $unitedToken = false): array
    {
        $payload = [
            'uuid' => $uuid,
            'data' => $signature,
        ];
        if ($inn !== null && trim($inn) !== '') {
            $payload['inn'] = trim($inn);
        }
        if ($unitedToken) {
            $payload['unitedToken'] = true;
        }

        $response = $this->http->request('POST', $this->baseUrl . '/auth/simpleSignIn', [
            'json' => $payload,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $data = $this->unwrap($response['json']);

        return [
            'token' => $this->extractToken($data),
            'expiresAt' => $this->extractExpiration($data),
            'organization' => $this->extractOrganization($data),
            'raw' => $data,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createDocument(string $token, string $productGroup, array $payload): array
    {
        $response = $this->http->request('POST', $this->baseUrl . '/lk/documents/create', [
            'query' => ['pg' => $productGroup],
            'json' => $payload,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => '*/*',
            ],
        ]);

        return $this->unwrap($response['json']);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function listDocuments(string $token, string $productGroup, array $filters = []): array
    {
        $query = array_merge(['pg' => $productGroup], $filters);
        $response = $this->http->request('GET', $this->docBaseUrl . '/doc/list', [
            'query' => $query,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
        ]);

        return $this->unwrap($response['json']);
    }

    public function getDocumentInfo(string $token, string $documentId): array
    {
        $response = $this->http->request('GET', $this->docBaseUrl . '/doc/' . rawurlencode($documentId) . '/info', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
        ]);

        return $this->unwrap($response['json']);
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
    private function extractToken(array $response): ?string
    {
        foreach (['token', 'uuidToken', 'unitedToken'] as $key) {
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

    /**
     * @param array<string,mixed> $response
     * @return array<string,mixed>|null
     */
    private function extractOrganization(array $response): ?array
    {
        $candidates = [];
        foreach (['organization', 'orgInfo', 'participant', 'holder'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                $candidates[] = $response[$key];
            }
        }
        if (!$candidates && isset($response['organizationName'])) {
            $candidates[] = [
                'name' => $response['organizationName'],
                'inn' => $response['inn'] ?? null,
            ];
        }

        $source = $candidates[0] ?? [];
        if (!is_array($source)) {
            return null;
        }

        $result = [];
        foreach ([
            'name' => ['name', 'fullName', 'organizationName', 'participantName'],
            'inn' => ['inn', 'orgInn', 'participantInn'],
            'kpp' => ['kpp', 'participantKpp'],
            'ogrn' => ['ogrn', 'orgOgrn'],
        ] as $target => $keys) {
            foreach ($keys as $key) {
                if (isset($source[$key]) && is_string($source[$key]) && trim($source[$key]) !== '') {
                    $result[$target] = trim($source[$key]);
                    break;
                }
            }
        }

        return $result ?: null;
    }
}
