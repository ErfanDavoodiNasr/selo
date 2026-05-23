<?php

use Illuminate\Support\Facades\Route;

Route::fallback(function () {
    global $config;

    if (!is_array($config) || empty($config['installed'])) {
        $prefix = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        $prefix = $prefix === '/' ? '' : $prefix;
        return redirect($prefix . '/install/');
    }

    $realtimeMode = strtolower(trim((string)($config['realtime']['mode'] ?? 'auto')));
    if (!in_array($realtimeMode, ['auto', 'sse', 'poll'], true)) {
        $realtimeMode = 'auto';
    }

    return response()
        ->view('app', [
            'appUrl' => (string)($config['app']['url'] ?? ''),
            'basePath' => rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') === '/' ? '' : rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/'),
            'enableServiceWorker' => (bool)($config['app']['enable_service_worker'] ?? true),
            'realtimeMode' => $realtimeMode,
        ])
        ->header('X-Content-Type-Options', 'nosniff')
        ->header('Referrer-Policy', 'same-origin');
});
