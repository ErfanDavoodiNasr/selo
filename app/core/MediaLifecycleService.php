<?php
namespace App\Core;

class MediaLifecycleService
{
    private const DEFAULT_USER_TOTAL_QUOTA = 512 * 1024 * 1024;
    private const DEFAULT_USER_DAILY_QUOTA = 128 * 1024 * 1024;
    private const DEFAULT_APP_TOTAL_QUOTA = 2 * 1024 * 1024 * 1024;
    private const DEFAULT_APP_TOTAL_FILES = 50000;
    private const DEFAULT_PENDING_TTL = 6 * 60 * 60;
    private const DEFAULT_GC_MAX_ITEMS = 25;
    private static $stateReady = null;

    public static function enforceUploadQuotas(array $config, int $userId, int $incomingBytes, int $incomingFiles = 1): void
    {
        $incomingBytes = max(0, $incomingBytes);
        $incomingFiles = max(0, $incomingFiles);
        if ($incomingBytes <= 0 && $incomingFiles <= 0) {
            return;
        }

        $limits = self::limits($config);
        $pdo = Database::pdo();

        if ($incomingBytes > 0) {
            $usedByUser = (int)self::scalar(
                $pdo,
                'SELECT COALESCE(SUM(size_bytes), 0) FROM ' . $config['db']['prefix'] . 'media_files WHERE user_id = ?',
                [$userId]
            );
            if ($limits['user_total_quota_bytes'] > 0 && ($usedByUser + $incomingBytes) > $limits['user_total_quota_bytes']) {
                Response::json(['ok' => false, 'error' => 'سقف فضای فایل کاربر تکمیل شده است.'], 413);
            }

            $usedByUserToday = (int)self::scalar(
                $pdo,
                'SELECT COALESCE(SUM(size_bytes), 0) FROM ' . $config['db']['prefix'] . 'media_files WHERE user_id = ? AND created_at >= CURDATE()',
                [$userId]
            );
            if ($limits['user_daily_quota_bytes'] > 0 && ($usedByUserToday + $incomingBytes) > $limits['user_daily_quota_bytes']) {
                Response::json(['ok' => false, 'error' => 'سقف آپلود روزانه کاربر تکمیل شده است.'], 413);
            }
        }

        $appUsage = self::row(
            $pdo,
            'SELECT COALESCE(SUM(size_bytes), 0) AS total_bytes, COUNT(*) AS total_files FROM ' . $config['db']['prefix'] . 'media_files',
            []
        );
        $appTotalBytes = (int)($appUsage['total_bytes'] ?? 0);
        $appTotalFiles = (int)($appUsage['total_files'] ?? 0);
        if ($incomingBytes > 0 && $limits['app_total_quota_bytes'] > 0 && ($appTotalBytes + $incomingBytes) > $limits['app_total_quota_bytes']) {
            Response::json(['ok' => false, 'error' => 'ظرفیت کل فضای آپلود اپلیکیشن تکمیل شده است.'], 503);
        }
        if ($limits['app_total_files_limit'] > 0 && ($appTotalFiles + $incomingFiles) > $limits['app_total_files_limit']) {
            Response::json(['ok' => false, 'error' => 'تعداد فایل‌های ذخیره‌شده از سقف مجاز عبور کرده است.'], 503);
        }
    }

