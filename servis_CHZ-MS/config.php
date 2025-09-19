<?php
// Общий часовой пояс. НК использует московское время, поэтому сразу задаём его
// для всех скриптов, работающих с датами и временем.
date_default_timezone_set('Europe/Moscow');
// ---------------------------------------------------------------
//  ОБЩИЕ НАСТРОЙКИ
// ---------------------------------------------------------------
define('NK_BASE_URL', 'https://апи.национальный-каталог.рф'); // prod-контур
define('NK_API_KEY',  't2bgetnng1hhe0gi');                  // !!!
define('NK_LOG', __DIR__ . '/nk_debug.log');

function nkLog(string $m): void {
    file_put_contents(NK_LOG, '['.date('c')."] $m\n", FILE_APPEND);
}
// Параметры подключения к "Моему складу"
define('MS_BASE_URL', 'https://api.moysklad.ru/api/remap/1.2');
define('MS_TOKEN',    '2fd9212e8b1d4d2a990e319265845f2e70b8cf52'); // Токен авторизации
define('MS_LOG', __DIR__ . '/ms_debug.log');

function msLog(string $m): void {
    file_put_contents(MS_LOG, '['.date('c')."] $m\n", FILE_APPEND);
}

define('TRUE_API_BASE_URL', 'https://markirovka.crpt.ru/api/v4/true-api');
define('TRUE_API_LOG', __DIR__ . '/true_api_debug.log');

function trueApiLog(string $m): void
{
    file_put_contents(TRUE_API_LOG, '[' . date('c') . "] $m\n", FILE_APPEND);
}
// ---------------------------------------------------------------
/**
 * Универсальный запрос к REST-API НК
 */
function curlRequest(string $method, string $uri, array $query = [], $body = null, array $options = []): array
{
    $bearerToken = '';
    if (isset($options['bearerToken'])) {
        $bearerToken = trim((string) $options['bearerToken']);
    }

    if ($bearerToken === '') {
        $query['apikey'] = NK_API_KEY;
    } else {
        $query['token'] = $bearerToken;
    }
    $url = NK_BASE_URL . $uri . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

    $q = $query;
    unset($q['apikey']);
    if ($bearerToken !== '') {
        $q['token'] = '***';
    }
    nkLog("$method $uri " . http_build_query($q, '', '&', PHP_QUERY_RFC3986));

    // — IDN → punycode —
    if (function_exists('idn_to_ascii')) {
        $p = parse_url($url);
        if (preg_match('/[^\x20-\x7f]/', $p['host'])) {
            $p['host'] = idn_to_ascii($p['host'], 0, INTL_IDNA_VARIANT_UTS46);
            $url = "{$p['scheme']}://{$p['host']}{$p['path']}?{$p['query']}";
        }
    }

    $ch = curl_init($url);
    $headers = [
        'Content-Type: application/json; charset=utf-8',
        'Accept: application/json;charset=utf-8',
        'Accept-Encoding: gzip',
    ];
    if ($bearerToken !== '') {
        $headers[] = 'Authorization: ' . (stripos($bearerToken, 'Bearer ') === 0 ? $bearerToken : 'Bearer ' . $bearerToken);
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $body ? json_encode($body, JSON_UNESCAPED_UNICODE) : null,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_ENCODING       => 'gzip',
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        throw new RuntimeException('CURL: '.curl_error($ch));
    }
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    nkLog("HTTP $code" . ($raw !== '' ? ': ' . substr($raw, 0, 200) : ''));
    if ($code >= 400) {
        throw new RuntimeException("HTTP $code: $raw");
    }
    return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Запрос к REST-API сервиса "Мой склад".
 */
function msRequest(string $method, string $uri, $body = null): array
{
    $bodyJson = $body ? json_encode($body, JSON_UNESCAPED_UNICODE) : null;
    msLog("$method $uri" . ($bodyJson ? ' ' . substr($bodyJson, 0, 200) : ''));
    $ch = curl_init(MS_BASE_URL . $uri);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json;charset=utf-8',
            'Authorization: Bearer ' . MS_TOKEN,
            'Accept-Encoding: gzip'
        ],
        CURLOPT_POSTFIELDS     => $body ? json_encode($body, JSON_UNESCAPED_UNICODE) : null,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_ENCODING       => 'gzip',
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        throw new RuntimeException('CURL: '.curl_error($ch));
    }
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    msLog("HTTP $code" . ($raw !== '' ? ': ' . substr($raw, 0, 200) : ''));
    if ($code >= 400) {
        throw new RuntimeException("HTTP $code: $raw");
    }
    return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
}

function trueApiRequest(string $method, string $uri, array $query = [], $body = null, array $options = []): array
{
    $url = TRUE_API_BASE_URL . $uri;
    if ($query) {
        $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    $logQuery = $query;
    trueApiLog("$method $uri" . ($logQuery ? ' ' . http_build_query($logQuery, '', '&', PHP_QUERY_RFC3986) : ''));

    $headers = [
        'Accept: */*',
        'Content-Type: application/json; charset=utf-8',
        'Accept-Encoding: gzip',
    ];

    $bearerToken = '';
    if (isset($options['bearerToken'])) {
        $bearerToken = trim((string) $options['bearerToken']);
    }
    if ($bearerToken !== '') {
        $headers[] = 'Authorization: ' . (stripos($bearerToken, 'Bearer ') === 0 ? $bearerToken : 'Bearer ' . $bearerToken);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $body ? json_encode($body, JSON_UNESCAPED_UNICODE) : null,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_ENCODING       => 'gzip',
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        throw new RuntimeException('CURL: ' . curl_error($ch));
    }
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    trueApiLog("HTTP $code" . ($raw !== '' ? ': ' . substr($raw, 0, 200) : ''));
    if ($code >= 400) {
        throw new RuntimeException("HTTP $code: $raw");
    }

    if ($raw === '') {
        return [];
    }

    return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
}
