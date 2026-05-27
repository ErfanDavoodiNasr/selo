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
    'providers' => [
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
    ],
    'aliases' => [
        'Artisan' => Illuminate\Support\Facades\Artisan::class,
        'Route' => Illuminate\Support\Facades\Route::class,
    ],
];
