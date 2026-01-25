<?php
return [
    'installed' => false,
    'app' => [
        'name' => 'SELO',
        'name_fa' => 'سلو',
        'url' => 'http://localhost',
        'timezone' => 'Asia/Tehran',
        'jwt_secret' => 'CHANGE_ME',
    ],
    'db' => [
        'host' => 'localhost',
        'name' => 'selo',
        'user' => 'root',
        'pass' => '',
        'prefix' => 'selo_',
    ],
    'uploads' => [
        'dir' => __DIR__ . '/../storage/uploads',
        'max_size' => 2 * 1024 * 1024,
    ],
];
