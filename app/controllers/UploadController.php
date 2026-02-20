<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\MediaLifecycleService;
use App\Core\UploadPaths;
use App\Core\Response;
use App\Core\Logger;
use App\Core\ImageSafety;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Filesystem;

class UploadController
{
    private const MAX_DURATION = 86400; // 24h
    private const DEFAULT_MAX_FILES = 10;

    public static function upload(array $config): void
    {
        $user = Auth::requireUser($config);
        self::enforceBodyLimit($config);
        if (RateLimiter::endpointIsLimited($config, 'upload', (int)$user['id'])) {
            Response::json(['ok' => false, 'error' => 'درخواست‌ها بیش از حد مجاز است. کمی بعد تلاش کنید.'], 429);
        }
        RateLimiter::hitEndpoint($config, 'upload', (int)$user['id']);

        $files = self::collectFiles();
        if (empty($files)) {
            Logger::warn('upload_failed', ['reason' => 'missing_file'], 'upload');
            Response::json(['ok' => false, 'error' => 'فایل ارسال نشده است.'], 422);
        }

        MediaLifecycleService::cleanupExpiredPending($config);

        $uploadsCfg = $config['uploads'] ?? [];
        $maxFiles = (int)($uploadsCfg['max_files_per_request'] ?? self::DEFAULT_MAX_FILES);
        $maxFiles = max(1, min(20, $maxFiles));
        if (count($files) > $maxFiles) {
            Response::json(['ok' => false, 'error' => 'تعداد فایل‌های ارسال شده بیش از حد مجاز است.'], 422);
        }

        $typeHint = strtolower(trim($_POST['type'] ?? 'auto'));
        if ($typeHint === '') {
            $typeHint = 'auto';
        }
        $allowedTypes = ['voice', 'file', 'photo', 'video', 'auto'];
        if (!in_array($typeHint, $allowedTypes, true)) {
            Logger::warn('upload_failed', ['reason' => 'invalid_type', 'type' => $typeHint], 'upload');
            Response::json(['ok' => false, 'error' => 'نوع فایل نامعتبر است.'], 422);
        }

        $metaList = self::parseMeta($_POST['meta'] ?? null);
        $limits = self::sizeLimits($config);

        $counts = ['photo' => 0, 'video' => 0, 'voice' => 0, 'file' => 0];
        $maxPhotos = (int)($uploadsCfg['max_photos_per_request'] ?? self::DEFAULT_MAX_FILES);
        $maxVideos = (int)($uploadsCfg['max_videos_per_request'] ?? self::DEFAULT_MAX_FILES);

        $mediaDir = UploadPaths::mediaDir($config);
        if (!Filesystem::ensureDir($mediaDir)) {
            Logger::error('upload_failed', ['reason' => 'media_dir_not_writable'], 'upload');
            Response::json(['ok' => false, 'error' => 'مسیر آپلود قابل نوشتن نیست.'], 500);
        }

        $items = [];
        $incomingBytes = self::sumIncomingBytes($files);
        MediaLifecycleService::enforceUploadQuotas($config, (int)$user['id'], $incomingBytes, count($files));
        foreach ($files as $index => $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                Logger::warn('upload_failed', ['reason' => 'upload_error', 'code' => $file['error']], 'upload');
                Response::json(['ok' => false, 'error' => 'آپلود فایل ناموفق بود.'], 400);
            }
            if (!is_uploaded_file($file['tmp_name'])) {
                Logger::warn('upload_failed', ['reason' => 'invalid_upload'], 'upload');
                Response::json(['ok' => false, 'error' => 'فایل معتبر نیست.'], 400);
            }

            $meta = isset($metaList[$index]) && is_array($metaList[$index]) ? $metaList[$index] : [];
            $forceType = strtolower(trim((string)($meta['force_type'] ?? '')));
            if ($forceType !== '' && !in_array($forceType, ['voice', 'file', 'photo', 'video'], true)) {
                Response::json(['ok' => false, 'error' => 'نوع فایل نامعتبر است.'], 422);
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';

            $detectedType = self::detectType($mime, $file['name'] ?? '', $forceType, $typeHint);

            if ($typeHint !== 'auto' && $detectedType !== $typeHint) {
                Response::json(['ok' => false, 'error' => 'نوع فایل با درخواست مطابقت ندارد.'], 422);
            }

            $maxSize = $limits[$detectedType] ?? $limits['default'];
            if ($file['size'] > $maxSize) {
                Logger::warn('upload_failed', ['reason' => 'size_exceeded', 'type' => $detectedType, 'size' => (int)$file['size']], 'upload');
                Response::json(['ok' => false, 'error' => 'حجم فایل بیش از حد مجاز است.'], 422);
            }

            $allowedMimes = self::allowedMimes();
            if ($detectedType !== 'file' && !in_array($mime, $allowedMimes[$detectedType] ?? [], true)) {
                Logger::warn('upload_failed', ['reason' => 'invalid_mime', 'type' => $detectedType, 'mime' => $mime], 'upload');
                Response::json(['ok' => false, 'error' => 'فرمت فایل مجاز نیست.'], 422);
            }
            if ($detectedType === 'photo') {
                $imgCheck = ImageSafety::validateForDecode($file['tmp_name'], $uploadsCfg);
                if (!$imgCheck['ok']) {
                    Logger::warn('upload_failed', ['reason' => 'unsafe_image', 'detail' => $imgCheck['error']], 'upload');
                    Response::json(['ok' => false, 'error' => $imgCheck['error']], 422);
                }
                $realMime = (string)($imgCheck['mime'] ?? '');
                if (!in_array($realMime, $allowedMimes['photo'] ?? [], true)) {
                    Logger::warn('upload_failed', ['reason' => 'invalid_image_header_mime', 'mime' => $realMime], 'upload');
                    Response::json(['ok' => false, 'error' => 'فرمت تصویر معتبر نیست.'], 422);
                }
            }

            $counts[$detectedType] = ($counts[$detectedType] ?? 0) + 1;
            if ($detectedType === 'photo' && $counts['photo'] > $maxPhotos) {
                Response::json(['ok' => false, 'error' => 'حداکثر تعداد عکس در هر درخواست بیش از حد مجاز است.'], 422);
            }
            if ($detectedType === 'video' && $counts['video'] > $maxVideos) {
                Response::json(['ok' => false, 'error' => 'حداکثر تعداد ویدیو در هر درخواست بیش از حد مجاز است.'], 422);
            }

            $originalName = self::safeOriginalName($file['name'] ?? 'file');
            if ($detectedType === 'file') {
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?? '');
                if (self::isBlockedFile($ext, $mime)) {
                    Response::json(['ok' => false, 'error' => 'نوع فایل مجاز نیست.'], 422);
                }
            }
            $ext = self::extensionFor($detectedType, $mime, $originalName);
            $filename = bin2hex(random_bytes(16)) . ($ext ? ('.' . $ext) : '');
            $destination = rtrim($mediaDir, '/') . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                Logger::error('upload_failed', ['reason' => 'move_failed', 'type' => $detectedType], 'upload');
                Response::json(['ok' => false, 'error' => 'ذخیره فایل ممکن نیست.'], 500);
            }
            Filesystem::ensureWritableFile($destination);

