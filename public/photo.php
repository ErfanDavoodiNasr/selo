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

$user = App\Core\Auth::user($config);
if (!$user && isset($_COOKIE['selo_token']) && is_string($_COOKIE['selo_token'])) {
    $user = App\Core\Auth::userFromToken($config, $_COOKIE['selo_token']);
}
if (!$user) {
    sendPlaceholder();
}

$photoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($photoId <= 0) {
    sendPlaceholder();
}

$pdo = App\Core\Database::pdo();
$stmt = $pdo->prepare('SELECT upp.file_name, upp.thumbnail_name, upp.mime_type, upp.user_id
    FROM ' . $config['db']['prefix'] . 'user_profile_photos upp
    INNER JOIN ' . $config['db']['prefix'] . 'users u ON u.id = upp.user_id AND u.active_photo_id = upp.id
    WHERE upp.id = ? AND upp.deleted_at IS NULL
    LIMIT 1');
$stmt->execute([$photoId]);
$photo = $stmt->fetch();
if (!$photo) {
    sendPlaceholder();
}

$ownerId = (int)$photo['user_id'];
$viewerId = (int)$user['id'];
$allowed = ($ownerId === $viewerId);
if (!$allowed) {
    $accessStmt = $pdo->prepare('SELECT 1
        FROM ' . $config['db']['prefix'] . 'users u
        WHERE u.id = ?
          AND (
            EXISTS (
                SELECT 1 FROM ' . $config['db']['prefix'] . 'conversations c
                WHERE (c.user_one_id = ? AND c.user_two_id = u.id)
                   OR (c.user_two_id = ? AND c.user_one_id = u.id)
            )
            OR EXISTS (
                SELECT 1
                FROM ' . $config['db']['prefix'] . 'group_members gm_viewer
                INNER JOIN ' . $config['db']['prefix'] . 'group_members gm_owner
                    ON gm_owner.group_id = gm_viewer.group_id
                WHERE gm_viewer.user_id = ?
                  AND gm_viewer.status = ?
                  AND gm_owner.user_id = u.id
                  AND gm_owner.status = ?
            )
          )
        LIMIT 1');
    $accessStmt->execute([$ownerId, $viewerId, $viewerId, $viewerId, 'active', 'active']);
    $allowed = (bool)$accessStmt->fetchColumn();
}
if (!$allowed) {
    // Return placeholder to avoid disclosing whether photo_id is valid.
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
header('Cache-Control: private, max-age=3600');
header('Content-Length: ' . filesize($path));
readfile($path);
