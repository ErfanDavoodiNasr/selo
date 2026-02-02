<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\UploadPaths;
use App\Core\Response;

class ProfileController
{
    public static function uploadPhoto(array $config): void
    {
        $user = Auth::requireUser($config);
        if (!isset($_FILES['photo'])) {
            Response::json(['ok' => false, 'error' => 'فایل ارسال نشده است.'], 422);
        }
        $file = $_FILES['photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::json(['ok' => false, 'error' => 'آپلود فایل ناموفق بود.'], 400);
        }
        $maxSize = $config['uploads']['max_size'] ?? (2 * 1024 * 1024);
        if ($file['size'] > $maxSize) {
            Response::json(['ok' => false, 'error' => 'حجم فایل بیش از حد مجاز است.'], 422);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($allowed[$mime])) {
            Response::json(['ok' => false, 'error' => 'فرمت تصویر مجاز نیست.'], 422);
        }
        $ext = $allowed[$mime];
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $uploadDir = rtrim(UploadPaths::baseDir($config), '/');
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $destination = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            Response::json(['ok' => false, 'error' => 'ذخیره فایل ممکن نیست.'], 500);
        }

        $width = null;
        $height = null;
        $info = @getimagesize($destination);
        if ($info) {
            $width = (int)$info[0];
            $height = (int)$info[1];
        }
        $thumbnailName = self::createThumbnail($destination, $uploadDir, $mime);

        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');
        $insert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'user_profile_photos (user_id, file_name, mime_type, file_size, width, height, thumbnail_name, is_active, deleted_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NULL, ?)');
        $insert->execute([$user['id'], $filename, $mime, $file['size'], $width, $height, $thumbnailName, $now]);
        $photoId = (int)$pdo->lastInsertId();

        self::setActiveInternal($config, $user['id'], $photoId);

        Response::json(['ok' => true, 'data' => ['photo_id' => $photoId]]);
    }

    public static function setActive(array $config): void
    {
        $user = Auth::requireUser($config);
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $photoId = (int)($data['photo_id'] ?? 0);
        if ($photoId <= 0) {
            Response::json(['ok' => false, 'error' => 'شناسه عکس نامعتبر است.'], 422);
        }
        self::setActiveInternal($config, $user['id'], $photoId, true);
        Response::json(['ok' => true]);
    }

    public static function deletePhoto(array $config, int $photoId): void
    {
        $user = Auth::requireUser($config);
        if ($photoId <= 0) {
            Response::json(['ok' => false, 'error' => 'شناسه عکس نامعتبر است.'], 422);
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT file_name, thumbnail_name, is_active FROM ' . $config['db']['prefix'] . 'user_profile_photos WHERE id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$photoId, $user['id']]);
        $photo = $stmt->fetch();
        if (!$photo) {
            Response::json(['ok' => false, 'error' => 'عکس یافت نشد.'], 404);
        }

        $now = date('Y-m-d H:i:s');
        $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'user_profile_photos SET is_active = 0, deleted_at = ? WHERE id = ? AND user_id = ?')->execute([$now, $photoId, $user['id']]);
        if ((int)$photo['is_active'] === 1) {
            $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'users SET active_photo_id = NULL WHERE id = ?')->execute([$user['id']]);
        }

        $uploadDir = rtrim(UploadPaths::baseDir($config), '/');
        $path = $uploadDir . '/' . $photo['file_name'];
        if (is_file($path)) {
            @unlink($path);
        }
        if (!empty($photo['thumbnail_name'])) {
            $thumbPath = $uploadDir . '/' . $photo['thumbnail_name'];
            if (is_file($thumbPath)) {
                @unlink($thumbPath);
            }
        }

        Response::json(['ok' => true]);
    }

    private static function setActiveInternal(array $config, int $userId, int $photoId, bool $validate = false): void
    {
        $pdo = Database::pdo();
        if ($validate) {
            $check = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'user_profile_photos WHERE id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1');
            $check->execute([$photoId, $userId]);
            if (!$check->fetch()) {
                Response::json(['ok' => false, 'error' => 'عکس یافت نشد.'], 404);
            }
        }
        $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'user_profile_photos SET is_active = 0 WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'user_profile_photos SET is_active = 1 WHERE id = ? AND user_id = ? AND deleted_at IS NULL')->execute([$photoId, $userId]);
        $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'users SET active_photo_id = ? WHERE id = ?')->execute([$photoId, $userId]);
    }

    private static function createThumbnail(string $source, string $uploadDir, string $mime): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }
        $info = @getimagesize($source);
        if (!$info) {
            return null;
        }
        [$width, $height] = $info;
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $size = min($width, $height);
        $srcX = (int)(($width - $size) / 2);
        $srcY = (int)(($height - $size) / 2);
        $targetSize = 256;

        $image = null;
        switch ($mime) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($source);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $image = @imagecreatefromwebp($source);
                }
                break;
        }
        if (!$image) {
            return null;
        }

        $thumb = imagecreatetruecolor($targetSize, $targetSize);
        imagecopyresampled($thumb, $image, 0, 0, $srcX, $srcY, $targetSize, $targetSize, $size, $size);

        $thumbName = 'profile_' . bin2hex(random_bytes(8)) . '.jpg';
        $thumbPath = rtrim($uploadDir, '/') . '/' . $thumbName;
        imagejpeg($thumb, $thumbPath, 85);

        imagedestroy($thumb);
        imagedestroy($image);

        return $thumbName;
    }
}
