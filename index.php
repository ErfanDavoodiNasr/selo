<?php
// Root front controller for deployments where the web root is the project root.

function servePublicAsset(string $path): void
{
    $publicRoot = realpath(__DIR__ . '/public');
    if ($publicRoot === false) {
        return;
    }
    $target = realpath($publicRoot . '/' . ltrim($path, '/'));
    if ($target === false || strpos($target, $publicRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($target)) {
        return;
    }

    $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
    $map = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'map' => 'application/json; charset=UTF-8',
        'webmanifest' => 'application/manifest+json; charset=UTF-8',
    ];
    $mime = $map[$ext] ?? 'application/octet-stream';
    if (!isset($map[$ext]) && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $target);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
            finfo_close($finfo);
        }
    }

    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    if ($path === 'sw.js') {
        header('Cache-Control: no-cache, no-store, must-revalidate');
    } else {
        header('Cache-Control: public, max-age=31536000, immutable');
    }
    header('Content-Length: ' . filesize($target));
    readfile($target);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$basePath = rtrim(dirname($scriptName), '/');
if ($basePath === '/') {
    $basePath = '';
}
$relativePath = $path ?? '';
if ($basePath !== '' && strpos($relativePath, $basePath . '/') === 0) {
    $relativePath = substr($relativePath, strlen($basePath));
}
if (strpos($relativePath, '/assets/') === 0) {
    servePublicAsset(ltrim($relativePath, '/'));
}
if ($relativePath === '/sw.js') {
    servePublicAsset('sw.js');
}
if ($relativePath === '/favicon.ico') {
    servePublicAsset('favicon.ico');
}

require __DIR__ . '/public/index.php';
