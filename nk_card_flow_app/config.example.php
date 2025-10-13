<?php

return [
    'moysklad' => [
        'base_url' => 'https://online.moysklad.ru/api/remap/1.2',
        'token' => getenv('MOYSKLAD_TOKEN') ?: '',
        'username' => getenv('MOYSKLAD_LOGIN') ?: '',
        'password' => getenv('MOYSKLAD_PASSWORD') ?: '',
        'timeout' => 30,
        'attribute_bindings' => [
            'tnved' => 'tnved',
            'article' => 'article',
            'brand' => 'brand',
            'country' => 'country',
            'color' => 'color',
            'size' => 'size',
            'documents' => 'documents',
            'target_gender' => 'target_gender',
        ],
    ],
    'nk' => [
        'base_url' => 'https://markirovka.crpt.ru/api/v3',
        'api_key' => getenv('NK_API_KEY') ?: '',
        'timeout' => 30,
        'category_detection' => [
            'auto_strategy' => 'TNVED',
        ],
    ],
    'logging' => [
        'file' => __DIR__ . '/logs/nk_api.log',
        'level' => 'info',
    ],
    'card' => [
        'tnved_detailed_attr_id' => 13933,
        'categories_require_full_tnved' => [30933, 31234, 32190],
        'country_iso_map' => [
            'россия' => 'RU',
            'беларусь' => 'BY',
            'китай' => 'CN',
            'индонезия' => 'ID',
            'индия' => 'IN',
            'армения' => 'AM',
        ],
    ],
];
