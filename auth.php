<?php
/**
 * Управление сессиями пользователей
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Установить данные пользователя в сессию
 */
function set_user($login, $password, $account = null, $folder = null) {
    $_SESSION['login'] = $login;
    $_SESSION['password'] = $password;
    $_SESSION['account'] = $account ?? $login;
    $_SESSION['folder'] = $folder ?? $login;
    $_SESSION['auth_header'] = 'Basic ' . base64_encode($login . ':' . $password);
    
    return $_SESSION['account'];
}

/**
 * Получить данные пользователя из сессии
 */
function get_user() {
    return [
        'login' => $_SESSION['login'] ?? null,
        'account' => $_SESSION['account'] ?? null,
        'folder' => $_SESSION['folder'] ?? null
    ];
}

/**
 * Получить логин и пароль пользователя
 */
function get_credentials() {
    return [
        $_SESSION['login'] ?? '',
        $_SESSION['password'] ?? ''
    ];
}

/**
 * Получить account пользователя
 */
function get_account() {
    return $_SESSION['account'] ?? null;
}

/**
 * Получить folder пользователя
 */
function get_folder() {
    return $_SESSION['folder'] ?? null;
}

/**
 * Получить Authorization заголовок
 */
function get_auth_header() {
    return $_SESSION['auth_header'] ?? null;
}

/**
 * Проверить авторизован ли пользователь
 */
function is_authenticated() {
    return !empty($_SESSION['login']) && !empty($_SESSION['password']);
}

/**
 * Выход пользователя
 */
function logout_user() {
    unset($_SESSION['login']);
    unset($_SESSION['password']);
    unset($_SESSION['account']);
    unset($_SESSION['folder']);
    unset($_SESSION['auth_header']);
    
    // Очищаем другие данные сессии
    session_unset();
    session_destroy();
}

/**
 * Требовать авторизацию (для защиты endpoints)
 */
function require_auth() {
    if (!is_authenticated()) {
        http_response_code(401);
        echo json_encode(['error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}