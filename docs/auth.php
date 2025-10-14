<?php
session_start();

function require_auth(): void {
    if (empty($_SESSION['login']) || empty($_SESSION['password'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function set_user(string $login, string $password, ?string $account = null, ?string $uid = null): string {
    $parts = explode('@', $uid ?? $login);
    if ($account === null) {
        $account = end($parts);
    }
    $user = $parts[0];
    $_SESSION['login'] = $login;
    $_SESSION['password'] = $password;
    $_SESSION['account'] = $account;
    $_SESSION['user'] = $user;
    return $account;
}

function get_credentials(): array {
    return [$_SESSION['login'] ?? '', $_SESSION['password'] ?? ''];
}

function get_login(): string {
    return $_SESSION['login'] ?? '';
}

function get_account(): string {
    return $_SESSION['account'] ?? '';
}

function get_user(): string {
    return $_SESSION['user'] ?? '';
}

function logout_user(): void {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
?>
