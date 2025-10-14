<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/ms_api.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $login = $data['login'] ?? '';
    $password = $data['password'] ?? '';
    if ($login === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['error' => 'bad request'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        ms_api_request('https://api.moysklad.ru/api/remap/1.2/entity/customerorder?limit=1', $login, $password);
        $emp = ms_api_request('https://api.moysklad.ru/api/remap/1.2/context/employee', $login, $password);
        $uid = $emp['uid'] ?? $login;
        $parts = explode('@', $uid);
        $account = end($parts);
        $folder = $uid;
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'no_api_access') {
            http_response_code(403);
            echo json_encode(['error' => 'no_api_access'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        http_response_code(401);
        echo json_encode(['error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $account = set_user($login, $password, $account ?? null, $folder ?? null);
    $user = get_user();
    echo json_encode(['status' => 'ok', 'account' => $account, 'user' => $user], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'DELETE') {
    logout_user();
    echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
    exit;
}

$auth = !empty($_SESSION['login']) && !empty($_SESSION['password']);
$account = get_account();
$user = get_user();
echo json_encode(['authenticated' => $auth, 'account' => $account, 'user' => $user], JSON_UNESCAPED_UNICODE);

