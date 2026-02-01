<?php
require __DIR__ . '/../app/bootstrap.php';

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    http_response_code(404);
    exit;
}
$config = require $configFile;

$photoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($photoId <= 0) {
    http_response_code(404);
    exit;
}

$pdo = App\Core\Database::pdo();
$stmt = $pdo->prepare('SELECT file_name, thumbnail_name, mime_type FROM ' . $config['db']['prefix'] . 'user_profile_photos WHERE id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$photoId]);
$photo = $stmt->fetch();
if (!$photo) {
    http_response_code(404);
    exit;
}

$thumb = isset($_GET['thumb']) && $_GET['thumb'] === '1';
$fileName = $photo['file_name'];
if ($thumb && !empty($photo['thumbnail_name'])) {
    $fileName = $photo['thumbnail_name'];
}
$path = rtrim($config['uploads']['dir'], '/') . '/' . $fileName;
if (!file_exists($path)) {
    http_response_code(404);
    exit;
}

if ($thumb) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $thumbMime = $finfo->file($path);
    header('Content-Type: ' . ($thumbMime ?: $photo['mime_type']));
} else {
    header('Content-Type: ' . $photo['mime_type']);
}
header('Cache-Control: public, max-age=604800');
readfile($path);
