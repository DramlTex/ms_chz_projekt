<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../bootstrap.php';

$itemsPerPage = 50;
$page        = max(1, (int)($_GET['page'] ?? 1));
$search      = trim((string)($_GET['q'] ?? ''));
$fromRaw     = trim((string)($_GET['from'] ?? ''));
$toRaw       = trim((string)($_GET['to']   ?? ''));

$normalizeDate = static function (string $value): string {
    if ($value === '') {
        return '';
    }
    $value = str_replace('T', ' ', $value);
    if (strlen($value) === 16) {
        $value .= ':00';
    }
    return $value;
};

$fromDate = $normalizeDate($fromRaw);
$toDate   = $normalizeDate($toRaw);
if ($toDate === '') {
    $toDate = date('Y-m-d H:i:s');
}

$params = [];
if ($fromDate !== '') {
    $params['from_date'] = $fromDate;
}
if ($toDate !== '') {
    $params['to_date'] = $toDate;
}

$nkAuthActive = nkGetAuthTokenMeta() !== null;
$cryptoProBootstrap = renderCryptoProExtensionBootstrap();

$all    = [];
$total  = 0;
$pages  = 1;
$cards  = [];
$hasMore = false;
$fulls  = [];

if ($nkAuthActive) {
    $batch  = 1000;
    $offset = 0;

    do {
        $chunk = NkApi::list($params, $batch, $offset);
        $all   = array_merge($all, $chunk);
        $offset += $batch;
    } while (count($chunk) === $batch);

    if ($search !== '') {
        $needle = mb_strtolower($search);
        $all = array_values(array_filter($all, static function (array $row) use ($needle): bool {
            $name = mb_strtolower((string)($row['good_name'] ?? ''));
            $code = strtolower(gtin($row));
            return mb_stripos($name, $needle) !== false || str_contains($code, $needle);
        }));
    }

    $total   = count($all);
    $pages   = max(1, (int)ceil($total / $itemsPerPage));
    $page    = min($page, $pages);
    $cards   = array_slice($all, ($page - 1) * $itemsPerPage, $itemsPerPage);
    $hasMore = $page < $pages;

    $ids = array_filter(array_column($cards, 'good_id'));
    foreach (array_chunk($ids, 25) as $chunk) {
        foreach (NkApi::feedProduct($chunk) as $good) {
            $fulls[$good['good_id']] = $good;
        }
    }
} else {
    $page = 1;
}

function attr(array $card, string $name): string
{
    foreach ($card['good_attrs'] ?? [] as $attr) {
        if (($attr['attr_name'] ?? '') === $name) {
            return (string)($attr['attr_value'] ?? '');
        }
    }
    return '';
}

function gtin(array $row): string
{
    if (!empty($row['gtin'])) {
        return (string)$row['gtin'];
    }
    foreach ($row['identified_by'] ?? [] as $id) {
        if (($id['type'] ?? '') === 'gtin') {
            return (string)($id['value'] ?? '');
        }
    }
    return '';
}

