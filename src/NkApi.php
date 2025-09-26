<?php
declare(strict_types=1);

/**
 * NkApi.php
 * Обёртка над REST-API Национального каталога (НКМТ).
 *
 * Требует:  config/app.php — в котором объявлены:
 *   • NK_BASE_URL
 *   • NK_API_KEY
 *   • функция curlRequest($method, $uri, $query = [], $body = null)
 */
require_once __DIR__ . '/../config/app.php';

class NkApi
{
    /**
     * Нормализовать GTIN до формата, ожидаемого НК (14 цифр с лидирующим нулём).
     */
    public static function normalizeGtin(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === null) {
            return $raw;
        }

        $length = strlen($digits);
        if ($length === 14) {
            return $digits;
        }

        if ($length === 13) {
            return ($digits[0] ?? '') === '0'
                ? $digits
                : '0' . $digits;
        }

        return $raw;
    }

    /* ============================================================
       СПРАВОЧНЫЕ / ЧТЕНИЕ
       ============================================================ */

    /**
     * Короткий список карточек.
     * GET /v4/product-list
     *
     * @param array $extra   дополнительные query-параметры
     * @param int   $limit   макс. количество (≤ 1000)
     * @param int   $offset  смещение
     * @return array         массив goods
     */
    public static function list(array $extra = [], int $limit = 1000, int $offset = 0): array
    {
        $resp = curlRequest(
            'GET',
            '/v4/product-list',
            array_merge(['limit' => $limit, 'offset' => $offset], $extra)
        );
        return $resp['result']['goods'] ?? [];
    }

    /**
     * Массовый запрос подробных карточек.
     * GET /v3/feed-product
     *
     * @param int[] $ids  массив goodId (≤ 100)
     * @return array      массив goods, каждая содержит все поля + attributes
     */
    public static function feedProduct(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $resp = curlRequest(
            'GET',
            '/v3/feed-product',
            ['good_ids' => implode(';', $ids)]
        );

        /* сервер может вернуть result = [...] либо result = ['goods'=>[]] */
        if (isset($resp['result'][0])) {
            return $resp['result'];
        }

        if (isset($resp['result']['goods'])) {
            return $resp['result']['goods'];
        }

        return [];
    }

    /**
     * Получить подробную карточку по GTIN.
     * GET /v3/product
     *
     * @param string $gtin  идентификатор товара
     * @return array        карточка товара целиком
     */
    public static function cardByGtin(string $gtin): array
    {
        $raw = trim($gtin);
        if ($raw === '') {
            return [];
        }

        $candidates = [];
        $normalized = self::normalizeGtin($raw);
        if ($normalized !== '' && !in_array($normalized, $candidates, true)) {
            $candidates[] = $normalized;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if (is_string($digits) && $digits !== '' && !in_array($digits, $candidates, true)) {
            $candidates[] = $digits;
        }

        if (!in_array($raw, $candidates, true)) {
            $candidates[] = $raw;
        }

        $endpoints = [
            ['uri' => '/v3/product', 'label' => 'product'],
            ['uri' => '/v3/short-product', 'label' => 'short-product'],
        ];

        foreach ($candidates as $candidate) {
            foreach ($endpoints as $endpoint) {
                try {
                    nkLog(sprintf(
                        'cardByGtin candidate request: "%s" via %s',
                        $candidate,
                        $endpoint['label']
                    ));
                    $resp = curlRequest(
                        'GET',
                        $endpoint['uri'],
                        ['gtin' => $candidate]
                    );
                } catch (RuntimeException $e) {
                    if (strpos($e->getMessage(), 'HTTP 404') === 0) {
                        nkLog(sprintf(
                            'cardByGtin candidate "%s" via %s → HTTP 404',
                            $candidate,
                            $endpoint['label']
                        ));
                        continue;
                    }
                    throw $e;
                }

                $card = $resp['result']['good'] ?? [];
                if ($card) {
                    nkLog(sprintf(
                        'cardByGtin candidate "%s" via %s → success (goodId=%s)',
                        $candidate,
                        $endpoint['label'],
                        $card['good_id'] ?? '—'
                    ));
                    return $card;
                }

                nkLog(sprintf(
                    'cardByGtin candidate "%s" via %s → empty payload',
                    $candidate,
                    $endpoint['label']
                ));
            }
        }

        return [];
    }

    /* ============================================================
       ПОДПИСЬ / ПУБЛИКАЦИЯ
       ============================================================ */

    /**
     * Запрос XML для подписи.
     * POST /v3/feed-product-document
     *
     * @param int[] $goodIds
     * @return array  массив [{goodId, xml}]
     */
    public static function docsForSign(array $goodIds): array
    {
        $resp = curlRequest(
            'POST',
            '/v3/feed-product-document',
            [],
            ['goodIds' => $goodIds, 'publicationAgreement' => true]
        );
        return $resp['result']['xmls'] ?? [];
    }

    /**
     * Отправить откреплённую подпись (CAdES-BES / PKCS#7).
     * POST /v3/feed-product-sign-pkcs
     *
     * @param array $pack  [{goodId, base64Xml, signature}, …]
     * @return array       ['signed'=>[...], 'errors'=>[...]]
     */
    public static function sendSignPack(array $pack): array
    {
        $resp = curlRequest('POST', '/v3/feed-product-sign-pkcs', [], $pack);
        return $resp['result'] ?? [];
    }

    /**
     * Отправить прикреплённую (enveloped) подпись.
     * (Метод помечен как устаревший, но оставлен для совместимости.)
     * POST /v3/feed-product-sign
     *
     * @param array $pack  [{goodId, xml}, …]
     * @return array       ['signed'=>[...], 'errors'=>[...]]
     */
    public static function sendSignAttached(array $pack): array
    {
        $resp = curlRequest('POST', '/v3/feed-product-sign', [], $pack);
        return $resp['result'] ?? [];
    }

    /**
     * Проверка КИ/GTIN/ТНВЭД через mark-check.
     * POST /v3/mark-check
     *
     * @param array $criteria ['cis'=>[], 'gtins'=>[], 'tnveds'=>[]]
     * @return array
     */
    public static function markCheck(array $criteria): array
    {
        $payload = [];

        foreach (['cis', 'gtins', 'tnveds'] as $key) {
            if (empty($criteria[$key]) || !is_array($criteria[$key])) {
                continue;
            }
            $values = array_values(array_filter(array_map(static function ($value) {
                $text = trim((string)$value);
                return $text === '' ? null : $text;
            }, $criteria[$key])));
            if ($values) {
                $payload[$key] = $values;
            }
        }

        if (!$payload) {
            throw new InvalidArgumentException('Не переданы данные для mark-check');
        }

        return curlRequest('POST', '/v3/mark-check', [], $payload);
    }
}