            $width = null;
            $height = null;
            $duration = null;
            $thumbnailName = null;

            if ($detectedType === 'photo') {
                $imgCheck = ImageSafety::validateForDecode($destination, $uploadsCfg);
                if (!$imgCheck['ok']) {
                    @unlink($destination);
                    Response::json(['ok' => false, 'error' => $imgCheck['error']], 422);
                }
                $width = (int)$imgCheck['width'];
                $height = (int)$imgCheck['height'];
                $thumbnailName = self::createImageThumbnail($destination, $mediaDir, $uploadsCfg);
            } elseif ($detectedType === 'video') {
                $width = self::sanitizeInt($meta['width'] ?? null, 0, 10000);
                $height = self::sanitizeInt($meta['height'] ?? null, 0, 10000);
                $duration = self::sanitizeInt($meta['duration'] ?? null, 0, self::MAX_DURATION);
                if ($duration === null) {
                    $duration = self::probeDuration($uploadsCfg, $destination);
                }
                $thumbnailName = self::createVideoThumbnail($destination, $mediaDir, $uploadsCfg);
            } elseif ($detectedType === 'voice') {
                $duration = self::sanitizeInt($meta['duration'] ?? null, 0, self::MAX_DURATION);
                if ($duration === null) {
                    $duration = self::probeDuration($uploadsCfg, $destination);
                }
            }

