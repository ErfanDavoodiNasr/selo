<?php

$seloConfigFile = __DIR__ . '/config.php';
$seloDistFile = __DIR__ . '/config.dist.php';
$seloConfig = is_file($seloConfigFile) ? require $seloConfigFile : (is_file($seloDistFile) ? require $seloDistFile : []);
$keyMaterial = (string)($seloConfig['app']['jwt_secret'] ?? 'selo-development-key');

return [
    'name' => (string)($seloConfig['app']['name'] ?? 'SELO'),
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
    'url' => (string)($seloConfig['app']['url'] ?? 'http://localhost'),
    'timezone' => (string)($seloConfig['app']['timezone'] ?? 'Asia/Tehran'),
    'locale' => 'fa',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'key' => getenv('APP_KEY') ?: 'base64:' . base64_encode(hash('sha256', $keyMaterial, true)),
    'cipher' => 'AES-256-CBC',
    'maintenance' => [
        'driver' => 'file',
        'store' => 'database',
    ],
    'providers' => Illuminate\Support\ServiceProvider::defaultProviders()->toArray(),
];
