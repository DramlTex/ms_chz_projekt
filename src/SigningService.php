<?php
declare(strict_types=1);

require_once __DIR__ . '/NkApi.php';

/**
 * Получает все карточки в статусе ожидания подписи
 * за указанный период (по умолчанию — последний год).
 *
 * @param string|null $fromDate Дата/время начала периода (Y-m-d H:i:s)
 * @param string|null $toDate   Дата/время окончания периода (Y-m-d H:i:s)
 *
 * @return array[]
 */
function nkFetchAwaitingDrafts(?string $fromDate = null, ?string $toDate = null): array
{
    $fromDate = $fromDate ?: date('Y-m-d H:i:s', strtotime('-1 year'));
    $toDate   = $toDate   ?: date('Y-m-d H:i:s');

    $batch  = 1000;
    $offset = 0;
    $all    = [];

    do {
        $chunk = NkApi::list([
            'from_date' => $fromDate,
            'to_date'   => $toDate,
        ], $batch, $offset);

        $all    = array_merge($all, $chunk);
        $offset += $batch;
    } while (count($chunk) === $batch);

    $normalize = static fn(string $v): string =>
        strtolower(preg_replace('/[^a-z0-9]/i', '', $v));

    $hasStatus = static function (array $row, string $status) use ($normalize): bool {
        foreach ((array)($row['good_detailed_status'] ?? []) as $item) {
            $item = $normalize((string)$item);
            if ($item === $status || str_contains($item, $status)) {
                return true;
            }
        }
        return false;
    };

    return array_values(array_filter(
        $all,
        static fn($row) => $hasStatus($row, 'notsigned') || $hasStatus($row, 'waitsign')
    ));
}
