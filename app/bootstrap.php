<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

spl_autoload_register(static function (string $class): void {
    if (strpos($class, 'App\\') !== 0) {
        return;
    }

    $relative = substr($class, 4);
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

return require __DIR__ . '/../config/app.php';
