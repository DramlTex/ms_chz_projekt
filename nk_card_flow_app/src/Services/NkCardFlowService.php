<?php

namespace NkCardFlow\Services;

use NkCardFlow\Logger\FileLogger;
use NkCardFlow\MoySklad\BarcodeHelper;
use NkCardFlow\MoySklad\MoySkladClient;
use NkCardFlow\Nk\CardBuilder;
use NkCardFlow\Nk\NkClient;
use RuntimeException;

class NkCardFlowService
{
    public function __construct(
        private MoySkladClient $moySklad,
        private NkClient $nkClient,
        private CardBuilder $cardBuilder,
        private FileLogger $logger,
    ) {
    }

    public function sendProductToNk(
        string $productId,
        ?string $variantId,
        bool $useLiveGtin,
        bool $moderation,
        array $nameOptions,
        ?string $producerInn = null,
    ): array {
        $this->logger->info('Starting NK card flow for product {product} variant {variant}', [
            'product' => $productId,
            'variant' => $variantId ?? 'n/a',
        ]);

        $product = $this->moySklad->getProduct($productId);
        $variant = $variantId ? $this->moySklad->getVariant($variantId) : null;
        $productData = $this->moySklad->extractItemDataWithInheritance($variant ?? $product, $variant ? $product : null);

        if (empty($productData['tnved'])) {
            throw new RuntimeException('Product TNVED code is required for NK card');
        }

        $cardData = $this->cardBuilder->build($productData, null, !$useLiveGtin, $moderation, $nameOptions);

        if ($useLiveGtin) {
            $gtin = $this->nkClient->getLiveGtin();
            $cardData['gtin'] = $gtin;
            $cardData['is_tech_gtin'] = false;
        } elseif ($producerInn) {
            $generated = $this->nkClient->generateTechGtin($cardData['categories'][0] ?? 0, $producerInn);
            if ($generated) {
                $cardData['gtin'] = $generated;
            }
        }

        $result = $this->nkClient->sendCard($cardData, !$cardData['is_tech_gtin']);
        $feedId = $result['feed_id'];
        $this->logger->info('Card sent to NK feed {feed}', ['feed' => $feedId]);

        $feedInfo = $this->nkClient->waitForFinalStatus($feedId);
        $status = $this->nkClient->formatStatusResponse($feedInfo);

        if (!empty($status['gtin'])) {
            $formatted = BarcodeHelper::formatGtin($status['gtin']);
            $this->moySklad->updateProductBarcodes($variantId ?? $productId, [
                ['gtin' => $formatted],
            ], $variantId !== null);
            $status['gtin_updated_in_ms'] = true;
        } else {
            $status['gtin_updated_in_ms'] = false;
        }

        return $status;
    }
}
