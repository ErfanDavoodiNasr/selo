<?php

return [
    'default' => getenv('CACHE_STORE') ?: 'file',
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
    ],
    'prefix' => 'selo_cache',
];
