<?php
require __DIR__ . '/../app/bootstrap.php';

function sendPlaceholder(): void
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMB/axXf5kAAAAASUVORK5CYII=');
    header('Content-Type: image/png');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=604800');
    header('Content-Length: ' . strlen($png));
    echo $png;
    exit;
}

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    sendPlaceholder();
}
$config = require $configFile;

$photoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($photoId <= 0) {
    sendPlaceholder();
}

$pdo = App\Core\Database::pdo();
$stmt = $pdo->prepare('SELECT file_name, thumbnail_name, mime_type FROM ' . $config['db']['prefix'] . 'user_profile_photos WHERE id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$photoId]);
$photo = $stmt->fetch();
if (!$photo) {
    sendPlaceholder();
}

$thumb = isset($_GET['thumb']) && $_GET['thumb'] === '1';
$fileName = $photo['file_name'];
if ($thumb && !empty($photo['thumbnail_name'])) {
    $fileName = $photo['thumbnail_name'];
}
$uploadDir = \App\Core\UploadPaths::baseDir($config);
$path = rtrim($uploadDir, '/') . '/' . $fileName;
if (!file_exists($path)) {
    sendPlaceholder();
}

if ($thumb) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $thumbMime = $finfo->file($path);
    header('Content-Type: ' . ($thumbMime ?: $photo['mime_type']));
} else {
    header('Content-Type: ' . $photo['mime_type']);
}
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=604800');
header('Content-Length: ' . filesize($path));
readfile($path);
