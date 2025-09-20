<?php
declare(strict_types=1);

namespace App\Support;

use App\Exceptions\HttpException;
use JsonException;
use RuntimeException;

final class HttpClient
{
    private int $timeout;
    private bool $verifyPeer;

    public function __construct(int $timeout = 30, bool $verifyPeer = true)
    {
        $this->timeout = $timeout;
        $this->verifyPeer = $verifyPeer;
    }

    /**
     * @param array<string,mixed> $options
     *
     * @return array{status:int,body:string,headers:array<string,array<int,string>>,json:array<string,mixed>|null}
     */
    public function request(string $method, string $url, array $options = []): array
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Не удалось инициализировать cURL.');
        }

        $query = $options['query'] ?? [];
        if ($query) {
            $queryString = http_build_query($this->normalizeQuery($query));
            if ($queryString !== '') {
                $url .= (str_contains($url, '?') ? '&' : '?') . $queryString;
            }
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) ($options['timeout'] ?? $this->timeout));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifyPeer);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifyPeer ? 2 : 0);

        $headers = $this->prepareHeaders($options['headers'] ?? []);

        if (array_key_exists('json', $options)) {
            $json = json_encode($options['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new RuntimeException('Не удалось сериализовать JSON.');
            }
            $body = $json;
            if (!$this->hasHeader($headers, 'Content-Type')) {
                $headers[] = 'Content-Type: application/json';
            }
        } elseif (array_key_exists('body', $options)) {
            $body = (string) $options['body'];
        } else {
            $body = null;
        }

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($chResource, string $line) use (&$responseHeaders): int {
            $length = strlen($line);
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, 'HTTP/')) {
                return $length;
            }
            [$name, $value] = array_map('trim', explode(':', $trimmed, 2));
            $key = strtolower($name);
            $responseHeaders[$key] ??= [];
            $responseHeaders[$key][] = $value;
            return $length;
        });

        $rawBody = curl_exec($ch);
        if ($rawBody === false) {
            $errorMessage = curl_error($ch);
            $errorCode = curl_errno($ch);
            curl_close($ch);
            throw new RuntimeException(sprintf('Ошибка cURL (%d): %s', $errorCode, $errorMessage));
        }

        $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: 0;
        curl_close($ch);

        $bodyText = (string) $rawBody;
        $decoded = $this->decodeJson($bodyText);

        if ($statusCode >= 400) {
            $message = $this->resolveErrorMessage($decoded, $bodyText, $statusCode);
            throw new HttpException($statusCode, $message, [
                'body' => $bodyText,
                'json' => $decoded,
                'headers' => $responseHeaders,
            ]);
        }

        return [
            'status' => $statusCode,
            'body' => $bodyText,
            'headers' => $responseHeaders,
            'json' => $decoded,
        ];
    }

    /**
     * @param array<string,array<int,string>>|null $decoded
     */
    private function resolveErrorMessage(?array $decoded, string $fallback, int $statusCode): string
    {
        if ($decoded !== null) {
            foreach (['message', 'error', 'detail', 'description'] as $key) {
                if (isset($decoded[$key]) && is_string($decoded[$key]) && $decoded[$key] !== '') {
                    return $decoded[$key];
                }
            }
        }

        $trimmed = trim($fallback);
        if ($trimmed !== '') {
            return sprintf('HTTP %d: %s', $statusCode, mb_substr($trimmed, 0, 512));
        }

        return sprintf('HTTP %d', $statusCode);
    }

    /**
     * @param array<string,mixed> $headers
     * @return list<string>
     */
    private function prepareHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            if (is_int($name)) {
                $normalized[] = (string) $value;
                continue;
            }
            if (is_array($value)) {
                foreach ($value as $item) {
                    $normalized[] = $name . ': ' . $item;
                }
                continue;
            }
            $normalized[] = $name . ': ' . $value;
        }
        return $normalized;
    }

    /**
     * @param list<string> $headers
     */
    private function hasHeader(array $headers, string $needle): bool
    {
        $needleLower = strtolower($needle);
        foreach ($headers as $header) {
            [$name] = explode(':', $header, 2);
            if (strtolower(trim($name)) === $needleLower) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    private function normalizeQuery(array $query): array
    {
        $normalized = [];
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_bool($value)) {
                $normalized[$key] = $value ? 'true' : 'false';
                continue;
            }
            $normalized[$key] = $value;
        }
        return $normalized;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function decodeJson(string $body): ?array
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return null;
        }
        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return null;
        }
        return is_array($decoded) ? $decoded : null;
    }
}
