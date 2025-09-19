<?php
declare(strict_types=1);

require_once __DIR__ . '/../NkApi.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Преобразует входную дату из формата YYYY-MM-DD или ISO в формат API НК.
 */
function normalizeDate(string $value, bool $isStart): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
        return $trimmed . ($isStart ? ' 00:00:00' : ' 23:59:59');
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/', $trimmed)) {
        return str_replace('T', ' ', $trimmed) . ':00';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/', $trimmed)) {
        return str_replace('T', ' ', $trimmed);
    }
    $timestamp = strtotime($trimmed);
    if ($timestamp === false) {
        throw new InvalidArgumentException('Неверный формат даты: ' . $value);
    }
    return date('Y-m-d H:i:s', $timestamp);
}

function getGtin(array $row): string
{
    if (!empty($row['gtin'])) {
        return (string) $row['gtin'];
    }
    foreach ($row['identified_by'] ?? [] as $identifier) {
        if (($identifier['type'] ?? '') === 'gtin' && !empty($identifier['value'])) {
            return (string) $identifier['value'];
        }
    }
    return '';
}

function getAttribute(array $detail, string $name): string
{
    foreach ($detail['good_attrs'] ?? [] as $attribute) {
        if (($attribute['attr_name'] ?? '') === $name) {
            return (string) ($attribute['attr_value'] ?? '');
        }
    }
    return '';
}

function detectCategory(array $good, array $detail): string
{
    $candidates = [
        $good['category'] ?? null,
        $good['category_name'] ?? null,
        $good['group_name'] ?? null,
        getAttribute($detail, 'Категория товара'),
        getAttribute($detail, 'Категория'),
        getAttribute($detail, 'Группа товаров'),
    ];
    foreach ($candidates as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }
    }
    return '';
}

function detectBrand(array $good, array $detail): string
{
    $candidates = [
        $good['brand_name'] ?? null,
        $good['brand'] ?? null,
        getAttribute($detail, 'Бренд'),
    ];
    foreach ($candidates as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }
    }
    return '';
}

function filterBySearch(array $goods, string $search): array
{
    if ($search === '') {
        return $goods;
    }
    $needle = mb_strtolower($search);
    return array_values(array_filter($goods, static function (array $row) use ($needle): bool {
        $haystack = [
            mb_strtolower((string) ($row['good_name'] ?? '')),
            mb_strtolower(getGtin($row)),
            mb_strtolower((string) ($row['brand_name'] ?? '')),
        ];
        foreach ($row['identified_by'] ?? [] as $identifier) {
            if (($identifier['type'] ?? '') === 'gtin' && !empty($identifier['value'])) {
                $haystack[] = mb_strtolower((string) $identifier['value']);
            }
        }
        $joined = trim(implode(' ', array_filter($haystack)));
        return $joined !== '' && str_contains($joined, $needle);
    }));
}

function filterByGroup(array $items, string $group): array
{
    if ($group === '') {
        return $items;
    }
    $map = [
        'clothes' => ['одежд'],
        'shoes' => ['обув'],
        'textile' => ['текст'],
        'perfume' => ['парфюм'],
        'photo' => ['фото'],
    ];
    $needles = $map[$group] ?? null;
    if (!$needles) {
        return $items;
    }
    return array_values(array_filter($items, static function (array $item) use ($needles): bool {
        $category = mb_strtolower((string) ($item['category'] ?? ''));
        if ($category === '') {
            return false;
        }
        foreach ($needles as $needle) {
            if (str_contains($category, $needle)) {
                return true;
            }
        }
        return false;
    }));
}

try {
    $limit = max(1, min((int) ($_GET['limit'] ?? 200), 500));
    $offset = max(0, (int) ($_GET['offset'] ?? 0));
    $fromParam = trim((string) ($_GET['from'] ?? ''));
    $toParam = trim((string) ($_GET['to'] ?? ''));
    $search = trim((string) ($_GET['search'] ?? ''));
    $group = trim((string) ($_GET['group'] ?? ''));

    $query = [];
    if ($fromParam !== '') {
        $query['from_date'] = normalizeDate($fromParam, true);
    }
    if ($toParam !== '') {
        $query['to_date'] = normalizeDate($toParam, false);
    }

    $goods = NkApi::list($query, $limit, $offset);
    $totalFetched = count($goods);

    $goods = filterBySearch($goods, $search);

    $ids = array_column($goods, 'good_id');
    $details = [];
    foreach (array_chunk($ids, 25) as $chunk) {
        if (!$chunk) {
            continue;
        }
        foreach (NkApi::feedProduct($chunk) as $detail) {
            $details[$detail['good_id']] = $detail;
        }
    }

    $items = [];
    foreach ($goods as $row) {
        $goodId = (int) ($row['good_id'] ?? 0);
        $detail = $details[$goodId] ?? [];
        $items[] = [
            'id' => $goodId,
            'gtin' => getGtin($row),
            'name' => (string) ($row['good_name'] ?? ''),
            'brand' => detectBrand($row, $detail),
            'category' => detectCategory($row, $detail),
            'size' => getAttribute($detail, 'Размер одежды / изделия'),
            'color' => getAttribute($detail, 'Цвет'),
            'tnved' => getAttribute($detail, 'Код ТНВЭД'),
            'article' => getAttribute($detail, 'Модель / артикул производителя'),
            'status' => (string) ($row['good_status'] ?? ''),
            'updatedAt' => $row['updated_at'] ?? $row['last_update'] ?? $row['to_date'] ?? null,
        ];
    }

    $items = filterByGroup($items, $group);

    echo json_encode([
        'items' => $items,
        'meta' => [
            'total' => $totalFetched,
            'count' => count($items),
            'limit' => $limit,
            'offset' => $offset,
            'search' => $search !== '' ? $search : null,
            'group' => $group !== '' ? $group : null,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
