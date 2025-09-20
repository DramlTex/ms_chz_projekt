<?php
declare(strict_types=1);

use App\Services\SuzClient;
use App\Services\TrueApiClient;
use App\Support\HttpClient;
use App\Support\HttpHelpers;

$config = require __DIR__ . '/../../app/bootstrap.php';

$http = new HttpClient(
    (int) ($config['http']['timeout'] ?? 45),
    (bool) ($config['http']['verify_peer'] ?? true)
);
$trueApiClient = new TrueApiClient($http, $config['true_api'] ?? []);
$suzClient = new SuzClient($http, $config['suz'] ?? []);

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'session':
            HttpHelpers::json([
                'trueApi' => $_SESSION['true_api'] ?? null,
                'suz' => $_SESSION['suz'] ?? null,
            ]);
            break;

        case 'true-api-key':
            $inn = isset($_GET['inn']) ? trim((string) $_GET['inn']) : null;
            $challenge = $trueApiClient->requestAuthKey($inn !== '' ? $inn : null);
            $_SESSION['true_api_challenge'] = $challenge;
            HttpHelpers::json(['challenge' => $challenge]);
            break;

        case 'true-api-signin':
            HttpHelpers::requireMethod('POST');
            $payload = HttpHelpers::readJsonBody();
            $uuid = (string) ($payload['uuid'] ?? '');
            $signature = (string) ($payload['signature'] ?? '');
            $inn = isset($payload['inn']) ? (string) $payload['inn'] : null;
            $unitedToken = isset($payload['unitedToken']) ? (bool) $payload['unitedToken'] : false;
            if ($uuid === '' || $signature === '') {
                throw new InvalidArgumentException('uuid и signature обязательны.');
            }
            $result = $trueApiClient->signIn($uuid, $signature, $inn, $unitedToken);
            $_SESSION['true_api'] = $result;
            HttpHelpers::json($result);
            break;

        case 'suz-key':
            $omsId = isset($_GET['omsId']) ? trim((string) $_GET['omsId']) : null;
            $challenge = $suzClient->requestAuthKey($omsId !== '' ? $omsId : null);
            $_SESSION['suz_challenge'] = $challenge;
            HttpHelpers::json(['challenge' => $challenge]);
            break;

        case 'suz-signin':
            HttpHelpers::requireMethod('POST');
            $payload = HttpHelpers::readJsonBody();
            $signature = (string) ($payload['signature'] ?? '');
            $uuid = isset($payload['uuid']) ? (string) $payload['uuid'] : null;
            $inn = isset($payload['inn']) ? (string) $payload['inn'] : null;
            $omsConnection = (string) ($payload['omsConnection'] ?? ($config['suz']['oms_connection'] ?? ''));
            $omsId = (string) ($payload['omsId'] ?? ($config['suz']['oms_id'] ?? ''));
            if ($signature === '') {
                throw new InvalidArgumentException('signature обязателен.');
            }
            if ($omsConnection === '') {
                throw new InvalidArgumentException('Укажите omsConnection.');
            }
            if ($omsId === '') {
                throw new InvalidArgumentException('Укажите omsId.');
            }
            $result = $suzClient->signIn($omsConnection, $signature, $uuid, $inn);
            $_SESSION['suz'] = array_merge($result, [
                'omsId' => $omsId,
                'omsConnection' => $omsConnection,
            ]);
            HttpHelpers::json($_SESSION['suz']);
            break;

        default:
            throw new InvalidArgumentException('Неизвестное действие.');
    }
} catch (Throwable $exception) {
    HttpHelpers::handleException($exception);
}
