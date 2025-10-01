<?php
session_start();

// API endpoints
define('TRUE_API_URL', 'https://markirovka.crpt.ru/api/v3/true-api');
define('NK_API_URL', 'https://xn--80ajghhoc2aj1c8b.xn--p1ai');
define('SUZ_API_URL', 'https://suzcloud.crpt.ru/api/v3');

// Функция HTTP-запроса
function apiRequest(string $url, string $method = 'GET', ?array $headers = null, $body = null): array {
    $ch = curl_init($url);
    
    $defaultHeaders = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers ?: $defaultHeaders,
        CURLOPT_TIMEOUT => 30,
    ]);
    
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 400) {
        throw new Exception("HTTP $httpCode: $response");
    }
    
    return $response ? json_decode($response, true) : [];
}

// Работа с токенами
function getToken(string $key): ?string {
    if (empty($_SESSION[$key]['token'])) return null;
    
    $expiresAt = $_SESSION[$key]['expires_at'] ?? 0;
    if ($expiresAt > 0 && time() > $expiresAt) {
        unset($_SESSION[$key]);
        return null;
    }
    
    return $_SESSION[$key]['token'];
}

function setToken(string $key, string $token, ?int $expiresAt = null): void {
    $_SESSION[$key] = [
        'token' => $token,
        'expires_at' => $expiresAt ?: (time() + 36000),
    ];
}

function clearToken(string $key): void {
    unset($_SESSION[$key]);
}

// Работа с OMS настройками
function getOmsSettings(): array {
    return [
        'connection' => $_SESSION['oms_connection'] ?? '',
        'id' => $_SESSION['oms_id'] ?? '',
    ];
}

function setOmsSettings(string $connection, string $id): void {
    $_SESSION['oms_connection'] = trim($connection);
    $_SESSION['oms_id'] = trim($id);
}

function clearOmsSettings(): void {
    unset($_SESSION['oms_connection'], $_SESSION['oms_id']);
}