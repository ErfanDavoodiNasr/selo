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
        'media_dir' => __DIR__ . '/../storage/uploads/media',
        'media_max_size' => 20 * 1024 * 1024,
        'photo_max_size' => 5 * 1024 * 1024,
        'video_max_size' => 25 * 1024 * 1024,
        'voice_max_size' => 10 * 1024 * 1024,
        'file_max_size' => 20 * 1024 * 1024,
    ],
    'calls' => [
        'signaling_url' => 'ws://localhost:3001/ws',
        'signaling_secret' => 'CHANGE_ME',
        'token_ttl_seconds' => 120,
        'ring_timeout_seconds' => 45,
        'rate_limit' => [
            'max_attempts' => 6,
            'window_minutes' => 1,
            'lock_minutes' => 2,
        ],
        'ice_servers' => [
            ['urls' => ['stun:stun.l.google.com:19302']],
        ],
    ],
];