    public static function markPending(array $config, int $mediaId): void
    {
        if ($mediaId <= 0) {
            return;
        }
        $limits = self::limits($config);
        if (!self::ensureStateTable($config)) {
            return;
        }

        $expiresAt = date('Y-m-d H:i:s', time() + $limits['pending_ttl_seconds']);
        $now = date('Y-m-d H:i:s');
        $pdo = Database::pdo();
        $sql = 'INSERT INTO ' . $config['db']['prefix'] . 'media_upload_state
            (media_id, state, pending_expires_at, attached_at, updated_at)
            VALUES (?, ?, ?, NULL, ?)
            ON DUPLICATE KEY UPDATE
            state = VALUES(state),
            pending_expires_at = VALUES(pending_expires_at),
            attached_at = NULL,
            updated_at = VALUES(updated_at)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$mediaId, 'pending', $expiresAt, $now]);
    }

    public static function markAttached(array $config, array $mediaIds): void
    {
        $ids = [];
        foreach ($mediaIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            return;
        }

        if (!self::ensureStateTable($config)) {
            return;
        }
        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');
        $sqlInsert = $pdo->prepare('INSERT IGNORE INTO ' . $config['db']['prefix'] . 'media_upload_state (media_id, state, pending_expires_at, attached_at, updated_at) VALUES (?, ?, NULL, ?, ?)');
        foreach ($ids as $id) {
            $sqlInsert->execute([$id, 'attached', $now, $now]);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sqlUpdate = 'UPDATE ' . $config['db']['prefix'] . 'media_upload_state
            SET state = ?, pending_expires_at = NULL, attached_at = ?, updated_at = ?
            WHERE media_id IN (' . $placeholders . ')';
        $params = array_merge(['attached', $now, $now], $ids);
        $stmt = $pdo->prepare($sqlUpdate);
        $stmt->execute($params);
    }

    public static function cleanupExpiredPending(array $config): void
    {
        if (!self::ensureStateTable($config)) {
            return;
        }
        $limits = self::limits($config);
        $maxItems = $limits['gc_max_items'];
        if ($maxItems <= 0) {
            return;
        }

        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');
        $sql = 'SELECT mus.media_id, mf.file_name, mf.thumbnail_name
            FROM ' . $config['db']['prefix'] . 'media_upload_state mus
            JOIN ' . $config['db']['prefix'] . 'media_files mf ON mf.id = mus.media_id
            WHERE mus.state = ? AND mus.pending_expires_at IS NOT NULL AND mus.pending_expires_at <= ?
            ORDER BY mus.pending_expires_at ASC
            LIMIT ' . $maxItems;
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['pending', $now]);
        $rows = $stmt->fetchAll();
        if (empty($rows)) {
            return;
        }

        $mediaDir = UploadPaths::mediaDir($config);
        $deleteStmt = $pdo->prepare(
            'DELETE FROM ' . $config['db']['prefix'] . 'media_files mf
             WHERE mf.id = ?
               AND NOT EXISTS (SELECT 1 FROM ' . $config['db']['prefix'] . 'messages m WHERE m.media_id = mf.id)
               AND NOT EXISTS (SELECT 1 FROM ' . $config['db']['prefix'] . 'message_attachments ma WHERE ma.media_id = mf.id)'
        );

        foreach ($rows as $row) {
            $mediaId = (int)$row['media_id'];
            $deleteStmt->execute([$mediaId]);
            if ($deleteStmt->rowCount() > 0) {
                self::deleteFile($mediaDir, $row['file_name'] ?? null);
                self::deleteFile($mediaDir, $row['thumbnail_name'] ?? null);
            } else {
                // If already attached elsewhere, normalize state.
                self::markAttached($config, [$mediaId]);
            }
        }
    }

    private static function ensureStateTable(array $config): bool
    {
        if (self::$stateReady !== null) {
            return self::$stateReady;
        }
        try {
            $pdo = Database::pdo();
            $table = $config['db']['prefix'] . 'media_upload_state';
            $mediaTable = $config['db']['prefix'] . 'media_files';
            $fkName = 'fk_media_state_' . substr(sha1($table), 0, 12);
            $sql = 'CREATE TABLE IF NOT EXISTS `' . $table . '` (
                `media_id` BIGINT UNSIGNED NOT NULL,
                `state` ENUM(\'pending\', \'attached\') NOT NULL DEFAULT \'pending\',
                `pending_expires_at` DATETIME NULL,
                `attached_at` DATETIME NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`media_id`),
                KEY `idx_state_exp` (`state`, `pending_expires_at`),
                CONSTRAINT `' . $fkName . '` FOREIGN KEY (`media_id`) REFERENCES `' . $mediaTable . '` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
            $pdo->exec($sql);
            self::$stateReady = true;
        } catch (\Throwable $e) {
            self::$stateReady = false;
            Logger::warn('media_state_table_unavailable', ['error' => $e->getMessage()], 'upload');
        }
        return self::$stateReady;
    }

    private static function deleteFile(string $dir, ?string $name): void
    {
        if (!is_string($name) || trim($name) === '') {
            return;
        }
        $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function scalar($pdo, string $sql, array $params)
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private static function row($pdo, string $sql, array $params): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return is_array($row) ? $row : [];
    }

    private static function limits(array $config): array
    {
        $uploads = $config['uploads'] ?? [];
        return [
            'user_total_quota_bytes' => max(0, (int)($uploads['user_total_quota_bytes'] ?? self::DEFAULT_USER_TOTAL_QUOTA)),
            'user_daily_quota_bytes' => max(0, (int)($uploads['user_daily_quota_bytes'] ?? self::DEFAULT_USER_DAILY_QUOTA)),
            'app_total_quota_bytes' => max(0, (int)($uploads['app_total_quota_bytes'] ?? self::DEFAULT_APP_TOTAL_QUOTA)),
            'app_total_files_limit' => max(0, (int)($uploads['app_total_files_limit'] ?? self::DEFAULT_APP_TOTAL_FILES)),
            'pending_ttl_seconds' => max(300, (int)($uploads['pending_ttl_seconds'] ?? self::DEFAULT_PENDING_TTL)),
            'gc_max_items' => max(1, min(100, (int)($uploads['gc_max_items'] ?? self::DEFAULT_GC_MAX_ITEMS))),
        ];
    }
}
