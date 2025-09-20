<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\HttpClient;
use InvalidArgumentException;

final class NationalCatalogClient
{
    private HttpClient $http;
    private string $baseUrl;
    private ?string $apiKey;

    /**
     * @param array{base_url:string,api_key?:string} $config
     */
    public function __construct(HttpClient $http, array $config)
    {
        $this->http = $http;
        $this->baseUrl = rtrim($config['base_url'] ?? '', '/');
        $this->apiKey = isset($config['api_key']) && $config['api_key'] !== ''
            ? (string) $config['api_key']
            : null;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function listProducts(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = array_merge(
            ['limit' => $limit, 'offset' => $offset],
            $this->filterQuery($filters)
        );

        $response = $this->http->request('GET', $this->baseUrl . '/v4/product-list', [
            'query' => $query,
            'headers' => $this->headers(),
        ]);

        return $this->unwrap($response['json']);
    }

    /**
     * @param array<int,int|string> $goodIds
     * @return array<string,mixed>
     */
    public function fetchProductDetails(array $goodIds): array
    {
        if ($goodIds === []) {
            throw new InvalidArgumentException('Список goodIds пуст.');
        }

        $response = $this->http->request('GET', $this->baseUrl . '/v3/feed-product', [
            'query' => ['good_ids' => implode(';', $goodIds)],
            'headers' => $this->headers(),
        ]);

        return $this->unwrap($response['json']);
    }

    public function getProductByGtin(string $gtin): array
    {
        $response = $this->http->request('GET', $this->baseUrl . '/v3/product', [
            'query' => ['gtin' => $gtin],
            'headers' => $this->headers(),
        ]);

        return $this->unwrap($response['json']);
    }

    /**
     * @param array<int,int|string> $goodIds
     */
    public function requestDocumentsForSign(array $goodIds, bool $publicationAgreement = true): array
    {
        $response = $this->http->request('POST', $this->baseUrl . '/v3/feed-product-document', [
            'json' => [
                'goodIds' => array_values($goodIds),
                'publicationAgreement' => $publicationAgreement,
            ],
            'headers' => $this->headers(),
        ]);

        return $this->unwrap($response['json']);
    }

    /**
     * @param array<int,array<string,mixed>> $pack
     */
    public function sendSignatures(array $pack): array
    {
        $response = $this->http->request('POST', $this->baseUrl . '/v3/feed-product-sign-pkcs', [
            'json' => $pack,
            'headers' => $this->headers(),
        ]);

        return $this->unwrap($response['json']);
    }

    /**
     * @param array<string,mixed> $response
     */
    private function unwrap(?array $response): array
    {
        if ($response === null) {
            return [];
        }
        if (isset($response['result']) && is_array($response['result'])) {
            $result = $response['result'];
            if (isset($result['goods']) && is_array($result['goods'])) {
                return [
                    'goods' => $result['goods'],
                    'meta' => array_diff_key($result, ['goods' => true]),
                ];
            }
            return $result;
        }
        return $response;
    }

    /**
     * @return array<string,string>
     */
    private function headers(): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];
        if ($this->apiKey !== null) {
            $headers['Api-Key'] = $this->apiKey;
        }
        return $headers;
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private function filterQuery(array $source): array
    {
        $allowed = ['search', 'dateFrom', 'dateTo', 'group', 'status', 'order'];
        $normalized = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }
            $value = $source[$key];
            if ($value === null) {
                continue;
            }
            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed === '') {
                    continue;
                }
                $normalized[$key] = $trimmed;
            } elseif (is_scalar($value)) {
                $normalized[$key] = $value;
            }
        }
        return $normalized;
    }
}
