<?php

namespace NkCardFlow\MoySklad;

use NkCardFlow\Config\Config;
use NkCardFlow\Http\HttpClient;
use NkCardFlow\Logger\FileLogger;
use RuntimeException;

class MoySkladClient
{
    private string $baseUrl;
    private ?string $token;
    private ?string $username;
    private ?string $password;
    private int $timeout;

    public function __construct(private HttpClient $httpClient, private FileLogger $logger, Config $config)
    {
        $this->baseUrl = rtrim($config->get('moysklad.base_url'), '/');
        $this->token = $config->get('moysklad.token');
        $this->username = $config->get('moysklad.username');
        $this->password = $config->get('moysklad.password');
        $this->timeout = (int) $config->get('moysklad.timeout', 30);
    }

    public function getProduct(string $productId): array
    {
        $url = sprintf('%s/entity/product/%s', $this->baseUrl, $productId);
        $response = $this->httpClient->request('GET', $url, [
            'headers' => $this->buildHeaders(),
            'timeout' => $this->timeout,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(sprintf('Failed to fetch product %s: HTTP %d', $productId, $response->getStatusCode()));
        }

        return $response->getJson();
    }

    public function getVariant(string $variantId): array
    {
        $url = sprintf('%s/entity/variant/%s', $this->baseUrl, $variantId);
        $response = $this->httpClient->request('GET', $url, [
            'headers' => $this->buildHeaders(),
            'timeout' => $this->timeout,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(sprintf('Failed to fetch variant %s: HTTP %d', $variantId, $response->getStatusCode()));
        }

        return $response->getJson();
    }

    /**
     * @param array<int, array<string, mixed>> $barcodes
     */
    public function updateProductBarcodes(string $entityId, array $barcodes, bool $isVariant = false): array
    {
        $entity = $isVariant ? 'variant' : 'product';
        $url = sprintf('%s/entity/%s/%s', $this->baseUrl, $entity, $entityId);

        $payload = json_encode(['barcodes' => $barcodes], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw new RuntimeException('Unable to encode barcode payload');
        }

        $response = $this->httpClient->request('PUT', $url, [
            'headers' => $this->buildHeaders(['Content-Type' => 'application/json']),
            'timeout' => $this->timeout,
            'body' => $payload,
        ]);

        if ($response->getStatusCode() >= 400) {
            $this->logger->error('Failed to update barcodes for {entity} {id}: {body}', [
                'entity' => $entity,
                'id' => $entityId,
                'body' => $response->getBody(),
            ]);
            throw new RuntimeException(sprintf('Failed to update barcodes: HTTP %d', $response->getStatusCode()));
        }

        return $response->getJson();
    }

    public function extractItemDataWithInheritance(array $item, ?array $parent = null): array
    {
        $attributes = $this->normalizeAttributes($parent ?? []);
        $attributes = array_replace($attributes, $this->normalizeAttributes($item));

        $data = [
            'id' => $item['id'] ?? $parent['id'] ?? null,
            'product_id' => $parent['id'] ?? $item['id'] ?? null,
            'name' => $item['name'] ?? $parent['name'] ?? '',
            'article' => $item['article'] ?? $parent['article'] ?? ($attributes['article'] ?? null),
            'brand' => $attributes['brand'] ?? null,
            'tnved' => $attributes['tnved'] ?? null,
            'country' => $attributes['country'] ?? null,
            'color' => $attributes['color'] ?? null,
            'size' => $attributes['size'] ?? null,
            'documents' => $attributes['documents'] ?? [],
            'target_gender' => $attributes['target_gender'] ?? null,
            'raw_attributes' => $attributes,
        ];

        return $data;
    }

    private function normalizeAttributes(array $item): array
    {
        $result = [];
        if (!isset($item['attributes']) || !is_array($item['attributes'])) {
            return $result;
        }

        foreach ($item['attributes'] as $attribute) {
            if (!isset($attribute['name'], $attribute['value'])) {
                continue;
            }

            $key = $this->slugify((string) $attribute['name']);
            $result[$key] = $attribute['value'];
        }

        return $result;
    }

    private function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9_]+/u', '_', $slug) ?? $slug;
        $slug = preg_replace('/_+/', '_', $slug) ?? $slug;

        return trim($slug, '_');
    }

    /**
     * @param array<string,string> $additional
     * @return array<string,string>
     */
    private function buildHeaders(array $additional = []): array
    {
        $headers = array_merge([
            'Accept' => 'application/json',
        ], $additional);

        if ($this->token) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        } elseif ($this->username && $this->password) {
            $headers['Authorization'] = 'Basic ' . base64_encode($this->username . ':' . $this->password);
        }

        return $headers;
    }
}
