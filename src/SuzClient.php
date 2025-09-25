<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/OrderAuth.php';

final class SuzClient
{
    public static function createOrder(string $omsId, string $clientToken, string $signature, array $payload): array
    {
        $headers = [
            'clientToken: ' . $clientToken,
            'X-Signature: ' . $signature,
        ];
        $uri = '/order?omsId=' . rawurlencode($omsId);
        return suzRequest('POST', $uri, $headers, $payload);
    }

    public static function list(string $omsId, string $clientToken, array $params = []): array
    {
        $headers = [
            'clientToken: ' . $clientToken,
        ];
        $query = $params ? '&' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '';
        return suzRequest('GET', '/order/list?omsId=' . rawurlencode($omsId) . $query, $headers);
    }

    public static function close(string $omsId, string $clientToken, string $signature, string $orderId): array
    {
        $headers = [
            'clientToken: ' . $clientToken,
            'X-Signature: ' . $signature,
        ];
        $payload = ['orderId' => $orderId];
        return suzRequest('POST', '/order/close?omsId=' . rawurlencode($omsId), $headers, $payload);
    }

    public static function ping(string $omsId, string $clientToken): array
    {
        $headers = [
            'clientToken: ' . $clientToken,
        ];
        return suzRequest('GET', '/ping?omsId=' . rawurlencode($omsId), $headers);
    }
}