            $pdo = Database::pdo();
            $mediaId = 0;
            try {
                $pdo->beginTransaction();
                $now = date('Y-m-d H:i:s');
                $insert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'media_files (user_id, type, file_name, original_name, mime_type, size_bytes, duration, width, height, thumbnail_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $insert->execute([
                    $user['id'],
                    $detectedType,
                    $filename,
                    $originalName,
                    $mime,
                    (int)$file['size'],
                    $duration,
                    $width,
                    $height,
                    $thumbnailName,
                    $now,
                ]);
                $mediaId = (int)$pdo->lastInsertId();
                MediaLifecycleService::markPending($config, $mediaId);
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if (is_file($destination)) {
                    @unlink($destination);
                }
                if ($thumbnailName !== null) {
                    $thumbPath = rtrim($mediaDir, '/') . '/' . $thumbnailName;
                    if (is_file($thumbPath)) {
                        @unlink($thumbPath);
                    }
                }
                Logger::error('upload_failed', ['reason' => 'db_persist_failed', 'error' => $e->getMessage()], 'upload');
                Response::json(['ok' => false, 'error' => 'ثبت فایل در پایگاه‌داده ممکن نیست.'], 500);
            }

            Logger::info('upload_success', [
                'media_id' => $mediaId,
                'type' => $detectedType,
                'size' => (int)$file['size'],
                'mime' => $mime,
            ], 'upload');

            $items[] = [
                'media_id' => $mediaId,
                'type' => $detectedType,
                'mime_type' => $mime,
                'size_bytes' => (int)$file['size'],
                'duration' => $duration,
                'width' => $width,
                'height' => $height,
                'thumbnail_name' => $thumbnailName,
                'original_name' => $originalName,
            ];
        }

        $multi = count($files) > 1 || isset($_FILES['files']) || ($_POST['multi'] ?? '') === '1';
        if ($multi) {
            Response::json([
                'ok' => true,
                'data' => [
                    'items' => $items,
                    'total' => count($items),
                ],
            ]);
        }

        Response::json([
            'ok' => true,
            'data' => $items[0],
        ]);
    }

    private static function sumIncomingBytes(array $files): int
    {
        $sum = 0;
        foreach ($files as $file) {
            $sum += max(0, (int)($file['size'] ?? 0));
        }
        return $sum;
    }

    private static function collectFiles(): array
    {
        if (isset($_FILES['files'])) {
            return self::normalizeFiles($_FILES['files']);
        }
        if (isset($_FILES['file'])) {
            return self::normalizeFiles($_FILES['file']);
        }
        return [];
    }

    private static function normalizeFiles(array $input): array
    {
        if (!isset($input['name'])) {
            return [];
        }
        if (!is_array($input['name'])) {
            return [$input];
        }
        $count = count($input['name']);
        $files = [];
        for ($i = 0; $i < $count; $i++) {
            $files[] = [
                'name' => $input['name'][$i] ?? '',
                'type' => $input['type'][$i] ?? '',
                'tmp_name' => $input['tmp_name'][$i] ?? '',
                'error' => $input['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $input['size'][$i] ?? 0,
            ];
        }
        return $files;
    }

    private static function parseMeta($meta): array
    {
        if ($meta === null || $meta === '') {
            return [];
        }
        if (is_array($meta)) {
            return $meta;
        }
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private static function detectType(string $mime, string $originalName, string $forceType, string $typeHint): string
    {
        if ($forceType !== '') {
            return $forceType;
        }
        if ($typeHint !== 'auto') {
            return $typeHint;
        }
        if (strpos($mime, 'image/') === 0) {
            return 'photo';
        }
        if (strpos($mime, 'video/') === 0) {
            return 'video';
        }
        if (strpos($mime, 'audio/') === 0) {
            return 'voice';
        }
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?? '');
        $imageExt = ['jpg', 'jpeg', 'png', 'webp'];
        $videoExt = ['mp4', 'webm', 'ogv', 'mov'];
        $audioExt = ['mp3', 'wav', 'ogg', 'webm', 'm4a', 'aac'];
        if (in_array($ext, $imageExt, true)) {
            return 'photo';
        }
        if (in_array($ext, $videoExt, true)) {
            return 'video';
        }
        if (in_array($ext, $audioExt, true)) {
            return 'voice';
        }
        return 'file';
    }

    private static function allowedMimes(): array
    {
        return [
            'photo' => ['image/jpeg', 'image/png', 'image/webp'],
            'video' => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'],
            'voice' => ['audio/ogg', 'audio/webm', 'audio/mpeg', 'audio/wav', 'audio/mp4', 'audio/aac', 'video/webm'],
        ];
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
            'audio/aac' => 'aac',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogv',
            'video/quicktime' => 'mov',
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

    private static function isBlockedFile(string $ext, string $mime): bool
    {
        $blockedExt = [
            'php', 'phtml', 'php3', 'php4', 'php5', 'phar',
            'pl', 'py', 'rb', 'cgi',
            'exe', 'bat', 'cmd', 'sh', 'com', 'dll',
            'js', 'mjs', 'cjs',
            'html', 'htm', 'xhtml',
            'svg', 'xml',
        ];
        $blockedMime = [
            'text/html',
            'application/xhtml+xml',
            'application/javascript',
            'text/javascript',
            'application/x-php',
            'text/x-php',
            'application/x-httpd-php',
            'application/x-sh',
            'application/x-msdownload',
            'image/svg+xml',
            'text/xml',
            'application/xml',
        ];
        if ($ext !== '' && in_array($ext, $blockedExt, true)) {
            return true;
        }
        if ($mime !== '' && in_array($mime, $blockedMime, true)) {
            return true;
        }
        return false;
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

    private static function safeOriginalName(string $name): string
    {
        $name = str_replace(["\0", "\r", "\n"], '', $name);
        $name = trim($name);
        if ($name === '') {
            $name = 'file';
        }
        if (mb_strlen($name) > 255) {
            $name = mb_substr($name, 0, 255);
        }
        return $name;
    }

    private static function createImageThumbnail(string $source, string $mediaDir, array $uploadsCfg): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }
        $maxSize = (int)($uploadsCfg['thumbnail_max_size'] ?? 480);
        $maxSize = max(64, min(1024, $maxSize));
        $imgCheck = ImageSafety::validateForDecode($source, $uploadsCfg, $maxSize * $maxSize);
        if (!$imgCheck['ok']) {
            return null;
        }

        $width = (int)$imgCheck['width'];
        $height = (int)$imgCheck['height'];
        $mime = (string)$imgCheck['mime'];
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $scale = min($maxSize / $width, $maxSize / $height, 1);
        $targetW = (int)round($width * $scale);
        $targetH = (int)round($height * $scale);

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

        $thumb = imagecreatetruecolor($targetW, $targetH);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $targetW, $targetH, $width, $height);

        $thumbName = 'thumb_' . bin2hex(random_bytes(8)) . '.jpg';
        $thumbPath = rtrim($mediaDir, '/') . '/' . $thumbName;
        imagejpeg($thumb, $thumbPath, 82);

        imagedestroy($thumb);
        imagedestroy($image);

        return $thumbName;
    }

    private static function createVideoThumbnail(string $source, string $mediaDir, array $uploadsCfg): ?string
    {
        $ffmpeg = $uploadsCfg['ffmpeg_path'] ?? '';
        if ($ffmpeg === '' || !is_executable($ffmpeg) || !self::canRunMediaProcess($uploadsCfg)) {
            return null;
        }
        $thumbName = 'vthumb_' . bin2hex(random_bytes(8)) . '.jpg';
        $thumbPath = rtrim($mediaDir, '/') . '/' . $thumbName;
        $timeout = self::mediaProcessTimeoutSeconds($uploadsCfg);
        self::runMediaProcess([$ffmpeg, '-y', '-i', $source, '-frames:v', '1', '-q:v', '3', '-vf', 'scale=480:-1', $thumbPath], $timeout);
        if (!file_exists($thumbPath)) {
            return null;
        }
        return $thumbName;
    }

    private static function probeDuration(array $uploadsCfg, string $source): ?int
    {
        $ffprobe = $uploadsCfg['ffprobe_path'] ?? '';
        if ($ffprobe === '' || !is_executable($ffprobe) || !self::canRunMediaProcess($uploadsCfg)) {
            return null;
        }
        $timeout = self::mediaProcessTimeoutSeconds($uploadsCfg);
        $output = self::runMediaProcess([
            $ffprobe,
            '-v',
            'error',
            '-show_entries',
            'format=duration',
            '-of',
            'default=noprint_wrappers=1:nokey=1',
            $source,
        ], $timeout);
        if ($output === null) {
            return null;
        }
        $duration = (float)trim($output);
        if ($duration <= 0) {
            return null;
        }
        $seconds = (int)round($duration);
        if ($seconds <= 0 || $seconds > self::MAX_DURATION) {
            return null;
        }
        return $seconds;
    }

    private static function canRunMediaProcess(array $uploadsCfg): bool
    {
        if (!empty($uploadsCfg['shared_mode']) || !empty($uploadsCfg['disable_process_execution'])) {
            return false;
        }
        if (!function_exists('proc_open') || self::isFunctionDisabled('proc_open')) {
            return false;
        }
        if (!function_exists('proc_close') || self::isFunctionDisabled('proc_close')) {
            return false;
        }
        if (!function_exists('proc_get_status') || self::isFunctionDisabled('proc_get_status')) {
            return false;
        }
        return true;
    }

    private static function mediaProcessTimeoutSeconds(array $uploadsCfg): int
    {
        $timeout = (int)($uploadsCfg['media_process_timeout_seconds'] ?? 4);
        return max(1, min(15, $timeout));
    }

    private static function runMediaProcess(array $args, int $timeoutSeconds): ?string
    {
        if (empty($args)) {
            return null;
        }
        $command = [];
        foreach ($args as $arg) {
            $command[] = escapeshellarg((string)$arg);
        }
        $cmd = implode(' ', $command);
        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = @proc_open($cmd, $spec, $pipes);
        if (!is_resource($proc)) {
            return null;
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $start = microtime(true);
        $timedOut = false;

        while (true) {
            $status = proc_get_status($proc);
            if (!is_array($status) || !array_key_exists('running', $status)) {
                fclose($pipes[1]);
                fclose($pipes[2]);
                @proc_close($proc);
                return null;
            }
            $stdout .= (string)stream_get_contents($pipes[1]);
            $stderr .= (string)stream_get_contents($pipes[2]);

            if (!$status['running']) {
                break;
            }
            if ((microtime(true) - $start) >= $timeoutSeconds) {
                $timedOut = true;
                break;
            }
            usleep(100000);
        }

        if ($timedOut && function_exists('proc_terminate') && !self::isFunctionDisabled('proc_terminate')) {
            @proc_terminate($proc);
            usleep(100000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        if ($timedOut) {
            Logger::warn('media_process_timeout', ['timeout_seconds' => $timeoutSeconds], 'upload');
            return null;
        }
        if ($exitCode !== 0) {
            Logger::warn('media_process_failed', ['exit_code' => $exitCode, 'stderr' => trim($stderr)], 'upload');
            return null;
        }
        return trim($stdout);
    }

    private static function isFunctionDisabled(string $name): bool
    {
        $raw = ini_get('disable_functions');
        if (!is_string($raw) || trim($raw) === '') {
            return false;
        }
        $list = array_map('trim', explode(',', strtolower($raw)));
        return in_array(strtolower($name), $list, true);
    }

    private static function enforceBodyLimit(array $config): void
    {
        $max = (int)($config['rate_limits']['max_body_bytes']['upload'] ?? 0);
        if ($max <= 0) {
            return;
        }
        if (Request::contentLength() > $max) {
            Response::json(['ok' => false, 'error' => 'حجم درخواست بیش از حد مجاز است.'], 413);
        }
    }
}
