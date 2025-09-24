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
        if ($gtin === '') return [];
        $resp = curlRequest(
            'GET',
            '/v3/product',
            ['gtin' => $gtin]
        );
        return $resp['result']['good'] ?? [];
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
}
