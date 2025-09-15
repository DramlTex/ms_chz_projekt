<?php
require_once __DIR__ . '/../src/MoySkladClient.php';

$login = getenv('MS_LOGIN') ?: 'login';
$password = getenv('MS_PASSWORD') ?: 'password';

$client = new MoySkladClient($login, $password);
try {
    $items = $client->getAssortment();
} catch (Exception $e) {
    $error = $e->getMessage();
    $items = [];
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
<h1>Ассортимент МойСклад</h1>
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
</body>
</html>
