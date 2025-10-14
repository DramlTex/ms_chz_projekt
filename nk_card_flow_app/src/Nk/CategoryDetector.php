<?php

namespace NkCardFlow\Nk;

use NkCardFlow\Config\Config;
use NkCardFlow\Http\HttpClient;
use NkCardFlow\Http\HttpResponse;
use NkCardFlow\Logger\FileLogger;
use RuntimeException;

class CategoryDetector
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;

    public function __construct(private HttpClient $httpClient, private FileLogger $logger, Config $config)
    {
        $this->baseUrl = rtrim($config->get('nk.base_url'), '/');
        $this->apiKey = (string) $config->get('nk.api_key');
        $this->timeout = (int) $config->get('nk.timeout', 30);
    }

    public function detectByTnved(string $tnved): array
    {
        $tnved = preg_replace('/\D+/', '', $tnved) ?? $tnved;
        if ($tnved === '') {
            throw new RuntimeException('TNVED code is empty');
        }

        $checkResponse = $this->request('POST', '/check/feacn', [
            'body' => json_encode(['feacn' => $tnved], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $payload = $checkResponse->getJson();
        if (($payload['result']['markable'] ?? false) !== true) {
            throw new RuntimeException('Provided TNVED is not markable in NK');
        }

        $categoryResponse = $this->request('GET', '/categories/by-feacn', [
            'query' => ['feacn' => $tnved],
        ]);

        $data = $categoryResponse->getJson();
        if (!isset($data['result']['categories']) || !is_array($data['result']['categories']) || $data['result']['categories'] === []) {
            throw new RuntimeException('NK API did not return categories for TNVED ' . $tnved);
        }

        $first = $data['result']['categories'][0];
        return [
            'productGroupCode' => $first['productGroupCode'] ?? null,
            'categoryId' => $first['categoryId'] ?? null,
            'raw' => $data['result']['categories'],
        ];
    }

    private function request(string $method, string $path, array $options = []): HttpResponse
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'apiKey' => $this->apiKey,
        ];

        $options['headers'] = isset($options['headers'])
            ? array_merge($headers, $options['headers'])
            : $headers;
        $options['timeout'] = $options['timeout'] ?? $this->timeout;

        $url = $this->baseUrl . $path;
        $response = $this->httpClient->request($method, $url, $options);

        if ($response->getStatusCode() >= 400) {
            $this->logger->error('NK category detection failed: {status} {body}', [
                'status' => $response->getStatusCode(),
                'body' => $response->getBody(),
            ]);
            throw new RuntimeException(sprintf('NK API error %d while requesting %s', $response->getStatusCode(), $path));
        }

        return $response;
    }
}
