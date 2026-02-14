<?php

return [
    // Storage mode: 'file' (no DB needed) or 'database' (faster queries, needs migration)
    'driver' => env('NUSANTARA_DRIVER', 'file'),

    // Table prefix for database mode
    'table_prefix' => 'nusantara_',

    // Cache duration in seconds (0 = no cache)
    // Recommended: 86400 (1 day) for file mode, 3600 (1 hour) for DB mode
    'cache_ttl' => env('NUSANTARA_CACHE_TTL', 86400),

    // Cache store (null = default store)
    'cache_store' => env('NUSANTARA_CACHE_STORE'),

    // Data source path (for file driver); null = use package default
    'data_path' => env('NUSANTARA_DATA_PATH'),

    // Search settings
    'search' => [
        'fuzzy_threshold' => (int) env('NUSANTARA_FUZZY_THRESHOLD', 70),
        'max_results' => (int) env('NUSANTARA_SEARCH_MAX_RESULTS', 20),
    ],

    // Custom aliases for fuzzy search (extend built-in aliases)
    'aliases' => [
        // 'your_alias' => 'TARGET REGION NAME',
    ],

    // Custom shipping styles (extend built-in styles)
    'shipping_styles' => [
        // 'custom_courier' => [
        //     'format' => ':village, :district, :regency, :province, :postal',
        //     'case' => 'upper',
        //     'separator' => ', ',
        // ],
    ],
];
