<?php

namespace NkCardFlow\Nk;

use NkCardFlow\Config\Config;
use NkCardFlow\Http\HttpClient;
use NkCardFlow\Http\HttpResponse;
use NkCardFlow\Logger\FileLogger;
use RuntimeException;

class NkClient
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

    public function sendCard(array $cardData, bool $useLiveGtin = false): array
    {
        $endpoint = $useLiveGtin ? '/feed/live' : '/feed';
        $response = $this->request('POST', $endpoint, [
            'body' => json_encode($cardData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $data = $response->getJson();
        if (!isset($data['result']['feed_id'])) {
            throw new RuntimeException('NK API did not return feed_id: ' . $response->getBody());
        }

        return $data['result'];
    }

    public function getLiveGtin(int $quantity = 1): string
    {
        $response = $this->request('GET', '/generate-gtins', [
            'query' => ['count' => $quantity],
        ]);

        $data = $response->getJson();
        $gtins = $data['result']['gtins'] ?? [];
        if (!$gtins) {
            throw new RuntimeException('NK API returned empty GTIN list');
        }

        return (string) $gtins[0];
    }

    public function generateTechGtin(int $categoryId, string $producerInn): ?string
    {
        $payload = json_encode([
            'categoryId' => $categoryId,
            'producerInn' => $producerInn,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = $this->request('POST', '/generate-code', [
            'body' => $payload,
        ]);

        $data = $response->getJson();
        return $data['result']['gtin'] ?? null;
    }

    public function waitForFinalStatus(string $feedId, int $interval = 10, int $retries = 30): array
    {
        for ($attempt = 0; $attempt < $retries; $attempt++) {
            $info = $this->checkFeedStatus($feedId);
            $status = strtoupper((string) ($info['status'] ?? ''));
            if (!in_array($status, ['PROCESSING', 'INPROGRESS', 'PENDING'], true)) {
                return $info;
            }

            sleep($interval);
        }

        $info = $this->checkFeedStatus($feedId);
        $info['timeout'] = true;
        return $info;
    }

    public function checkFeedStatus(string $feedId): array
    {
        $response = $this->request('GET', '/feed-status', [
            'query' => ['feed_id' => $feedId],
        ]);

        $data = $response->getJson();
        return $data['result'] ?? [];
    }

    public function formatStatusResponse(array $feedInfo): array
    {
        $errors = $feedInfo['errors'] ?? [];
        $gtin = null;
        foreach ($feedInfo['items'] ?? [] as $item) {
            if (isset($item['gtin'])) {
                $gtin = (string) $item['gtin'];
                break;
            }
        }

        return [
            'status' => $feedInfo['status'] ?? null,
            'gtin' => $gtin,
            'errors' => $errors,
            'raw' => $feedInfo,
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
            $this->logger->error('NK API {method} {path} failed: {status} {body}', [
                'method' => strtoupper($method),
                'path' => $path,
                'status' => $response->getStatusCode(),
                'body' => $response->getBody(),
            ]);
            throw new RuntimeException(sprintf('NK API error %d', $response->getStatusCode()));
        }

        return $response;
    }
}
