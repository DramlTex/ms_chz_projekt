<?php

namespace NkCardFlow\MoySklad;

class BarcodeHelper
{
    public static function formatGtin(string $gtin): string
    {
        $numbersOnly = preg_replace('/\D+/', '', $gtin) ?? '';
        return str_pad($numbersOnly, 14, '0', STR_PAD_LEFT);
    }
}
