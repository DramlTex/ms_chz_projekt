<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$script = cryptoProExtensionBootstrapJs();

if ($script === '') {
    echo "/* CryptoPro extension IDs are not configured */\n";
    return;
}

echo $script, "\n";
