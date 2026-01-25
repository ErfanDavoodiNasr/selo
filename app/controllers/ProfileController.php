<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
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
        $uploadDir = rtrim($config['uploads']['dir'], '/');
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $destination = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            Response::json(['ok' => false, 'error' => 'ذخیره فایل ممکن نیست.'], 500);
        }

        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');
        $insert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'user_profile_photos (user_id, file_name, mime_type, file_size, is_active, created_at) VALUES (?, ?, ?, ?, 0, ?)');
        $insert->execute([$user['id'], $filename, $mime, $file['size'], $now]);
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

    private static function setActiveInternal(array $config, int $userId, int $photoId, bool $validate = false): void
    {
        $pdo = Database::pdo();
        if ($validate) {
            $check = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'user_profile_photos WHERE id = ? AND user_id = ? LIMIT 1');
            $check->execute([$photoId, $userId]);
            if (!$check->fetch()) {
                Response::json(['ok' => false, 'error' => 'عکس یافت نشد.'], 404);
            }
        }
        $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'user_profile_photos SET is_active = 0 WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'user_profile_photos SET is_active = 1 WHERE id = ? AND user_id = ?')->execute([$photoId, $userId]);
        $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'users SET active_photo_id = ? WHERE id = ?')->execute([$photoId, $userId]);
    }
}
