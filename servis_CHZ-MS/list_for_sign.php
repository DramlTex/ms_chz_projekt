<?php
/**
 * list_for_sign.php
 * Возвращает JSON‑массив черновых карточек, ожидающих подписи
 *   [
 *     { "goodId": 123, "name": "Толстовка XL" },
 *     …
 *   ]
 */
require_once 'NkApi.php';

header('Content-Type: application/json; charset=utf-8');

try {
    /* ────────────────────── 1. диапазон дат ──────────────────────── */
    $fromDate = date('Y-m-d H:i:s', strtotime('-1 year'));
    $toDate   = date('Y-m-d H:i:s');

    /* ───────────────────── 2. собираем все товары ─────────────────── */
    $batch  = 1000;          // шаг пагинации
    $offset = 0;
    $all    = [];

    do {
        $chunk = NkApi::list(
            [
                'from_date' => $fromDate,
                'to_date'   => $toDate
                // good_status НЕ передаём — отфильтруем позже
            ],
            $batch,
            $offset
        );

        $all    = array_merge($all, $chunk);
        $offset += $batch;
    } while (count($chunk) === $batch);

    /* ───────────────── 3. фильтруем «ждущие подписи» ──────────────── */
    $normalize = fn(string $v): string =>
        strtolower(preg_replace('/[^a-z0-9]/i', '', $v));

    $hasStatus = static function (array $row, string $status) use ($normalize): bool {
        foreach ((array)($row['good_detailed_status'] ?? []) as $item) {
            $item = $normalize($item);
            if ($item === $status || str_contains($item, $status)) {
                return true;
            }
        }
        return false;
    };

    $need = array_filter(
        $all,
        fn($r) => $hasStatus($r, 'notsigned') || $hasStatus($r, 'waitsign')
    );

    /* ────────────────── 4. готовим ответ фронту ───────────────────── */
    $out = array_map(
        fn($r) => [
            'goodId' => $r['good_id'],
            'name'   => $r['good_name'] ?? ''
        ],
        array_values($need)
    );

    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
