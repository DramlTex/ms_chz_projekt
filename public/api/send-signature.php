<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
header('Content-Type: application/json; charset=utf-8');

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload || !isset($payload['signPack'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad JSON'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* --- формируем URL и переводим IDN -> Punycode --- */
$url = NK_BASE_URL . '/v3/feed-product-sign-pkcs?apikey=' . NK_API_KEY;

if (function_exists('idn_to_ascii')) {
    $p = parse_url($url);
    if (preg_match('/[^\x20-\x7f]/', $p['host'])) {          // есть не-ASCII
        $p['host'] = idn_to_ascii($p['host'], 0, INTL_IDNA_VARIANT_UTS46);
        $url = "{$p['scheme']}://{$p['host']}{$p['path']}?{$p['query']}";
    }
}

/* --- отправляем запрос --- */
$bodyJson = json_encode($payload['signPack'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => $bodyJson,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_CONNECTTIMEOUT => 5
]);

$raw  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
if ($raw === false) {
    http_response_code(500);
    echo json_encode(['error' => 'CURL: ' . curl_error($ch)], JSON_UNESCAPED_UNICODE);
    curl_close($ch);
    exit;
}
curl_close($ch);

http_response_code($code);
echo $raw;                         // API уже вернёт JSON
