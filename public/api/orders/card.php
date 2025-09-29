<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $gtin = trim((string)($_GET['gtin'] ?? ''));
    if ($gtin === '') {
        throw new InvalidArgumentException('GTIN не передан');
    }

    $card = NkApi::cardByGtin($gtin);
    if (!$card) {
        http_response_code(404);
        echo json_encode(['error' => 'Карточка не найдена'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    $attrsList  = [];
    $attrsMap   = [];
    $templateId = null;

    foreach ((array)($card['good_attrs'] ?? []) as $attr) {
        if (!is_array($attr)) {
            continue;
        }
        $name  = (string)($attr['attr_name'] ?? '');
        $value = (string)($attr['attr_value'] ?? '');
        if ($name === '' && $value === '') {
            continue;
        }
        $attrsList[] = ['name' => $name, 'value' => $value];
        if ($name !== '') {
            $attrsMap[$name] = $value;
        }
        if ($templateId === null && $name !== '') {
            $lower = mb_strtolower($name);
            if (str_contains($lower, 'шаблон') || str_contains($lower, 'template')) {
                $digits = preg_replace('/\D+/', '', $value);
                if (is_string($digits) && $digits !== '') {
                    $templateId = (int)$digits;
                }
            }
        }
    }

    if ($templateId === null && isset($card['template_id']) && is_numeric($card['template_id'])) {
        $templateId = (int)$card['template_id'];
    }

    $identifiedBy = [];
    foreach ((array)($card['identified_by'] ?? []) as $idRow) {
        if (!is_array($idRow)) {
            continue;
        }
        $type  = (string)($idRow['type'] ?? '');
        $value = (string)($idRow['value'] ?? '');
        if ($type !== '' && $value !== '') {
            $identifiedBy[$type] = $value;
        }
    }

    $gtinValue = (string)($card['gtin'] ?? '');
    if ($gtinValue === '' && isset($identifiedBy['gtin'])) {
        $gtinValue = (string)$identifiedBy['gtin'];
    }
    if ($gtinValue === '') {
        $gtinValue = NkApi::normalizeGtin($gtin);
    }

    $productGroup = (string)($card['product_group'] ?? $card['productGroup'] ?? '');

    $tnved = $attrsMap['Код ТНВЭД']
        ?? $attrsMap['Код ТН ВЭД']
        ?? $attrsMap['tnved']
        ?? null;

    $response = [
        'goodId'        => (string)($card['good_id'] ?? ''),
        'name'          => (string)($card['good_name'] ?? ''),
        'gtin'          => $gtinValue,
        'productGroup'  => $productGroup,
        'tnved'         => $tnved !== null ? (string)$tnved : null,
        'templateId'    => $templateId,
        'brand'         => $attrsMap['Товарный знак (бренд)'] ?? $attrsMap['Бренд'] ?? null,
        'article'       => $attrsMap['Модель / артикул производителя'] ?? $attrsMap['Артикул'] ?? null,
        'attributes'    => $attrsList,
        'identifiedBy'  => $identifiedBy,
        'raw'           => $card,
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (JsonException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Некорректный JSON: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    $message = $e->getMessage();
    if (preg_match('/^HTTP\s+(\d{3})/i', $message, $matches)) {
        $status = (int)$matches[1];

        if (in_array($status, [401, 403], true)) {
            nkForgetAuthToken();
            http_response_code(401);
            echo json_encode([
                'error'   => 'Авторизация в Национальном каталоге недействительна или истекла. Пожалуйста, получите токен заново.',
                'details' => $message,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        if ($status >= 400 && $status < 600) {
            http_response_code($status);
            echo json_encode([
                'error'   => 'Национальный каталог вернул ошибку ' . $status,
                'details' => $message,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
    }

    http_response_code(502);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