function esc(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Карточки НК</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        :root {
            color-scheme: light;
            --bg-page: linear-gradient(135deg, #eef2f3, #e3ebf6);
            --bg-card: rgba(255, 255, 255, 0.96);
            --bg-table-header: linear-gradient(180deg, #fbfbfd, #e9edf5);
            --border-color: #d7dce5;
            --accent: #4364d8;
            --accent-dark: #3653bd;
            --danger: #b42318;
            --warning: #b76b00;
            --success: #157f2f;
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 2.5rem;
            background: var(--bg-page);
            color: #1f2937;
        }

        .page {
            max-width: 1400px;
            margin: 0 auto;
            background: var(--bg-card);
            border-radius: 16px;
            box-shadow: 0 25px 60px rgba(15, 23, 42, 0.12);
            padding: 2.5rem 3rem;
        }

        .page__header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
        }

        .page__actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page__actions .button {
            white-space: nowrap;
        }

        h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }

        .page__meta {
            margin: 0.25rem 0 0;
            color: #4b5563;
            font-size: 0.95rem;
        }

        .filters {
            margin-top: 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filters label {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            font-size: 0.85rem;
            color: #4b5563;
        }

        .filters input[type="text"],
        .filters input[type="datetime-local"] {
            padding: 0.6rem 0.75rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 0.95rem;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.55rem 1.1rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            transition: transform 0.15s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            box-shadow: none;
        }

        .button--primary {
            color: #fff;
            background: linear-gradient(135deg, var(--accent), #4c6be2);
            box-shadow: 0 10px 25px rgba(67, 100, 216, 0.35);
        }

        .button--primary:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--accent-dark), #425ed0);
            transform: translateY(-1px);
        }

        .button--secondary {
            background: #e5e7eb;
            color: #1f2937;
        }

        .button--secondary:hover:not(:disabled) {
            background: #d1d5db;
        }

        .button--ghost {
            background: transparent;
            color: var(--accent-dark);
        }

        .button--small {
            padding: 0.4rem 0.75rem;
            font-size: 0.85rem;
        }

        .table-wrap {
            margin-top: 2rem;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: var(--bg-table-header);
            text-align: left;
            font-weight: 600;
            font-size: 0.85rem;
            color: #374151;
            padding: 0.75rem 0.85rem;
        }

        tbody tr {
            border-top: 1px solid var(--border-color);
        }

        tbody tr:nth-child(even) {
            background: rgba(244, 246, 250, 0.65);
        }

        tbody tr.needs-sign {
            box-shadow: inset 4px 0 0 #fbbf24;
        }

        tbody td {
            vertical-align: top;
            padding: 0.9rem 0.85rem;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .col-select {
            width: 44px;
        }

        .card-header {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: #eef2ff;
            color: #3730a3;
            border-radius: 999px;
            padding: 0.25rem 0.65rem;
        }

        .pill--gtin {
            background: #ecfdf5;
            color: #047857;
        }

        .pill--name {
            background: transparent;
            color: inherit;
            font-weight: 500;
            padding: 0;
            text-transform: none;
            letter-spacing: normal;
        }

        .card-name {
            font-size: 1.05rem;
            font-weight: 600;
            color: #111827;
        }

        .attr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.5rem 1.25rem;
        }

        .attr-grid dt {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
        }

        .attr-grid dd {
            margin: 0.2rem 0 0;
            font-weight: 500;
            color: #1f2937;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.75rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            background: #e5e7eb;
            color: #374151;
        }

        .status-draft { color: #6b7280; }
        .status-notsigned,
        .status-waitSign { color: var(--warning); }
        .status-published { color: var(--success); }

        .status-list {
            margin: 0.75rem 0 0;
            padding-left: 1.1rem;
            color: #4b5563;
            font-size: 0.85rem;
        }

        .actions {
            margin-top: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: space-between;
            align-items: center;
        }

        .pagination {
            margin-top: 1.5rem;
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 0.4rem 0.7rem;
            border-radius: 6px;
            border: 1px solid transparent;
            font-size: 0.85rem;
            color: #1f2937;
            text-decoration: none;
        }

        .pagination a:hover {
            border-color: var(--accent);
            color: var(--accent-dark);
        }

        .pagination .current {
            background: var(--accent);
            color: #fff;
        }

        .auth-alert {
            margin-top: 1.5rem;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            border: 1px solid rgba(183, 107, 0, 0.35);
            background: #fff7ed;
            color: #92400e;
            line-height: 1.5;
        }

        .auth-alert strong {
            display: block;
            margin-bottom: 0.35rem;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.45);
            z-index: 30;
            padding: 1.5rem;
        }

        .modal-overlay.is-visible {
            display: flex;
        }

        .modal-window {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.25);
            max-width: 540px;
            width: 100%;
            padding: 1.5rem;
            position: relative;
        }

        .modal-window--wide {
            max-width: 720px;
        }

        .nk-auth-panel {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .nk-auth-panel__header {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .nk-auth-panel__header h2 {
            margin: 0;
            font-size: 1.25rem;
        }

        .nk-auth-panel__intro {
            margin: 0;
            color: #4b5563;
            font-size: 0.9rem;
        }

        .nk-auth-panel__content {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .modal-close {
            position: absolute;
            top: 0.65rem;
            right: 0.65rem;
            border: none;
            background: transparent;
            font-size: 1.4rem;
            cursor: pointer;
            color: #6b7280;
        }

        #modal-body {
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .sign-panel {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .sign-panel__header {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .sign-panel__header h2 {
            margin: 0;
            font-size: 1.35rem;
        }

        .sign-panel__intro {
            margin: 0;
            color: #6b7280;
            font-size: 0.9rem;
        }

        .sign-steps {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .sign-step {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: #f9fafb;
        }

        .sign-step__header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sign-step__badge {
            width: 2.1rem;
            height: 2.1rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--accent);
            color: #fff;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .sign-step__title {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #111827;
        }

        .sign-step__hint {
            margin: 0;
            color: #4b5563;
            font-size: 0.9rem;
        }

        .sign-step__content {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .sign-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            width: 100%;
        }

        .sign-controls select {
            flex: 1 0 240px;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 0.95rem;
            width: 100%;
            max-width: 100%;
            min-width: 0;
        }

        .sign-controls select option {
            white-space: normal;
            word-break: break-word;
        }

        .sign-controls--cert {
            flex: 1 1 320px;
            min-width: 0;
        }

        .sign-controls--cert select {
            flex: 1 1 100%;
        }

        .nk-auth-block {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: flex-start;
            justify-content: flex-start;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            background: #eef2ff;
            border: 1px solid rgba(67, 100, 216, 0.25);
        }

        .nk-auth-block > * {
            min-width: 0;
        }

        .nk-auth-block__info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            min-width: 200px;
        }

        .nk-auth-block__title {
            margin: 0;
            font-weight: 600;
            font-size: 0.95rem;
            color: #1f2937;
        }

        .nk-auth-block__status {
            margin: 0;
            font-size: 0.85rem;
            color: #4b5563;
        }

        .nk-auth-block__status--active {
            color: var(--success);
            font-weight: 600;
        }

        .nk-auth-block__actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .nk-auth-block .button {
            flex: 0 0 auto;
        }

        #signLog {
            background: #0f172a;
            color: #f8fafc;
            font-family: "SFMono-Regular", Consolas, monospace;
            font-size: 0.82rem;
            padding: 1rem;
            border-radius: 8px;
            max-height: 240px;
            overflow-y: auto;
            white-space: pre-wrap;
        }

        .sign-selection {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            padding: 0.65rem;
            border-radius: 8px;
            background: #fff;
            border: 1px dashed rgba(67, 100, 216, 0.35);
            min-height: 2.5rem;
        }

        .sign-selection .pill {
            background: #e0e7ff;
            color: #3730a3;
        }

        .sign-selection__empty {
            margin: 0;
            color: #6b7280;
            font-size: 0.85rem;
        }

        .awaiting-list {
            max-height: 180px;
            overflow-y: auto;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 0.75rem;
            background: #fff;
            font-size: 0.85rem;
        }

        .awaiting-list ul {
            margin: 0;
            padding-left: 1.1rem;
        }

        .sign-cert-info {
            border-radius: 8px;
            border: 1px dashed rgba(67, 100, 216, 0.35);
            background: #eef2ff;
            padding: 0.75rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            font-size: 0.85rem;
            color: #1f2937;
        }

        .sign-cert-info__title {
            margin: 0;
            font-weight: 600;
        }

        .sign-cert-info__meta {
            margin: 0;
            color: #4b5563;
        }

        @media (max-width: 1024px) {
            body { padding: 1.5rem; }
            .page { padding: 1.75rem; }
            thead th:nth-child(3),
            tbody td:nth-child(3) {
                min-width: 320px;
            }
        }

        @media (max-width: 768px) {
            .page { padding: 1.5rem; }
            .page__header { flex-direction: column; align-items: stretch; }
            .page__actions { width: 100%; justify-content: flex-start; flex-wrap: wrap; }
            .table-wrap { overflow-x: auto; }
            table { min-width: 640px; }
        }
    </style>
    <?php if ($cryptoProBootstrap !== '') {
        echo $cryptoProBootstrap, "\n";
    } ?>
    <script src="assets/js/cadesplugin_api.js"></script>
</head>
<body>
<main class="page">
    <header class="page__header">
        <div>
            <h1>Карточки Национального каталога</h1>
            <p class="page__meta">Всего карточек: <?= $total ?> • Страница <?= $page ?> из <?= $pages ?></p>
        </div>
        <div class="page__actions">
            <button type="button" class="button button--ghost" id="openNkAuthModal">Авторизация</button>
            <button type="button" class="button button--primary" id="openSignModal">Подписание карточек</button>
        </div>
    </header>

    <?php if (!$nkAuthActive): ?>
    <div class="auth-alert" role="alert">
        <strong>Требуется авторизация через True API.</strong>
        <p>Перед загрузкой карточек получите bearer-токен НК: нажмите «Авторизация» и выполните подпись challenge сертификатом CryptoPro.</p>
    </div>
    <?php endif; ?>

    <form method="get" class="filters">
        <label>
            Поиск по названию или GTIN
            <input type="text" name="q" placeholder="Например, 4601234" value="<?= esc($search) ?>">
        </label>
        <label>
            Дата создания с
            <input type="datetime-local" name="from" value="<?= esc($fromRaw) ?>">
        </label>
        <label>
            Дата создания до
            <input type="datetime-local" name="to" value="<?= esc($toRaw) ?>">
        </label>
        <div>
            <button type="submit" class="button button--secondary">Применить фильтр</button>
        </div>
    </form>

    <div class="table-wrap">
        <table class="cards-table">
            <thead>
            <tr>
                <th class="col-select"><input type="checkbox" id="selectAll"></th>
                <th>Карточка</th>
                <th>Атрибуты</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($cards as $row):
                $id   = (string)$row['good_id'];
                $full = $fulls[$id] ?? [];

                $tnved = attr($full, 'Код ТНВЭД');
                $article = attr($full, 'Модель / артикул производителя');
                $color = attr($full, 'Цвет');
                $size = attr($full, 'Размер одежды / изделия');
                $trts = attr($full, 'Номер технического регламента');
                $decl = attr($full, 'Декларация о соответствии');

                $gtin = gtin($row);
                $status = (string)($row['good_status'] ?? '');
                $detailed = array_filter((array)($row['good_detailed_status'] ?? []));
                $statusClass = [
                    'draft' => 'status-draft',
                    'notsigned' => 'status-notsigned',
                    'waitsign' => 'status-waitSign',
                    'published' => 'status-published',
                ][$status] ?? '';
            ?>
            <tr class="<?= $statusClass ?>"
                data-id="<?= esc($id) ?>"
                data-gtin="<?= esc($gtin) ?>"
                data-name="<?= esc($row['good_name'] ?? '') ?>"
                data-tnved="<?= esc($tnved) ?>"
                data-article="<?= esc($article) ?>">
                <td class="col-select"><input type="checkbox" class="select-item"></td>
                <td>
                    <div class="card-header">
                        <span class="pill">ID <?= esc($id) ?></span>
                        <span class="pill pill--gtin">GTIN <?= esc($gtin ?: '—') ?></span>
                    </div>
                    <div class="card-name"><?= esc($row['good_name'] ?? '—') ?></div>
                </td>
                <td>
                    <dl class="attr-grid">
                        <div><dt>ТНВЭД-10</dt><dd><?= esc($tnved ?: '—') ?></dd></div>
                        <div><dt>Артикул</dt><dd><?= esc($article ?: '—') ?></dd></div>
                        <div><dt>Цвет</dt><dd><?= esc($color ?: '—') ?></dd></div>
                        <div><dt>Размер</dt><dd><?= esc($size ?: '—') ?></dd></div>
                        <div><dt>ТР ТС</dt><dd><?= esc($trts ?: '—') ?></dd></div>
                        <div><dt>Декларация</dt><dd><?= esc($decl ?: '—') ?></dd></div>
                    </dl>
                </td>
                <td>
                    <span class="status-badge <?= esc($statusClass) ?>"><?= esc($status ?: '—') ?></span>
                    <?php if ($detailed): ?>
                        <ul class="status-list">
                            <?php foreach ($detailed as $item): ?>
                                <li><?= esc($item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="button button--primary button--small create-single" type="button">Создать</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="actions">
        <button type="button" class="button button--primary" id="createSelected">Создать выбранные</button>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(['page' => $page - 1, 'q' => $search, 'from' => $fromRaw, 'to' => $toRaw]) ?>">&laquo; Назад</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?<?= http_build_query(['page' => $i, 'q' => $search, 'from' => $fromRaw, 'to' => $toRaw]) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($hasMore): ?>
                <a href="?<?= http_build_query(['page' => $page + 1, 'q' => $search, 'from' => $fromRaw, 'to' => $toRaw]) ?>">Вперёд &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
</main>

<div id="modal" class="modal-overlay" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-window">
        <button type="button" class="modal-close" aria-label="Закрыть уведомление">&times;</button>
        <div id="modal-body"></div>
    </div>
</div>

<div id="nkAuthModal" class="modal-overlay" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-window">
        <button type="button" class="modal-close" aria-label="Закрыть окно авторизации">&times;</button>
        <section class="nk-auth-panel">
            <header class="nk-auth-panel__header">
                <h2>Авторизация Национального каталога</h2>
                <p class="nk-auth-panel__intro">Получите bearer-токен через True API, чтобы загружать карточки и выполнять подпись.</p>
            </header>
            <div class="nk-auth-panel__content">
                <div class="nk-auth-block" id="nkAuthBlock">
                    <div class="nk-auth-block__info">
                        <p class="nk-auth-block__title">Авторизация Национального каталога</p>
                        <p class="nk-auth-block__status" id="nkAuthStatus">Токен не получен.</p>
                    </div>
                    <div class="sign-controls sign-controls--cert">
                        <select id="nkAuthCert">
                            <option value="">Загрузка сертификатов…</option>
                        </select>
                    </div>
                    <div class="sign-cert-info" id="nkAuthCertInfo">
                        <p class="sign-cert-info__title">Сертификат не выбран.</p>
                        <p class="sign-cert-info__meta">Выберите сертификат, чтобы получить токен для владельца.</p>
                    </div>
                    <div class="nk-auth-block__actions">
                        <button type="button" class="button button--ghost" id="nkAuthBtn">Получить токен</button>
                        <button type="button" class="button button--ghost" id="nkAuthResetBtn">Сбросить токен</button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<div id="signModal" class="modal-overlay" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-window modal-window--wide">
        <button type="button" class="modal-close" aria-label="Закрыть окно подписи">&times;</button>
        <section class="sign-panel">
            <header class="sign-panel__header">
                <h2>Подписание карточек</h2>
                <p class="sign-panel__intro">Выберите сертификат, подготовьте карточки и отправьте подписи в Национальный каталог.</p>
            </header>
            <div class="sign-steps">
                <article class="sign-step">
                    <div class="sign-step__header">
                        <span class="sign-step__badge">1</span>
                        <h3 class="sign-step__title">Выберите сертификат для подписи</h3>
                    </div>
                    <p class="sign-step__hint">Поддерживаются действующие сертификаты CryptoPro с правами подписи.</p>
                    <div class="sign-step__content">
                        <div class="sign-controls sign-controls--cert">
                            <select id="signCert">
                                <option value="">Загрузка сертификатов…</option>
                            </select>
                        </div>
                        <div class="sign-cert-info" id="signCertInfo">
                            <p class="sign-cert-info__title">Сертификат не выбран.</p>
                            <p class="sign-cert-info__meta">Выберите сертификат, чтобы посмотреть сведения о владельце и сроке действия.</p>
                        </div>
                    </div>
                </article>
                <article class="sign-step">
                    <div class="sign-step__header">
                        <span class="sign-step__badge">2</span>
                        <h3 class="sign-step__title">Подготовьте карточки к подписи</h3>
                    </div>
                    <p class="sign-step__hint">Отметьте карточки в таблице или автоматически выберите те, что ожидают подписи.</p>
                    <div class="sign-step__content">
                        <div class="sign-selection" id="signSelection" aria-live="polite">
                            <p class="sign-selection__empty">Нет выбранных карточек.</p>
                        </div>
                        <div class="awaiting-list" id="awaitingList" hidden></div>
                        <div class="sign-controls sign-controls--actions">
                            <button type="button" class="button button--secondary" id="loadAwaiting">Отметить ожидающие подписи</button>
                            <button type="button" class="button button--ghost" id="refreshAwaitingList">Обновить список</button>
                        </div>
                    </div>
                </article>
            </div>
            <button type="button" class="button button--primary" id="signSelectedBtn">Подписать выбранные</button>
            <div id="signLog">Инициализация CryptoPro…</div>
        </section>
    </div>
</div>

<script>
(() => {
  const nkAuthActive = <?php echo $nkAuthActive ? 'true' : 'false'; ?>;
  const modal = document.getElementById('modal');
  const modalBody = document.getElementById('modal-body');
  const modalClose = modal.querySelector('.modal-close');
  const signModal = document.getElementById('signModal');
  const signClose = signModal.querySelector('.modal-close');
  const nkAuthModal = document.getElementById('nkAuthModal');
  const nkAuthClose = nkAuthModal ? nkAuthModal.querySelector('.modal-close') : null;
  const openNkAuthButton = document.getElementById('openNkAuthModal');
  const openSignButton = document.getElementById('openSignModal');
  const awaitingContainer = document.getElementById('awaitingList');
  const selectionInfo = document.getElementById('signSelection');

  function showModal(html) {
    modalBody.innerHTML = html;
    modal.classList.add('is-visible');
    modal.setAttribute('aria-hidden', 'false');
  }

  function hideModal() {
    modal.classList.remove('is-visible');
    modal.setAttribute('aria-hidden', 'true');
  }

  function showNkAuthModal() {
    if (!nkAuthModal) return;
    nkAuthModal.classList.add('is-visible');
    nkAuthModal.setAttribute('aria-hidden', 'false');
    refreshNkAuthStatus(false).catch(() => {});
  }

  function hideNkAuthModal() {
    if (!nkAuthModal) return;
    nkAuthModal.classList.remove('is-visible');
    nkAuthModal.setAttribute('aria-hidden', 'true');
  }

  function showSignModal() {
    signModal.classList.add('is-visible');
    signModal.setAttribute('aria-hidden', 'false');
    updateSelectionInfo();
    refreshNkAuthStatus(false).catch(() => {});
  }

  function hideSignModal() {
    signModal.classList.remove('is-visible');
    signModal.setAttribute('aria-hidden', 'true');
  }

  modalClose.addEventListener('click', hideModal);
  modal.addEventListener('click', (event) => {
    if (event.target === modal) hideModal();
  });

  signClose.addEventListener('click', hideSignModal);
  signModal.addEventListener('click', (event) => {
    if (event.target === signModal) hideSignModal();
  });

  if (nkAuthClose) {
    nkAuthClose.addEventListener('click', hideNkAuthModal);
  }

  if (nkAuthModal) {
    nkAuthModal.addEventListener('click', (event) => {
      if (event.target === nkAuthModal) hideNkAuthModal();
    });
  }

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      hideModal();
      hideSignModal();
      hideNkAuthModal();
    }
  });

  if (openNkAuthButton) {
    openNkAuthButton.addEventListener('click', () => {
      showNkAuthModal();
    });
  }

  if (openSignButton) {
    openSignButton.addEventListener('click', () => {
      showSignModal();
      refreshAwaiting({ log: false, autoSelect: true, updateList: true }).catch(() => {});
    });
  }

  async function sendCreate(product) {
    const fd = new FormData();
    Object.entries(product).forEach(([key, value]) => fd.append(key, value ?? ''));
    const response = await fetch('api/create-product.php', { method: 'POST', body: fd });
    const text = await response.text();
    if (!response.ok) {
      throw new Error(text);
    }
    return JSON.parse(text);
  }

  document.querySelectorAll('.create-single').forEach((button) => {
    button.addEventListener('click', async (event) => {
      event.preventDefault();
      const row = button.closest('tr');
      if (!row) return;
      const product = {
        gtin: row.dataset.gtin,
        name: row.dataset.name,
        tnved: row.dataset.tnved,
        article: row.dataset.article,
      };
      showModal('<strong>Создание товара…</strong>');
      try {
        const result = await sendCreate(product);
        showModal(result.status === 'ok' ? '✅ Товар создан' : 'Ошибка: ' + (result.error || ''));
      } catch (error) {
        showModal('Ошибка: ' + (error.message || error));
      }
      setTimeout(hideModal, 2200);
    });
  });

  const selectAll = document.getElementById('selectAll');
  const getItemCheckboxes = () => Array.from(document.querySelectorAll('.select-item'));
  const getSelectedRows = () => getItemCheckboxes()
    .filter((checkbox) => checkbox.checked)
    .map((checkbox) => checkbox.closest('tr'))
    .filter(Boolean);

  function updateSelectionInfo() {
    if (!selectionInfo) return;
    const rows = getSelectedRows();
    if (!rows.length) {
      selectionInfo.innerHTML = '<p class="sign-selection__empty">Нет выбранных карточек.</p>';
      return;
    }
    const items = rows.slice(0, 12).map((row) => {
      const name = row.dataset.name || 'Без названия';
      const gtin = row.dataset.gtin || row.dataset.id || '';
      return `<span class="pill">${gtin ? gtin + ' · ' : ''}${name}</span>`;
    }).join('');
    const extra = rows.length > 12 ? `<span class="pill">+${rows.length - 12}</span>` : '';
    selectionInfo.innerHTML = items + extra;
  }

  function updateSelectAllState() {
    const boxes = getItemCheckboxes();
    const total = boxes.length;
    const checked = boxes.filter((checkbox) => checkbox.checked).length;
    if (!selectAll) return;
    selectAll.checked = total > 0 && checked === total;
    selectAll.indeterminate = checked > 0 && checked < total;
    updateSelectionInfo();
  }

  if (selectAll) {
    selectAll.addEventListener('change', (event) => {
      getItemCheckboxes().forEach((checkbox) => {
        checkbox.checked = event.target.checked;
      });
      updateSelectAllState();
    });
  }

  getItemCheckboxes().forEach((checkbox) => {
    checkbox.addEventListener('change', updateSelectAllState);
  });
  updateSelectAllState();

  const createSelectedBtn = document.getElementById('createSelected');
  if (createSelectedBtn) {
    createSelectedBtn.addEventListener('click', () => {
      const rows = getSelectedRows();
      if (!rows.length) {
        showModal('Нет выбранных карточек');
        setTimeout(hideModal, 1800);
        return;
      }
      const list = rows
        .map((row) => `${row.dataset.gtin || row.dataset.id || ''} ${row.dataset.name || ''}`.trim())
        .join('<br>');
      showModal(`<div style="max-height:320px;overflow:auto">${list}</div><div style="margin-top:1rem;display:flex;gap:0.75rem;justify-content:flex-end"><button type="button" class="button button--secondary" id="confirmMass">Подтвердить</button></div>`);
      const confirmBtn = document.getElementById('confirmMass');
      if (!confirmBtn) return;
      confirmBtn.addEventListener('click', async () => {
        showModal('<strong>Создание товаров…</strong>');
        const items = rows.map((row) => ({
          gtin: row.dataset.gtin,
          name: row.dataset.name,
          tnved: row.dataset.tnved,
          article: row.dataset.article,
        }));
        try {
          const response = await fetch('api/create-products.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items }),
          });
          const text = await response.text();
          if (!response.ok) throw new Error(text);
          const data = JSON.parse(text);
          const out = data.results.map((result) => (
            result.status === 'ok'
              ? `✅ ${result.gtin}`
              : `❌ ${result.gtin}: ${result.error}`
          )).join('<br>');
          showModal(out);
        } catch (error) {
          showModal('Ошибка: ' + (error.message || error));
        }
      }, { once: true });
    });
  }

  const searchInput = document.querySelector('.filters input[name="q"]');
  const tableRows = Array.from(document.querySelectorAll('tbody tr'));

  function filterRows() {
    if (!searchInput) return;
    const query = searchInput.value.trim().toLowerCase();
    if (query === '') {
      tableRows.forEach((row) => { row.style.display = ''; });
      return;
    }
    tableRows.forEach((row) => {
      const text = [
        row.dataset.id || '',
        row.dataset.gtin || '',
        row.dataset.name || '',
        row.dataset.tnved || '',
        row.dataset.article || '',
      ].join(' ').toLowerCase();
      row.style.display = text.includes(query) ? '' : 'none';
    });
  }

  if (searchInput) {
    searchInput.addEventListener('input', filterRows);
    window.addEventListener('DOMContentLoaded', filterRows);
    filterRows();
  }

  const signCertSelect = document.getElementById('signCert');
  const signCertInfo = document.getElementById('signCertInfo');
  const nkAuthCertSelect = document.getElementById('nkAuthCert');
  const nkAuthCertInfo = document.getElementById('nkAuthCertInfo');
  const signLog = document.getElementById('signLog');
  const signButton = document.getElementById('signSelectedBtn');
  const loadAwaitingBtn = document.getElementById('loadAwaiting');
  const refreshAwaitingBtn = document.getElementById('refreshAwaitingList');
  const nkAuthBtn = document.getElementById('nkAuthBtn');
  const nkAuthResetBtn = document.getElementById('nkAuthResetBtn');
  const nkAuthStatus = document.getElementById('nkAuthStatus');
  let certs = [];
  const certMeta = [];
  let currentCertIndex = -1;

  const certInfoTargets = [
    { container: signCertInfo, context: 'sign' },
    { container: nkAuthCertInfo, context: 'auth' },
  ];

  function logLine(message) {
    if (!signLog) return;
    signLog.textContent += (signLog.textContent ? '\n' : '') + message;
    signLog.scrollTop = signLog.scrollHeight;
  }

  function resetLog() {
    if (!signLog) return;
    signLog.textContent = '';
  }

  const parseCertSubject = (value) => {
    const text = String(value || '');
    return text.split(',').reduce((acc, chunk) => {
      const [rawKey, ...rawValue] = chunk.split('=');
      if (!rawKey || !rawValue.length) return acc;
      const key = rawKey.trim().toUpperCase();
      const val = rawValue.join('=').trim();
      if (!key || !val) return acc;
      acc[key] = val;
      return acc;
    }, {});
  };

  const getSubjectValue = (subject, keys) => {
    for (const key of keys) {
      const upper = key.toUpperCase();
      if (subject[upper]) return subject[upper];
    }
    return '';
  };

  const joinTruthy = (parts, separator = ' ') => parts.filter(Boolean).join(separator);

  const truncateText = (value, maxLength = 120) => {
    const text = String(value ?? '');
    return text.length > maxLength ? `${text.slice(0, maxLength - 1)}…` : text;
  };

  const sanitizeDate = (date) => (Number.isNaN(date.getTime()) ? null : date);

  const formatThumbprint = (value) => value.replace(/\s+/g, '').toUpperCase().replace(/(.{4})/g, '$1 ').trim();

  function renderCertInfo(container, index, context) {
    if (!container) return;
    container.innerHTML = '';
    container.removeAttribute('title');
    if (index < 0 || !certMeta[index]) {
      const title = document.createElement('p');
      title.className = 'sign-cert-info__title';
      title.textContent = 'Сертификат не выбран.';
      container.appendChild(title);
      const hint = document.createElement('p');
      hint.className = 'sign-cert-info__meta';
      hint.textContent = context === 'auth'
        ? 'Выберите сертификат, чтобы получить токен для владельца.'
        : 'Выберите сертификат, чтобы посмотреть сведения о владельце и сроке действия.';
      container.appendChild(hint);
      return;
    }
    const meta = certMeta[index];
    const title = document.createElement('p');
    title.className = 'sign-cert-info__title';
    title.textContent = meta.summary || 'Сертификат';
    container.appendChild(title);

    const positionLine = joinTruthy([meta.position, meta.org], ' • ');
    if (positionLine) {
      const line = document.createElement('p');
      line.className = 'sign-cert-info__meta';
      line.textContent = positionLine;
      container.appendChild(line);
    }

    if (meta.inn) {
      const innLine = document.createElement('p');
      innLine.className = 'sign-cert-info__meta';
      innLine.textContent = 'ИНН: ' + meta.inn;
      container.appendChild(innLine);
    }

    if (meta.validFrom || meta.validTo) {
      const period = document.createElement('p');
      period.className = 'sign-cert-info__meta';
      const fromText = meta.validFrom ? meta.validFrom.toLocaleDateString() : '—';
      const toText = meta.validTo ? meta.validTo.toLocaleDateString() : '—';
      period.textContent = 'Срок действия: ' + fromText + ' — ' + toText;
      container.appendChild(period);
    }

    if (meta.issuerOrg) {
      const issuerLine = document.createElement('p');
      issuerLine.className = 'sign-cert-info__meta';
      issuerLine.textContent = 'Выдан: ' + meta.issuerOrg;
      container.appendChild(issuerLine);
    }

    if (meta.thumbprint) {
      const thumbLine = document.createElement('p');
      thumbLine.className = 'sign-cert-info__meta';
      thumbLine.textContent = 'Отпечаток: ' + meta.thumbprint;
      container.appendChild(thumbLine);
    }

    if (meta.rawSubject || meta.rawIssuer) {
      let tooltip = '';
      if (meta.rawSubject) tooltip += 'Владелец: ' + meta.rawSubject;
      if (meta.rawIssuer) tooltip += (tooltip ? '\n' : '') + 'Выдан: ' + meta.rawIssuer;
      if (tooltip) container.title = tooltip;
    }
  }

  function setSelectValue(select, value) {
    if (!select) return;
    if (value === '') {
      select.value = '';
      if (select.value !== '') select.selectedIndex = -1;
      return;
    }
    select.value = value;
    if (select.value !== value) {
      select.selectedIndex = -1;
    }
  }

  function applyCertSelection(index) {
    currentCertIndex = index;
    const value = index >= 0 ? String(index) : '';
    setSelectValue(signCertSelect, value);
    setSelectValue(nkAuthCertSelect, value);
    certInfoTargets.forEach(({ container, context }) => {
      renderCertInfo(container, index, context);
    });
  }

  applyCertSelection(-1);

  async function loadCertificates() {
    const certSelects = [signCertSelect, nkAuthCertSelect].filter(Boolean);
    if (!certSelects.length) return;
    certs = [];
    certMeta.length = 0;
    certSelects.forEach((select) => {
      select.innerHTML = '';
      select.disabled = true;
      select.add(new Option('Загрузка сертификатов…', ''));
    });
    applyCertSelection(-1);
    let store;
    try {
      store = await cadesplugin.CreateObjectAsync('CAdESCOM.Store');
      await store.Open(2, 'My', 2);
      const collection = await store.Certificates;
      const count = await collection.Count;
      const now = new Date();
      certSelects.forEach((select) => { select.innerHTML = ''; });
      for (let i = 1; i <= count; i++) {
        const cert = await collection.Item(i);
        const validToRaw = new Date(await cert.ValidToDate);
        if (Number.isNaN(validToRaw.getTime()) || validToRaw < now) continue;
        const validFromRaw = new Date(await cert.ValidFromDate);
        const subject = await cert.SubjectName;
        const issuer = await cert.IssuerName;
        const subjectText = String(subject ?? '');
        const issuerText = String(issuer ?? '');
        const subjectMap = parseCertSubject(subjectText);
        const issuerMap = parseCertSubject(issuerText);
        const fio = getSubjectValue(subjectMap, ['CN'])
          || joinTruthy([
            getSubjectValue(subjectMap, ['SN', 'SURNAME']),
            getSubjectValue(subjectMap, ['G', 'GN', 'GIVENNAME']),
            getSubjectValue(subjectMap, ['S', 'PATRONYMIC']),
          ]);
        const position = getSubjectValue(subjectMap, ['T', 'TITLE']);
        const org = getSubjectValue(subjectMap, ['O', 'OU', 'О']);
        const inn = getSubjectValue(subjectMap, ['INN', 'ИНН']);
        const issuerOrg = getSubjectValue(issuerMap, ['O', 'OU', 'О']);
        const thumbprintRaw = await cert.Thumbprint;
        const thumbprint = thumbprintRaw ? formatThumbprint(String(thumbprintRaw)) : '';
        const optionParts = [];
        if (fio) optionParts.push(fio);
        if (position) optionParts.push(position);
        if (org) optionParts.push(org);
        const optionLabel = optionParts.join(' · ') || subjectText.replace(/\s*,\s*/g, ', ');
        const summaryParts = [];
        if (fio) summaryParts.push(fio);
        if (org) summaryParts.push(org);
        const summary = summaryParts.join(' • ') || optionLabel;
        const index = certs.length;
        certs.push(cert);
        certMeta.push({
          summary,
          position,
          org,
          inn,
          validFrom: sanitizeDate(validFromRaw),
          validTo: sanitizeDate(validToRaw),
          issuerOrg,
          thumbprint,
          rawSubject: subjectText,
          rawIssuer: issuerText,
        });
        certSelects.forEach((select) => {
          const option = new Option(truncateText(optionLabel), String(index));
          option.title = subjectText;
          select.add(option);
        });
      }
      if (!certs.length) {
        certSelects.forEach((select) => {
          select.add(new Option('Нет действующих сертификатов', ''));
        });
        logLine('⚠️ Действующих сертификатов не найдено');
      } else {
        certSelects.forEach((select) => {
          select.disabled = false;
        });
        applyCertSelection(0);
        logLine(`✅ Сертификаты загружены (${certs.length})`);
      }
    } catch (error) {
      logLine('❌ CryptoPro: ' + (error.message || error));
      certSelects.forEach((select) => {
        select.innerHTML = '';
        select.add(new Option('Ошибка загрузки сертификатов', ''));
      });
    } finally {
      if (store) {
        try { await store.Close(); } catch (e) { /* ignore */ }
      }
      certSelects.forEach((select) => {
        select.disabled = certs.length === 0;
      });
      if (!certs.length) {
        applyCertSelection(-1);
      }
      if (signButton) {
        signButton.disabled = certs.length === 0;
      }
      if (nkAuthBtn) {
        nkAuthBtn.disabled = certs.length === 0;
      }
    }
  }

  function handleCertChange(event) {
    const value = event.target.value;
    const index = value === '' ? -1 : Number(value);
    applyCertSelection(Number.isNaN(index) ? -1 : index);
  }

  if (signCertSelect) {
    signCertSelect.addEventListener('change', handleCertChange);
  }

  if (nkAuthCertSelect) {
    nkAuthCertSelect.addEventListener('change', handleCertChange);
  }

  if (signButton) {
    signButton.disabled = true;
  }

  if (typeof cadesplugin !== 'undefined' && typeof cadesplugin.then === 'function') {
    cadesplugin.then(loadCertificates).catch((error) => {
      logLine('❌ CryptoPro: ' + (error.message || error));
      if (signButton) signButton.disabled = true;
      if (nkAuthBtn) nkAuthBtn.disabled = true;
    });
  } else {
    logLine('❌ Плагин CryptoPro недоступен');
    if (nkAuthBtn) nkAuthBtn.disabled = true;
  }

  refreshNkAuthStatus(false).catch(() => {});

  const utf8ToB64 = (value) => window.btoa(unescape(encodeURIComponent(value)));
  const isBase64 = (value) => /^[0-9A-Za-z+/]+={0,2}$/.test(value.replace(/\s+/g, ''));

  async function signDetached(xmlB64, cert) {
    const signer = await cadesplugin.CreateObjectAsync('CAdESCOM.CPSigner');
    await signer.propset_Certificate(cert);
    const sd = await cadesplugin.CreateObjectAsync('CAdESCOM.CadesSignedData');
    try {
      if (typeof sd.propset_ContentEncoding === 'function') {
        await sd.propset_ContentEncoding(cadesplugin.CADESCOM_BASE64_TO_BINARY);
      }
      await sd.propset_Content(xmlB64);
      const pkcs7 = await sd.SignCades(signer, cadesplugin.CADESCOM_CADES_BES, true);
      return { pkcs7 };
    } catch (error) {
      logLine('ℹ️ attempt-1: ' + (error.message || error));
    }
    await sd.propset_Content(window.atob(xmlB64));
    const pkcs7 = await sd.SignCades(signer, cadesplugin.CADESCOM_CADES_BES, true);
    return { pkcs7 };
  }

  async function signAttachedAuth(data, cert) {
    const signer = await cadesplugin.CreateObjectAsync('CAdESCOM.CPSigner');
    await signer.propset_Certificate(cert);
    const sd = await cadesplugin.CreateObjectAsync('CAdESCOM.CadesSignedData');
    if (typeof sd.propset_ContentEncoding === 'function' && typeof cadesplugin.CADESCOM_STRING_TO_UCS2LE !== 'undefined') {
      try {
        await sd.propset_ContentEncoding(cadesplugin.CADESCOM_STRING_TO_UCS2LE);
      } catch (error) {
        // устаревшие версии плагина могут не поддерживать установку кодировки
      }
    }
    await sd.propset_Content(data);
    return sd.SignCades(signer, cadesplugin.CADESCOM_CADES_BES);
  }

  function updateNkAuthStatus(text, active = false) {
    if (!nkAuthStatus) return;
    nkAuthStatus.textContent = text;
    nkAuthStatus.classList.toggle('nk-auth-block__status--active', Boolean(active));
  }

  async function refreshNkAuthStatus(log = false) {
    if (!nkAuthStatus) return;
    try {
      const response = await fetch('api/nk-auth.php?mode=status', { cache: 'no-store' });
      const raw = await response.text();
      if (!response.ok) throw new Error(`nk-auth status ${response.status}\n${raw}`);
      const data = JSON.parse(raw);
      if (data.active) {
        const expiresAt = data.expiresAt ? new Date(data.expiresAt * 1000) : null;
        updateNkAuthStatus(
          expiresAt ? `Токен активен до ${expiresAt.toLocaleString()}` : 'Токен активен',
          true,
        );
        if (log) logLine('ℹ️ Токен НК активен');
      } else {
        updateNkAuthStatus('Токен не получен.');
        if (log) logLine('ℹ️ Токен НК отсутствует');
      }
    } catch (error) {
      updateNkAuthStatus('Не удалось получить статус токена.');
      if (log) logLine('❌ nk-auth status: ' + (error.message || error));
    }
  }

  async function refreshAwaiting({ log = true, autoSelect = true, updateList = false } = {}) {
    if (!loadAwaitingBtn) return { total: 0, matched: 0 };
    loadAwaitingBtn.disabled = true;
    if (refreshAwaitingBtn) refreshAwaitingBtn.disabled = true;
    try {
      const response = await fetch('api/awaiting-list.php');
      const text = await response.text();
      if (!response.ok) throw new Error(`awaiting-list ${response.status}\n${text}`);
      const data = JSON.parse(text);
      if (data?.error) throw new Error(data.error);
      if (!Array.isArray(data)) throw new Error('Некорректный ответ');
      const ids = new Set(data.map((item) => String(item.goodId)));
      let matched = 0;
      document.querySelectorAll('tbody tr').forEach((row) => {
        const match = ids.has(row.dataset.id);
        row.classList.toggle('needs-sign', match);
        if (autoSelect) {
          const checkbox = row.querySelector('.select-item');
          if (checkbox) checkbox.checked = match;
        }
        if (match) matched++;
      });
      updateSelectAllState();
      if (updateList && awaitingContainer) {
        if (!data.length) {
          awaitingContainer.hidden = false;
          awaitingContainer.innerHTML = '<p class="sign-selection__empty">Нет карточек, ожидающих подписи.</p>';
        } else {
          awaitingContainer.hidden = false;
          const items = data.map((item) => `<li>${item.goodId} — ${item.name || ''}</li>`).join('');
          awaitingContainer.innerHTML = `<strong>Карточки в очереди (${data.length}):</strong><ul>${items}</ul>`;
        }
      }
      if (log) {
        logLine(`ℹ️ Карточек, ожидающих подписи: ${data.length}. На этой странице: ${matched}.`);
        if (data.length && !matched) {
          logLine('ℹ️ Карточки есть, но они находятся вне текущей страницы.');
        }
      }
      return { total: data.length, matched };
    } catch (error) {
      if (log) logLine('❌ awaiting-list: ' + (error.message || error));
      throw error;
    } finally {
      loadAwaitingBtn.disabled = false;
      if (refreshAwaitingBtn) refreshAwaitingBtn.disabled = false;
    }
  }

  if (loadAwaitingBtn) {
    loadAwaitingBtn.addEventListener('click', () => {
      refreshAwaiting({ log: true, autoSelect: true, updateList: true }).catch(() => {});
    });
  }

  if (refreshAwaitingBtn) {
    refreshAwaitingBtn.addEventListener('click', () => {
      refreshAwaiting({ log: true, autoSelect: false, updateList: true }).catch(() => {});
    });
  }

  if (nkAuthBtn) {
    nkAuthBtn.addEventListener('click', async () => {
      const idx = currentCertIndex;
      const cert = idx >= 0 ? certs[idx] : undefined;
      if (!cert) {
        logLine('❌ Выберите сертификат для получения токена');
        return;
      }

      logLine('=== Авторизация НК ===');

      try {
        const challengeResponse = await fetch('api/nk-auth.php');
        const challengeText = await challengeResponse.text();
        if (!challengeResponse.ok) {
          throw new Error(`nk-auth challenge ${challengeResponse.status}\n${challengeText}`);
        }
        const challenge = JSON.parse(challengeText);
        if (!challenge.uuid || !challenge.data) {
          throw new Error('Некорректный ответ True API');
        }
        logLine('ℹ️ Получен challenge True API');

        const signature = await signAttachedAuth(challenge.data, cert);
        logLine('ℹ️ Подпись сформирована');

        const tokenResponse = await fetch('api/nk-auth.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ uuid: challenge.uuid, signature }),
        });
        const tokenText = await tokenResponse.text();
        if (!tokenResponse.ok) {
          throw new Error(`nk-auth exchange ${tokenResponse.status}\n${tokenText}`);
        }
        const tokenData = JSON.parse(tokenText);
        logLine('✅ Токен НК получен');
        if (tokenData.expiresAt) {
          const expiresAt = new Date(tokenData.expiresAt * 1000);
          logLine('ℹ️ Токен действует до ' + expiresAt.toLocaleString());
        }
        await refreshNkAuthStatus(true);
        logLine('ℹ️ Страница обновится, чтобы загрузить карточки с вашим токеном.');
        setTimeout(() => window.location.reload(), 800);
      } catch (error) {
        logLine('❌ ' + (error.message || error));
      }
    });
  }

  if (nkAuthResetBtn) {
    nkAuthResetBtn.addEventListener('click', async () => {
      try {
        const response = await fetch('api/nk-auth.php', { method: 'DELETE' });
        const text = await response.text();
        if (!response.ok) {
          throw new Error(`nk-auth reset ${response.status}\n${text}`);
        }
        logLine('ℹ️ Токен НК сброшен');
        await refreshNkAuthStatus(true);
      } catch (error) {
        logLine('❌ ' + (error.message || error));
      }
    });
  }

  if (signButton) {
    signButton.addEventListener('click', async () => {
      resetLog();
      logLine('=== Новый запуск ===');
      const idx = currentCertIndex;
      const cert = idx >= 0 ? certs[idx] : undefined;
      if (!cert) {
        logLine('❌ Выберите сертификат');
        if (signButton) signButton.disabled = certs.length === 0;
        return;
      }
      const rows = getSelectedRows();
      if (!rows.length) {
        logLine('❌ Нет выбранных карточек');
        return;
      }
      const ids = [...new Set(rows.map((row) => row.dataset.id).filter(Boolean))];
      if (!ids.length) {
        logLine('❌ Не найдены ID карточек');
        return;
      }
      signButton.disabled = true;
      try {
        const xmlResponse = await fetch('api/get-xml.php?ids=' + ids.join(','));
        const xmlText = await xmlResponse.text();
        if (!xmlResponse.ok) throw new Error(`get-xml ${xmlResponse.status}\n${xmlText}`);
        const list = JSON.parse(xmlText);
        if (list?.error) {
          logLine('❌ ' + list.error);
          return;
        }
        if (!Array.isArray(list) || !list.length) {
          logLine('✋ Нечего подписывать');
          return;
        }
        logLine('ℹ️ К подписи: ' + list.length);
        const pack = [];
        for (const item of list) {
          try {
            const src = item.xmlB64 ?? item.xml ?? '';
            const xmlB64 = isBase64(src) ? src.replace(/\s+/g, '') : utf8ToB64(src);
            const { pkcs7 } = await signDetached(xmlB64, cert);
            pack.push({ goodId: item.goodId, base64Xml: xmlB64, signature: pkcs7 });
            logLine('✅ ' + item.goodId);
          } catch (error) {
            logLine('🔴 ' + item.goodId + ': ' + (error.message || error));
          }
        }
        if (!pack.length) {
          logLine('✋ Нечего отправлять');
          return;
        }
        const apiResponse = await fetch('api/send-signature.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ signPack: pack }),
        });
        const apiRaw = await apiResponse.text();
        logLine('API:\n' + apiRaw);
        try {
          const parsed = JSON.parse(apiRaw);
          if (parsed.signed?.length) logLine('🌿 Подписано: ' + parsed.signed.join(', '));
          if (parsed.errors?.length) logLine('⚠️ Ошибки:\n' + JSON.stringify(parsed.errors, null, 2));
        } catch (error) {
          /* ignore JSON parse errors */
        }
        getItemCheckboxes().forEach((checkbox) => { checkbox.checked = false; });
        updateSelectAllState();
        await refreshAwaiting({ log: false, autoSelect: false, updateList: true });
      } catch (error) {
        logLine('❌ ' + (error.message || error));
      } finally {
        if (signButton) signButton.disabled = certs.length === 0;
      }
    });
  }

  if (!nkAuthActive && nkAuthModal) {
    showNkAuthModal();
  }
})();
</script>
</body>
</html>
