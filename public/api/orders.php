<?php
declare(strict_types=1);

use App\Services\SuzClient;
use App\Support\HttpClient;
use App\Support\HttpHelpers;

$config = require __DIR__ . '/../../app/bootstrap.php';

$http = new HttpClient(
    (int) ($config['http']['timeout'] ?? 45),
    (bool) ($config['http']['verify_peer'] ?? true)
);
$client = new SuzClient($http, $config['suz'] ?? []);

$session = $_SESSION['suz'] ?? [];
$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'create':
            HttpHelpers::requireMethod('POST');
            $body = HttpHelpers::readJsonBody();
            $payload = $body['payload'] ?? ($body['order'] ?? null);
            if (!is_array($payload)) {
                throw new InvalidArgumentException('payload должен содержать JSON заказа.');
            }
            $signature = (string) ($body['signature'] ?? '');
            if ($signature === '') {
                throw new InvalidArgumentException('signature обязателен.');
            }
            [$omsId, $clientToken] = resolveSuzContext($body, $session, $config);
            $result = $client->createOrder($omsId, $clientToken, $signature, $payload);
            HttpHelpers::json(['order' => $result]);
            break;

        case 'list':
            $filters = array_filter([
                'orderId' => $_GET['orderId'] ?? null,
                'status' => $_GET['status'] ?? null,
                'startDate' => $_GET['startDate'] ?? $_GET['dateFrom'] ?? null,
                'endDate' => $_GET['endDate'] ?? $_GET['dateTo'] ?? null,
                'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : null,
                'offset' => isset($_GET['offset']) ? (int) $_GET['offset'] : null,
            ], static fn($value) => $value !== null && $value !== '');
            [$omsId, $clientToken] = resolveSuzContext($_GET, $session, $config);
            $result = $client->listOrders($omsId, $clientToken, $filters);
            HttpHelpers::json($result);
            break;

        case 'close':
            HttpHelpers::requireMethod('POST');
            $body = HttpHelpers::readJsonBody();
            $orderId = (string) ($body['orderId'] ?? '');
            if ($orderId === '') {
                throw new InvalidArgumentException('orderId обязателен.');
            }
            $signature = (string) ($body['signature'] ?? '');
            if ($signature === '') {
                throw new InvalidArgumentException('signature обязателен.');
            }
            [$omsId, $clientToken] = resolveSuzContext($body, $session, $config);
            $result = $client->closeOrder($omsId, $clientToken, $signature, $orderId);
            HttpHelpers::json($result);
            break;

        case 'dropout':
            HttpHelpers::requireMethod('POST');
            $body = HttpHelpers::readJsonBody();
            $payload = $body['payload'] ?? null;
            if (!is_array($payload)) {
                throw new InvalidArgumentException('payload должен содержать JSON выбытия.');
            }
            $signature = (string) ($body['signature'] ?? '');
            if ($signature === '') {
                throw new InvalidArgumentException('signature обязателен.');
            }
            [$omsId, $clientToken] = resolveSuzContext($body, $session, $config);
            $result = $client->createDropout($omsId, $clientToken, $signature, $payload);
            HttpHelpers::json($result);
            break;

        case 'utilisation':
            HttpHelpers::requireMethod('POST');
            $body = HttpHelpers::readJsonBody();
            $payload = $body['payload'] ?? null;
            if (!is_array($payload)) {
                throw new InvalidArgumentException('payload должен содержать JSON утилизации.');
            }
            $signature = (string) ($body['signature'] ?? '');
            if ($signature === '') {
                throw new InvalidArgumentException('signature обязателен.');
            }
            [$omsId, $clientToken] = resolveSuzContext($body, $session, $config);
            $result = $client->submitUtilisation($omsId, $clientToken, $signature, $payload);
            HttpHelpers::json($result);
            break;

        default:
            throw new InvalidArgumentException('Неизвестное действие.');
    }
} catch (Throwable $exception) {
    HttpHelpers::handleException($exception);
}

/**
 * @param array<string,mixed> $source
 * @return array{string,string}
 */
function resolveSuzContext(array $source, array $session, array $config): array
{
    $omsId = '';
    $clientToken = '';

    if (!empty($source['omsId'])) {
        $omsId = (string) $source['omsId'];
    }
    if (!empty($source['clientToken'])) {
        $clientToken = (string) $source['clientToken'];
    }

    if ($omsId === '' && isset($session['omsId'])) {
        $omsId = (string) $session['omsId'];
    }
    if ($clientToken === '' && isset($session['clientToken'])) {
        $clientToken = (string) $session['clientToken'];
    }

    if ($omsId === '' && isset($config['suz']['oms_id'])) {
        $omsId = (string) $config['suz']['oms_id'];
    }

    if ($omsId === '') {
        throw new InvalidArgumentException('Не задан omsId.');
    }
    if ($clientToken === '') {
        throw new InvalidArgumentException('clientToken отсутствует. Авторизуйтесь в СУЗ.');
    }

    return [$omsId, $clientToken];
}
