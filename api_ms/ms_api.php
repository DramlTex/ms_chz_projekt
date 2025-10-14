<?php
require_once __DIR__ . '/../auth.php';

// Все ответы API возвращаются в формате JSON
header('Content-Type: application/json; charset=utf-8');

function ms_api_mock_enabled(): bool {
    return !empty($_SESSION['ms_api_mock']);
}

function ms_api_mock_response(string $endpoint): ?array {
    if (!ms_api_mock_enabled()) {
        return null;
    }

    $parsed = parse_url($endpoint);
    if ($parsed === false) {
        return ['rows' => []];
    }

    $path = $parsed['path'] ?? '';
    $query = [];
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $query);
    }

    if (preg_match('#/entity/store/([^/]+)/slots$#', $path, $matches)) {
        if (!empty($query['offset'])) {
            return ['rows' => []];
        }
        $storeId = $matches[1];
        return [
            'rows' => [
                ['id' => $storeId . '-slot-1', 'name' => 'Слот 1'],
                ['id' => $storeId . '-slot-2', 'name' => 'Слот 2'],
            ],
        ];
    }

    if (str_ends_with($path, '/entity/slot')) {
        if (!empty($query['offset'])) {
            return ['rows' => []];
        }
        return [
            'rows' => [
                ['id' => 'mock-slot-1', 'name' => 'Слот 1'],
                ['id' => 'mock-slot-2', 'name' => 'Слот 2'],
            ],
        ];
    }

    if (str_ends_with($path, '/entity/store')) {
        return [
            'rows' => [
                ['id' => 'mock-store-1', 'name' => 'Тестовый склад'],
            ],
        ];
    }

    return ['rows' => []];
}

/**
 * Отправляет информацию о запросе в журнал.
 */
function ms_log_request(string $endpoint, int $code, float $timeMs, ?string $responseBody = null): void {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    $url    = "$scheme://$host$base/log_request.php";

    $payload = [
        'script'        => $_SERVER['SCRIPT_NAME'] ?? null,
        'account'       => get_account(),
        'user'          => get_user(),
        'request'       => $endpoint,
        'response_time' => $timeMs,
        'response_code' => $code,
    ];
    if ($responseBody !== null) {
        $payload['response_body'] = $responseBody;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST              => true,
        CURLOPT_POSTFIELDS        => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER        => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER    => true,
        CURLOPT_TIMEOUT_MS        => 1000,
        CURLOPT_CONNECTTIMEOUT_MS => 1000,
    ]);
    $result = curl_exec($ch);
    $err    = curl_errno($ch);
    curl_close($ch);

    // If the HTTP request failed, write the log directly as a fallback.
    if ($result === false || $err) {
        if (!function_exists('ms_write_log')) {
            require __DIR__ . '/log_request.php';
        }
        ms_write_log($payload);
    }
}

/**
 * Возвращает или создаёт cURL-соединение для запросов к МойСклад.
 *
 * @param bool $close Закрыть текущее соединение.
 * @return resource|null
 */
function ms_api_curl_handle(bool $close = false) {
    static $ch = null;
    if ($close) {
        if ($ch !== null) {
            curl_close($ch);
            $ch = null;
        }
        return null;
    }
    if ($ch === null) {
        $ch = curl_init();
        register_shutdown_function('ms_api_close');
    }
    return $ch;
}

/**
 * Инициализирует соединение и настраивает базовые параметры.
 */
function ms_api_connect(?string $login = null, ?string $password = null): void {
    if ($login === null || $password === null) {
        list($login, $password) = get_credentials();
    }
    if ($login === '' || $password === '') {
        ms_api_error('missing credentials');
    }
    $ch = ms_api_curl_handle();
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
        CURLOPT_USERPWD        => "$login:$password",
        CURLOPT_ENCODING       => 'gzip',
    ]);
}

/**
 * Закрывает cURL-соединение.
 */
function ms_api_close(): void {
    ms_api_curl_handle(true);
}

/**
 * Низкоуровневый запрос к API МойСклад.
 * При ошибке бросает исключение RuntimeException.
 */
function ms_api_request(
    string $endpoint,
    ?string $login = null,
    ?string $password = null,
    string $method = 'GET',
    ?array $payload = null
): array {
    if (ms_api_mock_enabled()) {
        $mock = ms_api_mock_response($endpoint);
        if ($mock !== null) {
            return $mock;
        }
    }

    ms_api_connect($login, $password);
    $ch = ms_api_curl_handle();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    $headers = [
        'Accept: application/json;charset=utf-8',
        'Accept-Encoding: gzip',
    ];
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, null);
    }
    if (strtoupper($method) !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    } else {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $start    = microtime(true);
    $response = curl_exec($ch);
    $timeMs   = (microtime(true) - $start) * 1000;
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errNo    = curl_errno($ch);
    $errMsg   = $errNo ? curl_error($ch) : null;

    $responseBody = null;
    if ($errNo) {
        $responseBody = $errMsg;
    } elseif ($code < 200 || $code >= 300) {
        $responseBody = $response;
    }

    ms_log_request($endpoint, $errNo ? 0 : $code, $timeMs, $responseBody);

    if ($errNo) {
        throw new RuntimeException('curl error: ' . $errMsg);
    }

    $data = json_decode($response, true);

    if ($code < 200 || $code >= 300) {
        if (is_array($data) && isset($data['errors'][0]['code']) && (int)$data['errors'][0]['code'] === 1061) {
            throw new RuntimeException('no_api_access');
        }
        throw new RuntimeException('HTTP error ' . $code);
    }

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('JSON decode error');
    }
    return $data;
}

/**
 * Выполняет запрос и выводит JSON-ошибку при неудаче.
 */
function ms_api(
    string $endpoint,
    ?string $login = null,
    ?string $password = null,
    string $method = 'GET',
    ?array $payload = null
): array {
    try {
        return ms_api_request($endpoint, $login, $password, $method, $payload);
    } catch (RuntimeException $e) {
        ms_api_error($e->getMessage());
    }
}

/**
 * Выполняет запрос и возвращает null при ошибке.
 */
function ms_api_try(
    string $endpoint,
    ?string $login = null,
    ?string $password = null,
    string $method = 'GET',
    ?array $payload = null
): ?array {
    try {
        return ms_api_request($endpoint, $login, $password, $method, $payload);
    } catch (RuntimeException $e) {
        return null;
    }
}

/**
 * Выводит JSON с сообщением об ошибке и завершает работу.
 */
function ms_api_error(string $message): void {
    ms_api_close();
    http_response_code(500);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
