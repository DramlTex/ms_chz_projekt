<?php
session_start();
require_once __DIR__ . '/../src/MoySkladClient.php';

// Эндпоинт для проверки учётных данных
const MS_LOGIN_URL = 'https://api.moysklad.ru/api/remap/1.2/context/employee/';

$logPath = __DIR__ . '/../moysklad.log';

// ===== Хелперы логирования и диагностики =====
function log_line(string $msg): void {
    global $logPath;
    file_put_contents($logPath, sprintf("[%s] %s\n", date('c'), $msg), FILE_APPEND);
}

function tail_log(string $path, int $lines = 120): string {
    if (!is_file($path)) return 'Лог пока пуст';
    $data = file($path, FILE_IGNORE_NEW_LINES);
    if (!$data) return 'Лог пуст или недоступен';
    return implode("\n", array_slice($data, -$lines));
}

// Парсинг массива errors из ответов API
function extractApiErrors(string $body): string {
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['errors'])) return '';
    $msgs = [];
    foreach ($data['errors'] as $e) {
        $parts = [];
        if (isset($e['code']))           $parts[] = 'код ' . $e['code'];
        if (!empty($e['error']))         $parts[] = $e['error'];
        if (!empty($e['error_message'])) $parts[] = $e['error_message'];
        if (!empty($e['parameter']))     $parts[] = 'поле: ' . $e['parameter'];
        $msgs[] = implode(' — ', $parts);
    }
    return implode('; ', $msgs);
}

// ===== Выход =====
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$error = null;

// ===== Обработка формы авторизации =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Сброс на случай «залипшей» сессии
    unset($_SESSION['user'], $_SESSION['password']);

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $ch = curl_init(MS_LOGIN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $username . ':' . $password, // Basic
        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_ENCODING       => 'gzip', // включает Accept-Encoding: gzip и авторазжатие
        CURLOPT_HEADER         => true,   // вернём и заголовки, и тело
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json;charset=utf-8',
            'Accept-Encoding: gzip',
            'User-Agent: AssortmentDemo/1.0'
        ],
    ]);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE) ?: 0;
    $headersRaw = substr((string)$raw, 0, $headerSize) ?: '';
    $body       = substr((string)$raw, $headerSize) ?: '';
    curl_close($ch);

    // Диагностические заголовки X-Lognex-*
    $xAuth = $xAuthMsg = '-';
    foreach (preg_split('/\r\n|\r|\n/', $headersRaw) as $h) {
        if (stripos($h, 'X-Lognex-Auth:') === 0)         $xAuth    = trim(substr($h, 16));
        if (stripos($h, 'X-Lognex-Auth-Message:') === 0) $xAuthMsg = trim(substr($h, 23));
    }

    // Логи (пароль НИКОГДА не пишем)
    log_line(sprintf('LOGIN TRY user="%s" STATUS:%s CURL_ERR:%s X-Auth:%s X-Auth-Message:%s',
        $username, $status, $curlError ?: '-', $xAuth, $xAuthMsg));
    log_line('RESPONSE BODY: ' . mb_substr($body, 0, 2000));

    if ($raw === false) {
        $error = 'Ошибка соединения с сервисом "МойСклад"';
    } elseif ($status === 200) {
        $_SESSION['user'] = $username;
        $_SESSION['password'] = $password;
        header('Location: index.php'); // PRG — избегаем повторной отправки формы
        exit;
    } else {
        $apiErr = extractApiErrors($body);
        if ($status === 415) {
            $error = 'HTTP 415 — проверьте, что передаётся Accept-Encoding: gzip.';
        } elseif ($status === 400 && (stripos($body, 'Accept') !== false || stripos($apiErr, 'Accept') !== false)) {
            $error = 'HTTP 400 — неверный заголовок Accept. Должно быть application/json;charset=utf-8.';
        } elseif ($status === 401) {
            $error = 'HTTP 401 — проверьте логин/пароль и права доступа к API.';
        } else {
            $error = 'Ошибка авторизации в "Моём Складе" (HTTP ' . $status . ')' . ($apiErr ? ' — ' . $apiErr : '');
        }
    }
}

