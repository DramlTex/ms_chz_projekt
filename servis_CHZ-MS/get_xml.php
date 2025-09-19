<?php
require_once 'NkApi.php';

ini_set('display_errors', 0);
ini_set('log_errors',   1);
error_reporting(E_ALL);

const LOG = __DIR__ . '/sign_debug.log';
function slog($m)
{
    file_put_contents(LOG, '[' . date('c') . "] get_xml: $m\n", FILE_APPEND);
}

header('Content-Type: application/json; charset=utf-8');

try {
    /* ── 0. список ID из query‑параметра ─────────────────────────────── */
    $idsParam = trim($_GET['ids'] ?? '');

    if ($idsParam !== '') {
        $goodIds = array_filter(array_map('intval', explode(',', $idsParam)));
    } else {

        /* 1. черновики */
        $params  = [
            'good_status' => 'draft',
            'to_date'     => date('Y-m-d H:i:s') // format required by API
        ];
        $drafts = [];
        $offset  = 0;
        $batch   = 1000;
        do {
            $chunk   = NkApi::list($params, $batch, $offset);
            $drafts  = array_merge($drafts, $chunk);
            $offset += $batch;
        } while (count($chunk) === $batch);

        /* 2. нужно подписать? */
        $normalize = fn($v) => strtolower(preg_replace('/[^a-z0-9]/i', '', $v));
        $has = static function($row, $status) use ($normalize){
            $st   = $row['good_detailed_status'] ?? [];
            $list = array_map($normalize, (array)$st);
            foreach ($list as $item) {
                if ($item === $status || strpos($item, $status) !== false) {

        /* ── 1. получаем черновики ───────────────────────────────────── */
        $drafts = NkApi::list([
            'good_status' => 'draft',
            'to_date'     => date('Y-m-d H:i:s'),  // формат, который требует API
            'limit'       => 1000
        ]);

        /* ── 2. оставляем только «notsigned | waitsign» ──────────────── */
        $normalize = fn($v) => strtolower(preg_replace('/[^a-z0-9]/i', '', $v));

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
            $drafts,
            fn($r) => $hasStatus($r, 'notsigned') || $hasStatus($r, 'waitsign')
        );

        $goodIds = array_column($need, 'good_id');
    }

    /* ── 3. берём не больше 25 карточек за раз ─────────────────────── */
    $goodIds = array_slice($goodIds, 0, 25);

    if (!$goodIds) {
        echo json_encode(['error' => 'Нет карточек для подписи'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── 4. запрашиваем XML‑документы для подписи ───────────────────── */
    $src = NkApi::docsForSign($goodIds);   // [{goodId, xml}]

    $out = array_map(
        fn($r) => [
            'goodId' => $r['goodId'],
            'xmlB64' => $r['xml'] ?? ''     // оставляем BASE64 как есть
        ],
        $src
    );

    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    slog('ERROR ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
