<?php
session_start();

// API endpoints
define('TRUE_API_URL', 'https://markirovka.crpt.ru/api/v3/true-api');
define('NK_API_URL', 'https://xn--80ajghhoc2aj1c8b.xn--p1ai');
define('SUZ_API_URL', 'https://suzgrid.crpt.ru/api/v3');


/**
 * Сервисный лог приложения.
 */
function appLog(string $message, array $context = []): void {
    static $logFile = null;

    if ($logFile === null) {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/app.log';
    }

    if (!empty($context)) {
        $context = sanitizeLogContext($context);
        $message .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    error_log('[' . date('c') . '] ' . $message . PHP_EOL, 3, $logFile);
}

function sanitizeLogContext(array $context): array {
    if (isset($context['headers']) && is_array($context['headers'])) {
        $context['headers'] = maskSensitiveHeaders($context['headers']);
    }

    if (isset($context['responsePreview']) && is_string($context['responsePreview'])) {
        $context['responsePreview'] = trimResponse($context['responsePreview']);
    }

    return $context;
}

function maskSensitiveHeaders(array $headers): array {
    $sensitive = ['authorization', 'clienttoken', 'token', 'x-signature', 'signature'];

    foreach ($headers as &$header) {
        if (!is_string($header) || strpos($header, ':') === false) {
            continue;
        }

        [$name, $value] = array_map('trim', explode(':', $header, 2));
        if (in_array(strtolower($name), $sensitive, true)) {
            $value = maskSensitiveValue($value);
            $header = $name . ': ' . $value;
        }
    }
    unset($header);

    return $headers;
}

function maskSensitiveValue(string $value): string {
    $length = strlen($value);
    if ($length === 0) {
        return $value;
    }

    if ($length <= 8) {
        return str_repeat('*', $length);
    }

    return substr($value, 0, 4) . str_repeat('*', $length - 8) . substr($value, -4);
}

function trimResponse(string $response, int $limit = 2048): string {
    if (strlen($response) <= $limit) {
        return $response;
    }

    return substr($response, 0, $limit) . '...';
}

/**
 * Выполнить HTTP-запрос и вернуть «сырой» ответ.
 */
function apiRequestRaw(string $url, string $method = 'GET', ?array $headers = null, $body = null): array {
    $ch = curl_init($url);

    $defaultHeaders = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    $preparedHeaders = $headers ?: $defaultHeaders;
    $bodyPayload = null;

    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $preparedHeaders,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
    ];

 

    // НОВОЕ: Увеличенный таймаут DNS
    if (defined('CURLOPT_DNS_CACHE_TIMEOUT')) {
        $curlOptions[CURLOPT_DNS_CACHE_TIMEOUT] = 300; // 5 минут
    }

    curl_setopt_array($ch, $curlOptions);

    if ($body !== null) {
    $bodyPayload = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyPayload);
    
    // ДОБАВИТЬ: логирование для отладки
    appLog('Sending request body', [
        'length' => strlen($bodyPayload),
        'preview' => substr($bodyPayload, 0, 500)
    ]);
}

    $response = curl_exec($ch);
    if ($response === false) {
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        $host = parse_url($url, PHP_URL_HOST) ?: null;
        $dnsInfo = null;

        // Попытка получить DNS-информацию
        if ($host && function_exists('dns_get_record')) {
            $dnsRecords = @dns_get_record($host, DNS_A + DNS_AAAA);
            if ($dnsRecords !== false && !empty($dnsRecords)) {
                $dnsInfo = array_map(static function (array $record): array {
                    return array_filter([
                        'type' => $record['type'] ?? null,
                        'ip' => $record['ip'] ?? ($record['ipv6'] ?? null),
                    ]);
                }, $dnsRecords);
            } else {
                $dnsInfo = ['error' => 'DNS resolution failed'];
            }
        }

        appLog('HTTP request failed', [
            'url' => $url,
            'method' => $method,
            'headers' => $preparedHeaders,
            'bodyLength' => $bodyPayload === null ? 0 : strlen($bodyPayload),
            'curl_errno' => $errno,
            'curl_error' => $error,
            'host' => $host,
            'dns' => $dnsInfo,
            'curl_info' => $info,
        ]);

        if ($errno === CURLE_COULDNT_RESOLVE_HOST && $host) {
            $suggestions = "Решения:\n";
            $suggestions .= "1. Проверьте DNS на сервере: cat /etc/resolv.conf\n";
            $suggestions .= "2. Добавьте в /etc/resolv.conf:\n   nameserver 8.8.8.8\n   nameserver 8.8.4.4\n";
            $suggestions .= "3. Или используйте прямой IP в /etc/hosts:\n   $(dig +short $host) $host\n";
            $suggestions .= "4. Раскомментируйте USE_CUSTOM_DNS в config.php";
            
            $error .= sprintf("\n\nНе удалось разрешить хост %s.\n%s", $host, $suggestions);
        }

        throw new Exception('Ошибка запроса: ' . $error);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
    curl_close($ch);

    if ($httpCode >= 400) {
        appLog('HTTP response error', [
            'url' => $url,
            'method' => $method,
            'headers' => $preparedHeaders,
            'bodyLength' => $bodyPayload === null ? 0 : strlen($bodyPayload),
            'httpCode' => $httpCode,
            'contentType' => $contentType,
            'responsePreview' => $response,
        ]);

        throw new Exception("HTTP $httpCode: $response");
    }

    return [
        'body' => $response,
        'contentType' => $contentType,
    ];
}

/**
 * Выполнить HTTP-запрос и декодировать JSON-ответ.
 */
function apiRequest(string $url, string $method = 'GET', ?array $headers = null, $body = null): array {
    $raw = apiRequestRaw($url, $method, $headers, $body);
    if ($raw['body'] === '' || $raw['body'] === null) {
        return [];
    }

    $decoded = json_decode($raw['body'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Ошибка декодирования JSON: ' . json_last_error_msg());
    }

    return $decoded;
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