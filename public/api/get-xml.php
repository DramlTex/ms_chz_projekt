<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

const SIGN_LOG = __DIR__ . '/../../sign_debug.log';

function slog(string $message): void
{
    file_put_contents(SIGN_LOG, '[' . date('c') . "] get_xml: $message\n", FILE_APPEND);
}

header('Content-Type: application/json; charset=utf-8');

try {
    $idsParam = trim((string)($_GET['ids'] ?? ''));
    if ($idsParam !== '') {
        $goodIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $idsParam)))));
    } else {
        $drafts = nkFetchAwaitingDrafts();
        $goodIds = array_values(array_map('intval', array_column($drafts, 'good_id')));
    }

    $goodIds = array_values(array_filter($goodIds));
    $goodIds = array_slice($goodIds, 0, 25);

    if (!$goodIds) {
        echo json_encode(['error' => 'Нет карточек для подписи'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $documents = NkApi::docsForSign($goodIds);
    $payload = array_map(
        static fn(array $item) => [
            'goodId' => $item['goodId'],
            'xmlB64' => $item['xml'] ?? '',
        ],
        $documents
    );

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    slog('ERROR ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
