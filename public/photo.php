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
$stmt = $pdo->prepare('SELECT file_name, mime_type FROM ' . $config['db']['prefix'] . 'user_profile_photos WHERE id = ? LIMIT 1');
$stmt->execute([$photoId]);
$photo = $stmt->fetch();
if (!$photo) {
    http_response_code(404);
    exit;
}

$path = rtrim($config['uploads']['dir'], '/') . '/' . $photo['file_name'];
if (!file_exists($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $photo['mime_type']);
header('Cache-Control: public, max-age=604800');
readfile($path);
