<?php
declare(strict_types=1);

// Национальный каталог работает по московскому времени
// (на это полагается большинство API и расписаний).
date_default_timezone_set('Europe/Moscow');

// ---------------------------------------------------------------
//  БАЗОВЫЕ КОНСТАНТЫ
// ---------------------------------------------------------------
define('NK_BASE_URL', 'https://апи.национальный-каталог.рф');
define('NK_API_KEY',  getenv('NK_API_KEY') ?: '');
define('TRUE_API_BASE_URL', getenv('TRUE_API_BASE_URL') ?: 'https://markirovka.crpt.ru/api/v3/true-api');
define('SUZ_BASE_URL', getenv('SUZ_BASE_URL') ?: 'https://suzgrid.crpt.ru/api/v3');
define('CRYPTO_PRO_PLUGIN_ID', getenv('CRYPTO_PRO_PLUGIN_ID') ?: 'cadesplugin');

define('NK_LOG', getenv('NK_LOG_PATH') ?: dirname(__DIR__) . '/nk_debug.log');
define('MS_LOG', getenv('MS_LOG_PATH') ?: dirname(__DIR__) . '/ms_debug.log');
define('ORDERS_LOG', getenv('ORDERS_LOG_PATH') ?: dirname(__DIR__) . '/orders_debug.log');

define('MS_BASE_URL', 'https://api.moysklad.ru/api/remap/1.2');
define('MS_TOKEN',    '');

define('NK_TOKEN_SESSION_KEY', 'nk_auth_token');
define('NK_TOKEN_EXP_SAFETY_MARGIN', 60); // секунд
define('NK_TOKEN_DEFAULT_TTL', 9 * 3600);   // 9 часов по умолчанию

// ---------------------------------------------------------------
//  НАСТРОЙКИ ПЛАГИНА CRYPTOPRO
// ---------------------------------------------------------------
function envList(string $value): array
{
    $value = trim($value);
    if ($value === '') {
        return [];
    }

    $parts = preg_split('/[\s,;]+/', $value);
    if ($parts === false) {
        return [];
    }

    $result = [];
    foreach ($parts as $part) {
        $candidate = trim($part);
        if ($candidate === '' || in_array($candidate, $result, true)) {
            continue;
        }
        $result[] = $candidate;
    }

    return $result;
}

function cryptoProExtensionConfig(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $singleId = trim((string)(getenv('CRYPTO_PRO_EXTENSION_ID') ?: ''));
    $ids      = envList((string)(getenv('CRYPTO_PRO_EXTENSION_IDS') ?: ''));

    if ($singleId === '' && isset($ids[0])) {
        $singleId = $ids[0];
    }

    $cache = [
        'id'  => $singleId !== '' ? $singleId : null,
        'ids' => $ids,
    ];

    return $cache;
}

function renderCryptoProExtensionBootstrap(): string
{
    $config = cryptoProExtensionConfig();
    $assignments = [];

    if (!empty($config['id'])) {
        $encoded = json_encode($config['id'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded !== false) {
            $assignments[] = "window.cadespluginExtensionId = {$encoded};";
            $assignments[] = "window.cadesplugin_extension_id = {$encoded};";
        }
    }

    if (!empty($config['ids'])) {
        $encodedList = json_encode(array_values($config['ids']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encodedList !== false) {
            $assignments[] = "window.cadespluginExtensionIds = {$encodedList};";
        }
    }

    if (!$assignments) {
        return '';
    }

    return "<script>(function(){\n" . implode("\n", $assignments) . "\n})();</script>";
}

// ---------------------------------------------------------------
//  ЛОГИРОВАНИЕ
// ---------------------------------------------------------------
function writeLogSafe(string $path, string $message, string $channel): void
{
    static $failures = [];

    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
        if (empty($failures[$channel])) {
            error_log(sprintf('%s log directory is not writable: %s', $channel, $dir));
            $failures[$channel] = true;
        }
        return;
    }

    if (@file_put_contents($path, $message, FILE_APPEND) === false && empty($failures[$channel])) {
        error_log(sprintf('%s log file is not writable: %s', $channel, $path));
        $failures[$channel] = true;
    }
}

function nkLog(string $message): void
{
    $line = '[' . date('c') . "] $message\n";
    writeLogSafe(NK_LOG, $line, 'NK');
}

function msLog(string $message): void
{
    $line = '[' . date('c') . "] $message\n";
    writeLogSafe(MS_LOG, $line, 'MS');
}

function ordersLog(string $message): void
{
    $line = '[' . date('c') . "] $message\n";
    writeLogSafe(ORDERS_LOG, $line, 'ORDERS');
}

// ---------------------------------------------------------------
//  ХРАНЕНИЕ ТОКЕНА НК (Bearer из True API)
// ---------------------------------------------------------------
function nkGuessTokenExpiration(string $token): ?int
{
    $parts = explode('.', $token);
    if (count($parts) < 2) {
        return null;
    }

    $payload = strtr($parts[1], '-_', '+/');
    $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
    $decoded = base64_decode($payload, true);
    if ($decoded === false) {
        return null;
    }

    $data = json_decode($decoded, true);
    if (!is_array($data) || !isset($data['exp']) || !is_numeric($data['exp'])) {
        return null;
    }

    return (int)$data['exp'];
}

function nkStoreAuthToken(string $token, ?int $expiresAt = null): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    if ($expiresAt === null || $expiresAt <= time()) {
        $expiresAt = time() + NK_TOKEN_DEFAULT_TTL;
    }

    $expiresAt -= NK_TOKEN_EXP_SAFETY_MARGIN;
    if ($expiresAt <= time()) {
        $expiresAt = time() + NK_TOKEN_EXP_SAFETY_MARGIN;
    }

    $_SESSION[NK_TOKEN_SESSION_KEY] = [
        'token'      => $token,
        'expires_at' => $expiresAt,
    ];
}

