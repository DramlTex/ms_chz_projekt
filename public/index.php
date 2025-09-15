<?php
session_start();
require_once __DIR__ . '/../src/MoySkladClient.php';

const MY_SKLAD_URL = 'https://api.moysklad.ru/api/remap/1.2/context/employee/';

// Обработка формы авторизации.
$logPath = __DIR__ . '/../moysklad.log';

function log_line(string $msg) {
    global $logPath;
    file_put_contents($logPath, sprintf("[%s] %s\n", date('c'), $msg), FILE_APPEND);
}

function tail_log(string $path, int $lines = 120): string {
    if (!is_file($path)) return 'Лог пока пуст';
    $data = file($path, FILE_IGNORE_NEW_LINES);
    return implode("\n", array_slice($data, -$lines));
}

function extractApiErrors(string $body): string {
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['errors'])) return '';
    $msgs = [];
    foreach ($data['errors'] as $e) {
        $parts = [];
        if (isset($e['code'])) $parts[] = 'код ' . $e['code'];
        if (!empty($e['error'])) $parts[] = $e['error'];
        if (!empty($e['error_message'])) $parts[] = $e['error_message'];
        if (!empty($e['parameter'])) $parts[] = 'поле: ' . $e['parameter'];
        $msgs[] = implode(' — ', $parts);
    }
    return implode('; ', $msgs);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // На всякий случай сбрасываем старые креды,
    // чтобы «залипшая» сессия не мешала новой попытке
    unset($_SESSION['user'], $_SESSION['password']);

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // РЕКОМЕНДОВАННЫЙ эндпоинт для проверки логина
    // (обратите внимание на завершающий слэш)
    $ch = curl_init('https://api.moysklad.ru/api/remap/1.2/context/employee/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $username . ':' . $password,
        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_ENCODING       => 'gzip', // включает Accept-Encoding: gzip и распаковку
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

    // Достанем диагностические заголовки X-Lognex-*
    $xAuth = $xAuthMsg = '-';
    foreach (preg_split('/\r\n|\r|\n/', $headersRaw) as $h) {
        if (stripos($h, 'X-Lognex-Auth:') === 0)        $xAuth    = trim(substr($h, 16));
        if (stripos($h, 'X-Lognex-Auth-Message:') === 0) $xAuthMsg = trim(substr($h, 23));
    }

    // Логируем попытку (пароль не пишем)
    log_line(sprintf('LOGIN TRY user="%s" STATUS:%s CURL_ERR:%s X-Auth:%s X-Auth-Message:%s',
        $username, $status, $curlError ?: '-', $xAuth, $xAuthMsg));
    log_line('RESPONSE BODY: ' . mb_substr($body, 0, 2000));

    if ($raw === false) {
        $error = 'Ошибка соединения с сервисом "МойСклад"';
    } elseif ($status === 200) {
        $_SESSION['user'] = $username;
        $_SESSION['password'] = $password;
        header('Location: index.php');
        exit;
    } else {
        // Вытянем расшифровку из JSON "errors"
        $apiErr = extractApiErrors($body);
        if ($status === 415) {
            $error = 'HTTP 415 — проверьте, что передаётся Accept-Encoding: gzip.';
        } elseif ($status === 400 && str_contains(mb_strtolower($apiErr), 'accept')) {
            $error = 'HTTP 400 — неверный заголовок Accept. Должно быть application/json;charset=utf-8.';
        } elseif ($status === 401) {
            $error = 'HTTP 401 — неверные логин/пароль или нет доступа к API.';
        } else {
            $error = 'Ошибка авторизации в "Моём Складе" (HTTP ' . $status . ')' . ($apiErr ? ' — ' . $apiErr : '');
        }
    }
}

$items = [];
if (isset($_SESSION['user'], $_SESSION['password'])) {
    $client = new MoySkladClient($_SESSION['user'], $_SESSION['password']);
    try {
        // Запросим с expand=product (и limit), чтобы у variant был baseName
        $items = $client->getAssortment(['limit' => 100, 'expand' => 'product,attributes']);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Ассортимент МойСклад</title>
    <style>
        body { font-family: system-ui, sans-serif; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 14px; }
        th { background: #f6f6f6; text-align: left; }
        details { margin: 10px 0; }
        pre { background:#111; color:#9bf59b; padding:10px; border-radius:6px; }
    </style>
</head>
<body>
<?php if (!isset($_SESSION['user'])): ?>
    <h1>Вход</h1>
    <?php if (!empty($error)): ?>
  <p style="color:#b00;font-weight:600;"><?= htmlspecialchars($error) ?></p>
  <details open>
      <summary>Диагностика (последние строки лога)</summary>
      <pre><?= htmlspecialchars(tail_log($logPath, 120)) ?></pre>
  </details>
<?php endif; ?>
    <form method="post">
        <label>Логин: <input type="text" name="username" autocomplete="username"></label><br>
        <label>Пароль: <input type="password" name="password" autocomplete="current-password"></label><br><br>
        <button type="submit">Войти</button>
    </form>
<?php else: ?>
    <h1>Ассортимент МойСклад</h1>
    <p><a href="?logout=1">Выйти</a></p>
    <?php if (!empty($error)): ?>
        <p style="color:#b00; font-weight:600;">Ошибка: <?= htmlspecialchars($error) ?></p>
        <details open>
            <summary>Диагностика (последние строки лога)</summary>
            <pre><?= htmlspecialchars(tail_log($logPath, 120)) ?></pre>
        </details>
    <?php else: ?>
        <table id="assortment-table">
            <thead>
            <tr>
                <th>Тип</th>
                <th>Наименование</th>
                <th>Артикул</th>
                <th>ТНВЭД</th>
                <th>Доп. поля</th>
                <th>Базовый товар</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item):
                $additional = [];
                if (!empty($item['attributes'])) {
                    foreach ($item['attributes'] as $attr) {
                        $value = $attr['value'] ?? '';
                        $additional[] = ($attr['name'] ?? ($attr['meta']['href'] ?? 'attr')) . ': ' . (is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE));
                    }
                }
                $additionalStr = implode('; ', $additional);
                $type = $item['meta']['type'] ?? '';
                $baseName = '';
                if ($type === 'variant' && !empty($item['product']['name'])) {
                    $baseName = $item['product']['name'];
                }
            ?>
                <tr>
                    <td><?= htmlspecialchars($type) ?></td>
                    <td><?= htmlspecialchars($item['name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['article'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['tnved'] ?? '') ?></td>
                    <td><?= htmlspecialchars($additionalStr) ?></td>
                    <td><?= htmlspecialchars($baseName) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <details>
            <summary>Лог (последние строки)</summary>
            <pre><?= htmlspecialchars(tail_log($logPath, 100)) ?></pre>
        </details>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>
