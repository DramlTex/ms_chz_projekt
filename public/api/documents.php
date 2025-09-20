<?php
declare(strict_types=1);

use App\Services\TrueApiClient;
use App\Support\HttpClient;
use App\Support\HttpHelpers;

$config = require __DIR__ . '/../../app/bootstrap.php';

$http = new HttpClient(
    (int) ($config['http']['timeout'] ?? 45),
    (bool) ($config['http']['verify_peer'] ?? true)
);
$client = new TrueApiClient($http, $config['true_api'] ?? []);

$session = $_SESSION['true_api'] ?? [];
$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'create':
            HttpHelpers::requireMethod('POST');
            $body = HttpHelpers::readJsonBody();
            $productGroup = (string) ($body['productGroup'] ?? ($_GET['productGroup'] ?? ''));
            if ($productGroup === '') {
                throw new InvalidArgumentException('productGroup обязателен.');
            }
            $payload = $body['payload'] ?? ($body['document'] ?? null);
            if (!is_array($payload)) {
                throw new InvalidArgumentException('payload должен содержать документ True API.');
            }
            $token = resolveTrueApiToken($body, $session);
            $result = $client->createDocument($token, $productGroup, $payload);
            HttpHelpers::json(['document' => $result]);
            break;

        case 'list':
            $productGroup = (string) ($_GET['productGroup'] ?? $_GET['pg'] ?? '');
            if ($productGroup === '') {
                throw new InvalidArgumentException('Укажите productGroup (pg).');
            }
            $filters = array_filter([
                'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : null,
                'offset' => isset($_GET['offset']) ? (int) $_GET['offset'] : null,
                'documentFormat' => $_GET['documentFormat'] ?? null,
                'type' => $_GET['type'] ?? null,
            ], static fn($value) => $value !== null && $value !== '');
            $token = resolveTrueApiToken($_GET, $session);
            $result = $client->listDocuments($token, $productGroup, $filters);
            HttpHelpers::json($result);
            break;

        case 'info':
            $documentId = isset($_GET['documentId']) ? trim((string) $_GET['documentId']) : '';
            if ($documentId === '') {
                throw new InvalidArgumentException('documentId обязателен.');
            }
            $token = resolveTrueApiToken($_GET, $session);
            $result = $client->getDocumentInfo($token, $documentId);
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
 */
function resolveTrueApiToken(array $source, array $session): string
{
    $token = '';
    if (!empty($source['token'])) {
        $token = (string) $source['token'];
    }
    if ($token === '' && isset($session['token'])) {
        $token = (string) $session['token'];
    }
    if ($token === '') {
        throw new InvalidArgumentException('Отсутствует токен True API. Авторизуйтесь.');
    }
    return $token;
}