function nkForgetAuthToken(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION[NK_TOKEN_SESSION_KEY]);
    }
}

function nkGetAuthTokenMeta(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }

    $data = $_SESSION[NK_TOKEN_SESSION_KEY] ?? null;
    if (!is_array($data) || empty($data['token'])) {
        return null;
    }

    $token = (string)$data['token'];
    $expiresAt = $data['expires_at'] ?? null;
    if (is_int($expiresAt) && $expiresAt <= time()) {
        unset($_SESSION[NK_TOKEN_SESSION_KEY]);
        return null;
    }

    return [
        'token'      => $token,
        'expires_at' => is_int($expiresAt) ? $expiresAt : null,
    ];
}

function nkGetAuthToken(): ?string
{
    $meta = nkGetAuthTokenMeta();
    return $meta['token'] ?? null;
}

// ---------------------------------------------------------------
//  HTTP-КЛИЕНТЫ
// ---------------------------------------------------------------
function curlRequest(string $method, string $uri, array $query = [], $body = null): array
{
    $method = strtoupper($method);

    $token = nkGetAuthToken();
    if ($token === null && NK_API_KEY !== '') {
        $query['apikey'] = NK_API_KEY;
    }

    $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $url = rtrim(NK_BASE_URL, '/') . $uri;
    if ($queryString !== '') {
        $url .= '?' . $queryString;
    }

    $logQuery = $query;
    unset($logQuery['apikey']);
    $logSuffix = $logQuery ? ' ' . http_build_query($logQuery, '', '&', PHP_QUERY_RFC3986) : '';
    nkLog(sprintf('%s %s%s%s', $method, $uri, $logSuffix, $token !== null ? ' (bearer)' : ''));

    if (function_exists('idn_to_ascii')) {
        $parts = parse_url($url);
        if ($parts !== false && isset($parts['host']) && preg_match('/[^\x20-\x7f]/', $parts['host'])) {
            $parts['host'] = idn_to_ascii($parts['host'], 0, INTL_IDNA_VARIANT_UTS46);
            $queryPart = isset($parts['query']) ? '?' . $parts['query'] : '';
            $url = "{$parts['scheme']}://{$parts['host']}{$parts['path']}{$queryPart}";
        }
    }

    $headers = [
        'Content-Type: application/json; charset=utf-8',
        'Accept: application/json;charset=utf-8',
        'Accept-Encoding: gzip',
    ];
    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $payload = null;
    if ($body !== null) {
        $payload = is_string($body)
            ? $body
            : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_ENCODING       => 'gzip',
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('CURL: ' . $error);
    }
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    nkLog('HTTP ' . $code . ($raw !== '' ? ': ' . substr($raw, 0, 200) : ''));
    if ($code >= 400) {
        throw new RuntimeException("HTTP $code: $raw");
    }

    if ($raw === '') {
        return [];
    }

    try {
        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('JSON decode error: ' . $e->getMessage());
    }
}

function trueApiRequest(string $method, string $uri, array $headers = [], $body = null): array
{
    $method = strtoupper($method);
    $url = rtrim(TRUE_API_BASE_URL, '/') . $uri;

    $payload = null;
    if ($body !== null) {
        $payload = is_string($body)
            ? $body
            : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    nkLog(sprintf('TrueAPI %s %s', $method, $uri));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => array_merge([
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8',
            'Accept-Encoding: gzip',
        ], $headers),
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_ENCODING       => 'gzip',
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('CURL: ' . $error);
    }
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    nkLog(sprintf('TrueAPI HTTP %d', $code));
    if ($code >= 400) {
        throw new RuntimeException("HTTP $code: $raw");
    }

    if ($raw === '') {
        return [];
    }

    try {
        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('True API JSON decode error: ' . $e->getMessage());
    }
}

function suzRequest(string $method, string $uri, array $headers = [], $body = null): array
{
    $method = strtoupper($method);
    $url = rtrim(SUZ_BASE_URL, '/') . $uri;

    $payload = null;
    if ($body !== null) {
        $payload = is_string($body)
            ? $body
            : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    ordersLog(sprintf('SUZ %s %s', $method, $uri));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => array_merge([
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8',
            'Accept-Encoding: gzip',
        ], $headers),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_ENCODING       => 'gzip',
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('CURL: ' . $error);
    }
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    ordersLog(sprintf('SUZ HTTP %d', $code));
    if ($code >= 400) {
        throw new RuntimeException("HTTP $code: $raw");
    }

    if ($raw === '') {
        return [];
    }

    try {
        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('SUZ JSON decode error: ' . $e->getMessage());
    }
}

function msRequest(string $method, string $uri, $body = null): array
{
    $method = strtoupper($method);
    $payload = $body === null
        ? null
        : (is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    msLog($method . ' ' . $uri . ($payload ? ' ' . substr($payload, 0, 200) : ''));

    $ch = curl_init(MS_BASE_URL . $uri);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json;charset=utf-8',
            'Authorization: Bearer ' . MS_TOKEN,
            'Accept-Encoding: gzip',
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_ENCODING       => 'gzip',
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('CURL: ' . $error);
    }
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    msLog('HTTP ' . $code . ($raw !== '' ? ': ' . substr($raw, 0, 200) : ''));
    if ($code >= 400) {
        throw new RuntimeException("HTTP $code: $raw");
    }

    if ($raw === '') {
        return [];
    }

    try {
        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('JSON decode error: ' . $e->getMessage());
    }
}