// ===== Загрузка ассортимента =====
$items = [];
if (isset($_SESSION['user'], $_SESSION['password'])) {
    $client = new MoySkladClient($_SESSION['user'], $_SESSION['password'], 'https://api.moysklad.ru/api/remap/1.2', $logPath);
    try {
        $items = $client->getAssortment(); // РОВНО /entity/assortment
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

/**
 * ===== Подготовка «широкой» таблицы по доп. полям =====
 * Собираем список всех названий атрибутов в выборке и готовим функции приведения значений к строке.
 */

// Соберём уникальные имена колонок для атрибутов
$attrColumns = [];
foreach ($items as $it) {
    if (!empty($it['attributes']) && is_array($it['attributes'])) {
        foreach ($it['attributes'] as $attr) {
            $name = trim((string)($attr['name'] ?? ''));
            if ($name === '') { // фолбэк — ID или href
                $name = $attr['id'] ?? ($attr['meta']['href'] ?? 'attribute');
            }
            $attrColumns[$name] = true;
        }
    }
}
$attrColumns = array_keys($attrColumns);
natcasesort($attrColumns); // «человеческая» сортировка
$attrColumns = array_values($attrColumns);

// Помощники
function ms_is_list(array $a): bool {
    // совместимость с PHP < 8.1 (без array_is_list)
    $i = 0;
    foreach (array_keys($a) as $k) {
        if ($k !== $i++) return false;
    }
    return true;
}
function ms_value_to_string($value): string {
    if ($value === null) return '';
    if (is_bool($value))  return $value ? 'Да' : 'Нет';
    if (is_scalar($value)) return (string)$value;

    if (is_array($value)) {
        // Часто в enum/customentity есть поле name
        if (isset($value['name']) && is_scalar($value['name'])) {
            return (string)$value['name'];
        }
        // Список значений
        if (ms_is_list($value)) {
            $parts = [];
            foreach ($value as $v) {
                $parts[] = ms_value_to_string($v);
            }
            $parts = array_filter($parts, fn($s) => $s !== '');
            return implode(', ', $parts);
        }
        // meta-ссылка — покажем тип/последний сегмент href
        if (isset($value['meta']['href'])) {
            $type = $value['meta']['type'] ?? null;
            $href = $value['meta']['href'];
            $base = basename(parse_url($href, PHP_URL_PATH) ?? '');
            return $type ? ($type . ':' . $base) : $base;
        }
        // Фолбэк — компактный JSON
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    return '';
}
function ms_attr_value_to_string(array $attr): string {
    return ms_value_to_string($attr['value'] ?? null);
}
function clip_html(string $text, int $limit = 140): string {
    $text = (string)$text;
    if (mb_strlen($text) <= $limit) return htmlspecialchars($text, ENT_QUOTES);
    $short = mb_substr($text, 0, $limit - 1) . '…';
    return '<span title="'.htmlspecialchars($text, ENT_QUOTES).'">'.htmlspecialchars($short, ENT_QUOTES).'</span>';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Ассортимент МойСклад</title>
    <style>
        :root {
            --border: #e5e7eb;
            --bg-head: #f8fafc;
            --bg-row: #ffffff;
            --bg-row-alt: #f9fafb;
            --bg-hover: #f1f5f9;
        }
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 16px; }
        .error { color:#b00; font-weight:600; }
        .toolbar { margin: 8px 0 16px; }
        .table-wrap {
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: auto;
            box-shadow: 0 1px 0 rgba(0,0,0,.03);
            max-height: 80vh;
        }
        table.grid { border-collapse: separate; border-spacing: 0; width: 100%; }
        .grid thead th {
            position: sticky; top: 0; z-index: 2;
            background: var(--bg-head);
            border-bottom: 1px solid var(--border);
            padding: 10px;
            text-align: left;
            font-weight: 600; font-size: 13px; white-space: nowrap;
        }
        .grid td {
            border-bottom: 1px solid var(--border);
            padding: 8px 10px; font-size: 13px; vertical-align: top;
            max-width: 420px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .grid tbody tr:nth-child(odd) { background: var(--bg-row-alt); }
        .grid tbody tr:hover { background: var(--bg-hover); }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; }
        h1 { margin: 0 0 8px; }
    </style>
</head>
<body>
<?php if (!isset($_SESSION['user'])): ?>
    <h1>Вход</h1>
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
        <details open>
            <summary>Диагностика (последние строки лога)</summary>
            <pre class="mono"><?= htmlspecialchars(tail_log($logPath, 120)) ?></pre>
        </details>
    <?php endif; ?>
    <form method="post" autocomplete="on">
        <label>Логин: <input type="text" name="username" autocomplete="username" required></label><br>
        <label>Пароль: <input type="password" name="password" autocomplete="current-password" required></label><br><br>
        <button type="submit">Войти</button>
    </form>

<?php else: ?>
    <h1>Ассортимент МойСклад</h1>
    <div class="toolbar">
        <a href="?logout=1">Выйти</a>
    </div>

    <?php if (!empty($error)): ?>
        <p class="error">Ошибка: <?= htmlspecialchars($error) ?></p>
        <details open>
            <summary>Диагностика (последние строки лога)</summary>
            <pre class="mono"><?= htmlspecialchars(tail_log($logPath, 120)) ?></pre>
        </details>
    <?php else: ?>

        <div class="table-wrap">
            <table class="grid" id="assortment-table">
                <thead>
                <tr>
                    <th>Тип</th>
                    <th>Наименование</th>
                    <th>Артикул</th>
                    <th>ТНВЭД</th>
                    <th>Базовый товар</th>
                    <?php foreach ($attrColumns as $col): ?>
                        <th><?= htmlspecialchars($col) ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item):
                    // подготовим значения атрибутов для быстрой подстановки по имени
                    $attrValues = [];
                    if (!empty($item['attributes']) && is_array($item['attributes'])) {
                        foreach ($item['attributes'] as $attr) {
                            $name = trim((string)($attr['name'] ?? ''));
                            if ($name === '') {
                                $name = $attr['id'] ?? ($attr['meta']['href'] ?? 'attribute');
                            }
                            $attrValues[$name] = ms_attr_value_to_string($attr);
                        }
                    }
                    $type = $item['meta']['type'] ?? '';
                    $baseName = '';
                    // Без expand=product имя базового товара у variant может отсутствовать — это норма
                    if ($type === 'variant' && !empty($item['product']['name'])) {
                        $baseName = $item['product']['name'];
                    }
                ?>
                    <tr>
                        <td><?= htmlspecialchars($type) ?></td>
                        <td><?= htmlspecialchars($item['name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['article'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['tnved'] ?? '') ?></td>
                        <td><?= htmlspecialchars($baseName) ?></td>

                        <?php foreach ($attrColumns as $col): 
                            $val = $attrValues[$col] ?? '';
                        ?>
                            <td><?= clip_html($val, 120) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <details>
            <summary>Лог (последние строки)</summary>
            <pre class="mono"><?= htmlspecialchars(tail_log($logPath, 100)) ?></pre>
        </details>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>
