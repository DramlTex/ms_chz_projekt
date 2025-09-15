<?php
session_start();
require_once __DIR__ . '/../src/MoySkladClient.php';

const MY_SKLAD_URL = 'https://api.moysklad.ru/api/remap/1.2/context/usersettings';

// Выход из системы.
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$error = null;

// Обработка формы авторизации.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $ch = curl_init(MY_SKLAD_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        $error = 'Ошибка соединения с сервисом "Мой Склад"';
    } elseif ($status === 200) {
        $_SESSION['user'] = $username;
        $_SESSION['password'] = $password;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Ошибка авторизации в "Моём Складе"';
    }
}

$items = [];
if (isset($_SESSION['user'], $_SESSION['password'])) {
    $client = new MoySkladClient($_SESSION['user'], $_SESSION['password']);
    try {
        $items = $client->getAssortment();
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
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 4px; }
    </style>
</head>
<body>
<?php if (!isset($_SESSION['user'])): ?>
    <h1>Вход</h1>
    <?php if (!empty($error)): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post">
        <label>Логин: <input type="text" name="username"></label><br>
        <label>Пароль: <input type="password" name="password"></label><br>
        <button type="submit">Войти</button>
    </form>
<?php else: ?>
    <h1>Ассортимент МойСклад</h1>
    <p><a href="?logout=1">Выйти</a></p>
    <?php if (!empty($error)): ?>
        <p>Ошибка: <?= htmlspecialchars($error) ?></p>
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
                        $additional[] = $attr['name'] . ': ' . $value;
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
    <?php endif; ?>
<?php endif; ?>
</body>
</html>

