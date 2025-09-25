<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

const ORDER_TRUE_API_TOKEN_SESSION_KEY = 'order_true_api_token';
const ORDER_SUZ_TOKEN_SESSION_KEY      = 'order_suz_client_token';
const ORDER_SUZ_CONTEXT_SESSION_KEY    = 'order_suz_context';

function orderSuzContextDefaults(): array
{
    return [
        'oms_id'           => '',
        'oms_connect'      => '',
        'participant_inn'  => '',
        'station_url'      => SUZ_BASE_URL,
        'location_address' => '',
    ];
}

function orderStoreSuzContext(array $context): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $defaults = orderSuzContextDefaults();
    $normalized = $defaults;

    foreach ($defaults as $key => $_) {
        if (!array_key_exists($key, $context)) {
            continue;
        }
        $value = trim((string)$context[$key]);
        $normalized[$key] = $value;
    }

    $_SESSION[ORDER_SUZ_CONTEXT_SESSION_KEY] = $normalized;
}

function orderForgetSuzContext(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION[ORDER_SUZ_CONTEXT_SESSION_KEY]);
    }
}

function orderGetSuzContext(): array
{
    $defaults = orderSuzContextDefaults();

    if (session_status() !== PHP_SESSION_ACTIVE) {
        return $defaults;
    }

    $stored = $_SESSION[ORDER_SUZ_CONTEXT_SESSION_KEY] ?? [];
    if (!is_array($stored)) {
        return $defaults;
    }

    $context = $defaults;
    foreach ($defaults as $key => $_) {
        if (!array_key_exists($key, $stored)) {
            continue;
        }
        $context[$key] = trim((string)$stored[$key]);
    }

    if ($context['station_url'] === '') {
        $context['station_url'] = SUZ_BASE_URL;
    }

    return $context;
}

function orderStoreTrueApiToken(string $token, ?int $expiresAt = null): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    if ($expiresAt === null || $expiresAt <= time()) {
        $expiresAt = nkGuessTokenExpiration($token);
    }

    $_SESSION[ORDER_TRUE_API_TOKEN_SESSION_KEY] = [
        'token'      => $token,
        'expires_at' => $expiresAt,
    ];
}

function orderForgetTrueApiToken(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION[ORDER_TRUE_API_TOKEN_SESSION_KEY]);
    }
}

function orderGetTrueApiTokenMeta(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }

    $data = $_SESSION[ORDER_TRUE_API_TOKEN_SESSION_KEY] ?? null;
    if (!is_array($data) || empty($data['token'])) {
        return null;
    }

    $expiresAt = isset($data['expires_at']) && is_int($data['expires_at'])
        ? $data['expires_at']
        : nkGuessTokenExpiration((string)$data['token']);

    if ($expiresAt !== null && $expiresAt <= time()) {
        unset($_SESSION[ORDER_TRUE_API_TOKEN_SESSION_KEY]);
        return null;
    }

    return [
        'token'      => (string)$data['token'],
        'expires_at' => $expiresAt,
    ];
}

function orderStoreSuzToken(string $token, string $omsId, ?int $expiresAt = null, ?string $omsConnection = null): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    if ($expiresAt !== null && $expiresAt <= time()) {
        $expiresAt = null;
    }

    $_SESSION[ORDER_SUZ_TOKEN_SESSION_KEY] = [
        'token'       => $token,
        'expires_at'  => $expiresAt,
        'oms_id'      => $omsId,
        'oms_connect' => $omsConnection,
    ];

    $context = orderGetSuzContext();
    $context['oms_id'] = $omsId;
    if ($omsConnection !== null) {
        $context['oms_connect'] = $omsConnection;
    }
    orderStoreSuzContext($context);
}

function orderForgetSuzToken(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION[ORDER_SUZ_TOKEN_SESSION_KEY]);
    }
}

function orderGetSuzTokenMeta(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }

    $data = $_SESSION[ORDER_SUZ_TOKEN_SESSION_KEY] ?? null;
    if (!is_array($data) || empty($data['token'])) {
        return null;
    }

    $expiresAt = isset($data['expires_at']) && is_int($data['expires_at'])
        ? $data['expires_at']
        : null;

    if ($expiresAt !== null && $expiresAt <= time()) {
        unset($_SESSION[ORDER_SUZ_TOKEN_SESSION_KEY]);
        return null;
    }

    $context = orderGetSuzContext();

    return [
        'token'       => (string)$data['token'],
        'expires_at'  => $expiresAt,
        'oms_id'      => isset($data['oms_id']) ? (string)$data['oms_id'] : $context['oms_id'],
        'oms_connect' => isset($data['oms_connect']) ? (string)$data['oms_connect'] : $context['oms_connect'],
    ];
}
