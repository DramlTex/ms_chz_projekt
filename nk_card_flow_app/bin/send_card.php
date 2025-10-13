#!/usr/bin/env php
<?php

declare(strict_types=1);

use NkCardFlow\Config\Config;
use NkCardFlow\Http\HttpClient;
use NkCardFlow\Logger\FileLogger;
use NkCardFlow\Logger\LogLevel;
use NkCardFlow\MoySklad\MoySkladClient;
use NkCardFlow\Nk\CardBuilder;
use NkCardFlow\Nk\CategoryDetector;
use NkCardFlow\Nk\NkClient;
use NkCardFlow\Services\NkCardFlowService;

$baseDir = dirname(__DIR__);
$configArray = require $baseDir . '/bootstrap.php';
$config = Config::fromArray($configArray);

$logLevel = match (strtolower((string) $config->get('logging.level', 'info'))) {
    'debug' => LogLevel::DEBUG,
    'info' => LogLevel::INFO,
    'notice' => LogLevel::NOTICE,
    'warning' => LogLevel::WARNING,
    'error' => LogLevel::ERROR,
    default => LogLevel::INFO,
};

$logger = new FileLogger($config->get('logging.file'), $logLevel);
$httpClient = new HttpClient($logger);
$moyskladClient = new MoySkladClient($httpClient, $logger, $config);
$categoryDetector = new CategoryDetector($httpClient, $logger, $config);
$cardBuilder = new CardBuilder($categoryDetector, $logger, $config);
$nkClient = new NkClient($httpClient, $logger, $config);
$service = new NkCardFlowService($moyskladClient, $nkClient, $cardBuilder, $logger);

$options = getopt('', [
    'product-id:',
    'variant-id::',
    'live-gtin::',
    'moderation::',
    'producer-inn::',
    'name-options::',
]);

if (!isset($options['product-id'])) {
    fwrite(STDERR, "Usage: php bin/send_card.php --product-id=<uuid> [--variant-id=<uuid>] [--live-gtin] [--producer-inn=7700000000] [--name-options=article,color,size] [--moderation]\n");
    exit(1);
}

$productId = (string) $options['product-id'];
$variantId = isset($options['variant-id']) ? (string) $options['variant-id'] : null;
$useLiveGtin = array_key_exists('live-gtin', $options);
$moderation = array_key_exists('moderation', $options);
$nameOptions = isset($options['name-options']) ? array_map('trim', explode(',', (string) $options['name-options'])) : [];
$producerInn = isset($options['producer-inn']) ? (string) $options['producer-inn'] : null;

try {
    $result = $service->sendProductToNk($productId, $variantId, $useLiveGtin, $moderation, $nameOptions, $producerInn);
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
} catch (Throwable $e) {
    $logger->error('Card flow failed: {message}', ['message' => $e->getMessage()]);
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
