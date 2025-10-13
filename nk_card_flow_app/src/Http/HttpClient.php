<?php

namespace NkCardFlow\Http;

use NkCardFlow\Logger\FileLogger;
use NkCardFlow\Logger\LogLevel;
use RuntimeException;

class HttpClient
{
    public function __construct(private FileLogger $logger)
    {
    }

    /**
     * @param array{headers?: array<string,string>, query?: array<string, scalar>, body?: string, timeout?: int} $options
     */
    public function request(string $method, string $url, array $options = []): HttpResponse
    {
        $curl = curl_init();
        if ($curl === false) {
            throw new RuntimeException('Unable to initialize cURL');
        }

        $headers = [];
        foreach ($options['headers'] ?? [] as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }

        $query = $options['query'] ?? [];
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $options['timeout'] ?? 30,
        ]);

        if (isset($options['body'])) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $options['body']);
        }

        $start = microtime(true);
        $body = curl_exec($curl);
        $elapsed = microtime(true) - $start;

        if ($body === false) {
            $error = curl_error($curl);
            $code = curl_errno($curl);
            curl_close($curl);
            $this->logger->error('HTTP {method} {url} failed: {error}', [
                'method' => strtoupper($method),
                'url' => $url,
                'error' => $error,
            ]);

            throw new RuntimeException(sprintf('HTTP request failed: %s (code %d)', $error, $code));
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE) ?: '';
        curl_close($curl);

        $this->logger->info('HTTP {method} {url} -> {status} ({elapsed} s)', [
            'method' => strtoupper($method),
            'url' => $url,
            'status' => $statusCode,
            'elapsed' => number_format($elapsed, 3),
        ]);

        return new HttpResponse($statusCode, $body, $contentType, $headers);
    }
}
