<?php
// Root front controller for deployments where the web root is the project root.

function servePublicAsset(string $path): void
{
    $assetsRoot = realpath(__DIR__ . '/public/assets');
    if ($assetsRoot === false) {
        return;
    }
    $target = realpath($assetsRoot . '/' . ltrim($path, '/'));
    if ($target === false || strpos($target, $assetsRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($target)) {
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
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
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
    servePublicAsset(substr($relativePath, strlen('/assets/')));
}

require __DIR__ . '/public/index.php';
