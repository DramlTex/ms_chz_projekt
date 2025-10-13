<?php

namespace NkCardFlow\Nk;

use NkCardFlow\Config\Config;
use NkCardFlow\Logger\FileLogger;

class CardBuilder
{
    private array $countryIsoMap;
    private array $categoriesWithFullTnved;
    private int $tnvedDetailedAttrId;

    public function __construct(private CategoryDetector $categoryDetector, private FileLogger $logger, Config $config)
    {
        $this->countryIsoMap = array_change_key_case($config->get('card.country_iso_map', []), CASE_LOWER);
        $this->categoriesWithFullTnved = $config->get('card.categories_require_full_tnved', []);
        $this->tnvedDetailedAttrId = (int) $config->get('card.tnved_detailed_attr_id', 13933);
    }

    public function build(array $productData, ?int $categoryId, bool $isTechGtin, bool $moderation, array $nameOptions = []): array
    {
        $tnved = $this->normalizeTnved($productData['tnved'] ?? '');
        $category = $categoryId;
        $productGroupCode = null;

        if (!$category) {
            $categoryInfo = $this->categoryDetector->detectByTnved($tnved);
            $category = $categoryInfo['categoryId'] ?? null;
            $productGroupCode = $categoryInfo['productGroupCode'] ?? null;
        }

        $name = $this->composeName($productData, $nameOptions);
        $attrs = $this->buildAttributes($productData, $tnved, $category);

        $card = [
            'is_tech_gtin' => $isTechGtin,
            'tnved' => $tnved,
            'brand' => $productData['brand'] ?? '',
            'good_name' => $name,
            'moderation' => $moderation ? 1 : 0,
            'categories' => array_filter([$category]),
            'good_attrs' => $attrs,
        ];

        if ($productGroupCode) {
            $card['product_group_code'] = $productGroupCode;
        }

        return $card;
    }

    private function composeName(array $productData, array $nameOptions): string
    {
        $parts = [];
        if (!empty($productData['article']) && in_array('article', $nameOptions, true)) {
            $parts[] = $productData['article'];
        }
        $parts[] = $productData['name'] ?? 'Без названия';
        if (!empty($productData['size']) && in_array('size', $nameOptions, true)) {
            $parts[] = (string) $productData['size'];
        }
        if (!empty($productData['color']) && in_array('color', $nameOptions, true)) {
            $parts[] = (string) $productData['color'];
        }

        return trim(implode(' ', $parts));
    }

    private function normalizeCountry(?string $country): ?string
    {
        if ($country === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($country));
        return $this->countryIsoMap[$normalized] ?? $country;
    }

    private function normalizeTnved(string $tnved): string
    {
        $digits = preg_replace('/\D+/', '', $tnved) ?? '';
        return substr($digits, 0, 10);
    }

    private function buildAttributes(array $productData, string $tnved, ?int $categoryId): array
    {
        $attrs = [];
        $country = $this->normalizeCountry($productData['country'] ?? null);
        if ($country) {
            $attrs[] = ['attr_id' => 2630, 'attr_value' => $country];
        }
        if (!empty($productData['brand'])) {
            $attrs[] = ['attr_id' => 2504, 'attr_value' => $productData['brand']];
        }
        if (!empty($productData['article'])) {
            $attrs[] = ['attr_id' => 13914, 'attr_value' => $productData['article'], 'attr_value_type' => 'Артикул'];
        }
        if (!empty($productData['color'])) {
            $attrs[] = ['attr_id' => 36, 'attr_value' => $productData['color']];
        }
        if (!empty($productData['size'])) {
            $attrs[] = ['attr_id' => 35, 'attr_value' => (string) $productData['size'], 'attr_value_type' => 'МЕЖДУНАРОДНЫЙ'];
        }
        if (!empty($productData['target_gender'])) {
            $attrs[] = ['attr_id' => 14013, 'attr_value' => $productData['target_gender']];
        }
        foreach (($productData['documents'] ?? []) as $document) {
            $attrs[] = ['attr_id' => 13836, 'attr_value' => $document];
        }

        $requiresFull = in_array($categoryId, $this->categoriesWithFullTnved, true);
        if ($requiresFull && strlen($tnved) < 10) {
            $attrs[] = ['attr_id' => $this->tnvedDetailedAttrId, 'attr_value' => str_pad($tnved, 10, '0')];
        }

        return $attrs;
    }
}
