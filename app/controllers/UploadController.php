<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;

class UploadController
{
    private const MAX_DURATION = 86400; // 24h

    public static function upload(array $config): void
    {
        $user = Auth::requireUser($config);
        if (!isset($_FILES['file'])) {
            Response::json(['ok' => false, 'error' => 'فایل ارسال نشده است.'], 422);
        }

        $type = strtolower(trim($_POST['type'] ?? ''));
        $allowedTypes = ['voice', 'file', 'photo', 'video'];
        if (!in_array($type, $allowedTypes, true)) {
            Response::json(['ok' => false, 'error' => 'نوع فایل نامعتبر است.'], 422);
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::json(['ok' => false, 'error' => 'آپلود فایل ناموفق بود.'], 400);
        }

        $limits = self::sizeLimits($config);
        $maxSize = $limits[$type] ?? $limits['default'];
        if ($file['size'] > $maxSize) {
            Response::json(['ok' => false, 'error' => 'حجم فایل بیش از حد مجاز است.'], 422);
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            Response::json(['ok' => false, 'error' => 'فایل معتبر نیست.'], 400);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';

        $allowedMimes = [
            'photo' => ['image/jpeg', 'image/png', 'image/webp'],
            'video' => ['video/mp4', 'video/webm', 'video/ogg'],
            'voice' => ['audio/ogg', 'audio/webm', 'audio/mpeg', 'audio/wav', 'audio/mp4'],
        ];
        if ($type !== 'file' && !in_array($mime, $allowedMimes[$type] ?? [], true)) {
            Response::json(['ok' => false, 'error' => 'فرمت فایل مجاز نیست.'], 422);
        }

        $originalName = $file['name'] ?? 'file';
        $originalName = str_replace(["\0", "\r", "\n"], '', $originalName);
        $originalName = trim($originalName);
        if ($originalName === '') {
            $originalName = 'file';
        }
        if (mb_strlen($originalName) > 255) {
            $originalName = mb_substr($originalName, 0, 255);
        }

        $ext = self::extensionFor($type, $mime, $originalName);
        $filename = bin2hex(random_bytes(16)) . ($ext ? ('.' . $ext) : '');

        $mediaDir = $config['uploads']['media_dir'] ?? null;
        if (!$mediaDir) {
            $baseDir = $config['uploads']['dir'] ?? (dirname(__DIR__, 2) . '/storage/uploads');
            $mediaDir = rtrim($baseDir, '/') . '/media';
        }
        if (!is_dir($mediaDir)) {
            @mkdir($mediaDir, 0755, true);
        }
        if (!is_dir($mediaDir) || !is_writable($mediaDir)) {
            Response::json(['ok' => false, 'error' => 'مسیر آپلود قابل نوشتن نیست.'], 500);
        }

        $destination = rtrim($mediaDir, '/') . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            Response::json(['ok' => false, 'error' => 'ذخیره فایل ممکن نیست.'], 500);
        }

        $width = null;
        $height = null;
        if ($type === 'photo') {
            $info = @getimagesize($destination);
            if ($info) {
                $width = (int)$info[0];
                $height = (int)$info[1];
            }
        } elseif ($type === 'video') {
            $width = self::sanitizeInt($_POST['width'] ?? null, 0, 10000);
            $height = self::sanitizeInt($_POST['height'] ?? null, 0, 10000);
        }

        $duration = null;
        if ($type === 'voice' || $type === 'video') {
            $duration = self::sanitizeInt($_POST['duration'] ?? null, 0, self::MAX_DURATION);
        }

        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');
        $insert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'media_files (user_id, type, file_name, original_name, mime_type, size_bytes, duration, width, height, thumbnail_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)');
        $insert->execute([
            $user['id'],
            $type,
            $filename,
            $originalName,
            $mime,
            (int)$file['size'],
            $duration,
            $width,
            $height,
            $now,
        ]);
        $mediaId = (int)$pdo->lastInsertId();

        Response::json([
            'ok' => true,
            'data' => [
                'media_id' => $mediaId,
                'type' => $type,
                'mime_type' => $mime,
                'size_bytes' => (int)$file['size'],
                'duration' => $duration,
                'width' => $width,
                'height' => $height,
                'original_name' => $originalName,
            ],
        ]);
    }

    private static function sizeLimits(array $config): array
    {
        $defaults = [
            'photo' => 5 * 1024 * 1024,
            'video' => 25 * 1024 * 1024,
            'voice' => 10 * 1024 * 1024,
            'file' => 20 * 1024 * 1024,
            'default' => 20 * 1024 * 1024,
        ];
        $uploads = $config['uploads'] ?? [];
        return [
            'photo' => (int)($uploads['photo_max_size'] ?? $defaults['photo']),
            'video' => (int)($uploads['video_max_size'] ?? $defaults['video']),
            'voice' => (int)($uploads['voice_max_size'] ?? $defaults['voice']),
            'file' => (int)($uploads['file_max_size'] ?? $defaults['file']),
            'default' => (int)($uploads['media_max_size'] ?? $defaults['default']),
        ];
    }

    private static function extensionFor(string $type, string $mime, string $originalName): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'audio/ogg' => 'ogg',
            'audio/webm' => 'webm',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/mp4' => 'm4a',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogv',
        ];

        if ($type === 'file') {
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?? '');
            $blocked = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'pl', 'py', 'rb', 'cgi', 'exe', 'bat', 'cmd', 'sh', 'com', 'dll', 'js', 'jar', 'html', 'htm'];
            if ($ext === '' || in_array($ext, $blocked, true)) {
                return $map[$mime] ?? 'bin';
            }
            return $ext;
        }

        return $map[$mime] ?? 'bin';
    }

    private static function sanitizeInt($value, int $min, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $intVal = (int)$value;
        if ($intVal < $min || $intVal > $max) {
            return null;
        }
        return $intVal;
    }
}
