<?php
declare(strict_types=1);

use App\Services\NationalCatalogClient;
use App\Support\HttpClient;
use App\Support\HttpHelpers;

$config = require __DIR__ . '/../../app/bootstrap.php';

$http = new HttpClient(
    (int) ($config['http']['timeout'] ?? 45),
    (bool) ($config['http']['verify_peer'] ?? true)
);
$client = new NationalCatalogClient($http, $config['national_catalog'] ?? []);

defaultHeaders();
$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $limit = isset($_GET['limit']) ? max(1, min(1000, (int) $_GET['limit'])) : 100;
            $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
            $filters = [
                'search' => $_GET['search'] ?? null,
                'dateFrom' => $_GET['dateFrom'] ?? null,
                'dateTo' => $_GET['dateTo'] ?? null,
                'group' => $_GET['group'] ?? null,
                'status' => $_GET['status'] ?? null,
                'order' => $_GET['order'] ?? null,
            ];
            $result = $client->listProducts($filters, $limit, $offset);
            HttpHelpers::json($result);
            break;

        case 'details':
            HttpHelpers::requireMethod('POST');
            $payload = HttpHelpers::readJsonBody();
            $goodIds = $payload['goodIds'] ?? [];
            if (!is_array($goodIds) || $goodIds === []) {
                throw new InvalidArgumentException('goodIds должен содержать массив идентификаторов.');
            }
            $result = $client->fetchProductDetails($goodIds);
            HttpHelpers::json($result);
            break;

        case 'card':
            $gtin = isset($_GET['gtin']) ? trim((string) $_GET['gtin']) : '';
            if ($gtin === '') {
                throw new InvalidArgumentException('Укажите gtin.');
            }
            $result = $client->getProductByGtin($gtin);
            HttpHelpers::json($result);
            break;

        case 'docs-for-sign':
            HttpHelpers::requireMethod('POST');
            $payload = HttpHelpers::readJsonBody();
            $goodIds = $payload['goodIds'] ?? [];
            if (!is_array($goodIds) || $goodIds === []) {
                throw new InvalidArgumentException('Список goodIds пуст.');
            }
            $publicationAgreement = isset($payload['publicationAgreement'])
                ? (bool) $payload['publicationAgreement']
                : true;
            $result = $client->requestDocumentsForSign($goodIds, $publicationAgreement);
            HttpHelpers::json($result);
            break;

        case 'send-signatures':
            HttpHelpers::requireMethod('POST');
            $payload = HttpHelpers::readJsonBody();
            $pack = $payload['pack'] ?? [];
            if (!is_array($pack) || $pack === []) {
                throw new InvalidArgumentException('pack должен содержать массив подписей.');
            }
            $result = $client->sendSignatures($pack);
            HttpHelpers::json($result);
            break;

        default:
            throw new InvalidArgumentException('Неизвестное действие.');
    }
} catch (Throwable $exception) {
    HttpHelpers::handleException($exception);
}

function defaultHeaders(): void
{
    header('Content-Type: application/json; charset=utf-8');
}
