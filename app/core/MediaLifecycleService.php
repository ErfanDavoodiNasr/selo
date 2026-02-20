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
    private static $usageReady = null;

    public static function enforceUploadQuotas(array $config, int $userId, int $incomingBytes, int $incomingFiles = 1): void
    {
        $incomingBytes = max(0, $incomingBytes);
        $incomingFiles = max(0, $incomingFiles);
        if ($incomingBytes <= 0 && $incomingFiles <= 0) {
            return;
        }

        if (!self::ensureUsageTable($config)) {
            self::enforceUploadQuotasLegacy($config, $userId, $incomingBytes, $incomingFiles);
            return;
        }

        $usage = self::usageSnapshot($config, $userId);
        self::assertQuotaLimits($config, $usage, $incomingBytes, $incomingFiles);
    }

    public static function reserveUploadQuota(array $config, int $userId, int $incomingBytes, int $incomingFiles = 1): void
    {
        $incomingBytes = max(0, $incomingBytes);
        $incomingFiles = max(0, $incomingFiles);
        if ($incomingBytes <= 0 && $incomingFiles <= 0) {
            return;
        }

        if (!self::ensureUsageTable($config)) {
            self::enforceUploadQuotasLegacy($config, $userId, $incomingBytes, $incomingFiles);
            return;
        }

        $pdo = Database::pdo();
        if (!$pdo) {
            Response::json(['ok' => false, 'error' => 'اتصال پایگاه‌داده برقرار نیست.'], 500);
        }

        $autoTransaction = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $autoTransaction = true;
        }

        try {
            $today = date('Y-m-d');
            $userUsage = self::usageRowForUpdate($config, $userId, $today);
            $appUsage = self::usageRowForUpdate($config, 0, $today);

            $current = [
                'user_total_bytes' => (int)($userUsage['total_bytes'] ?? 0),
                'user_daily_bytes' => (int)($userUsage['daily_bytes'] ?? 0),
                'user_total_files' => (int)($userUsage['total_files'] ?? 0),
                'app_total_bytes' => (int)($appUsage['total_bytes'] ?? 0),
                'app_total_files' => (int)($appUsage['total_files'] ?? 0),
            ];
            self::assertQuotaLimitsOrThrow($config, $current, $incomingBytes, $incomingFiles);

            $newUserTotalBytes = $current['user_total_bytes'] + $incomingBytes;
            $newUserTotalFiles = $current['user_total_files'] + $incomingFiles;
            $newUserDailyBytes = $current['user_daily_bytes'] + $incomingBytes;
            $newAppTotalBytes = $current['app_total_bytes'] + $incomingBytes;
            $newAppTotalFiles = $current['app_total_files'] + $incomingFiles;

            self::persistUsageRow($config, $userId, $newUserTotalBytes, $newUserTotalFiles, $newUserDailyBytes, $today);
            self::persistUsageRow($config, 0, $newAppTotalBytes, $newAppTotalFiles, (int)($appUsage['daily_bytes'] ?? 0), $today);

            if ($autoTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($autoTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function releaseStorageUsage(array $config, int $userId, int $bytes, int $files = 1): void
    {
        $bytes = max(0, $bytes);
        $files = max(0, $files);
        if (($bytes <= 0 && $files <= 0) || !self::ensureUsageTable($config)) {
            return;
        }

        $pdo = Database::pdo();
        if (!$pdo) {
            return;
        }

        $autoTransaction = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $autoTransaction = true;
        }

        try {
            $today = date('Y-m-d');
            $userUsage = self::usageRowForUpdate($config, $userId, $today);
            $appUsage = self::usageRowForUpdate($config, 0, $today);

            $newUserTotalBytes = max(0, (int)$userUsage['total_bytes'] - $bytes);
            $newUserTotalFiles = max(0, (int)$userUsage['total_files'] - $files);
            $newAppTotalBytes = max(0, (int)$appUsage['total_bytes'] - $bytes);
            $newAppTotalFiles = max(0, (int)$appUsage['total_files'] - $files);

            self::persistUsageRow(
                $config,
                $userId,
                $newUserTotalBytes,
                $newUserTotalFiles,
                (int)$userUsage['daily_bytes'],
                (string)$userUsage['daily_date']
            );
            self::persistUsageRow(
                $config,
                0,
                $newAppTotalBytes,
                $newAppTotalFiles,
                (int)$appUsage['daily_bytes'],
                (string)$appUsage['daily_date']
            );

            if ($autoTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($autoTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::warn('media_usage_release_failed', ['error' => $e->getMessage()], 'upload');
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
        $sql = 'SELECT mus.media_id
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

        foreach ($rows as $row) {
            $mediaId = (int)$row['media_id'];
            if (!self::deleteIfUnreferenced($config, $mediaId)) {
                self::markAttached($config, [$mediaId]);
            }
        }
    }

    public static function deleteIfUnreferenced(array $config, int $mediaId): bool
    {
        if ($mediaId <= 0) {
            return false;
        }

        $pdo = Database::pdo();
        $metaStmt = $pdo->prepare('SELECT user_id, size_bytes, file_name, thumbnail_name FROM ' . $config['db']['prefix'] . 'media_files WHERE id = ? LIMIT 1');
        $metaStmt->execute([$mediaId]);
        $meta = $metaStmt->fetch();
        if (!$meta) {
            return false;
        }

        $deleteStmt = $pdo->prepare(
            'DELETE FROM ' . $config['db']['prefix'] . 'media_files mf
             WHERE mf.id = ?
               AND NOT EXISTS (
                   SELECT 1 FROM ' . $config['db']['prefix'] . 'messages m
                   WHERE m.media_id = mf.id AND m.is_deleted_for_all = 0
               )
               AND NOT EXISTS (
                   SELECT 1
                   FROM ' . $config['db']['prefix'] . 'message_attachments ma
                   JOIN ' . $config['db']['prefix'] . 'messages m2 ON m2.id = ma.message_id
                   WHERE ma.media_id = mf.id AND m2.is_deleted_for_all = 0
               )'
        );
        $deleteStmt->execute([$mediaId]);
        if ($deleteStmt->rowCount() <= 0) {
            return false;
        }

        $mediaDir = UploadPaths::mediaDir($config);
        self::deleteFile($mediaDir, $meta['file_name'] ?? null);
        self::deleteFile($mediaDir, $meta['thumbnail_name'] ?? null);
        self::releaseStorageUsage($config, (int)($meta['user_id'] ?? 0), (int)($meta['size_bytes'] ?? 0), 1);

        return true;
    }

    private static function ensureStateTable(array $config): bool
    {
        if (self::$stateReady !== null) {
            return self::$stateReady;
        }
        $table = $config['db']['prefix'] . 'media_upload_state';
        self::$stateReady = Database::tableExists($table);
        if (!self::$stateReady) {
            Logger::warn('media_state_table_unavailable', ['table' => $table], 'upload');
        }
        return self::$stateReady;
    }

    private static function ensureUsageTable(array $config): bool
    {
        if (self::$usageReady !== null) {
            return self::$usageReady;
        }
        $table = $config['db']['prefix'] . 'media_usage';
        self::$usageReady = Database::tableExists($table);
        if (!self::$usageReady) {
            Logger::warn('media_usage_table_unavailable', ['table' => $table], 'upload');
        }
        return self::$usageReady;
    }

    private static function usageSnapshot(array $config, int $userId): array
    {
        $pdo = Database::pdo();
        $today = date('Y-m-d');
        $user = self::readUsageRow($config, $userId, $today);
        $app = self::readUsageRow($config, 0, $today);

        return [
            'user_total_bytes' => (int)($user['total_bytes'] ?? 0),
            'user_daily_bytes' => (int)($user['daily_bytes'] ?? 0),
            'user_total_files' => (int)($user['total_files'] ?? 0),
            'app_total_bytes' => (int)($app['total_bytes'] ?? 0),
            'app_total_files' => (int)($app['total_files'] ?? 0),
        ];
    }

    private static function readUsageRow(array $config, int $userId, string $today): array
    {
        $pdo = Database::pdo();
        $table = $config['db']['prefix'] . 'media_usage';
        $stmt = $pdo->prepare('SELECT total_bytes, total_files, daily_bytes, daily_date FROM ' . $table . ' WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            $snapshot = self::buildUsageSnapshot($config, $userId, $today);
            return $snapshot;
        }

        if ((string)$row['daily_date'] !== $today) {
            $row['daily_bytes'] = 0;
            $row['daily_date'] = $today;
        }

        return [
            'total_bytes' => (int)$row['total_bytes'],
            'total_files' => (int)$row['total_files'],
            'daily_bytes' => (int)$row['daily_bytes'],
            'daily_date' => (string)$row['daily_date'],
        ];
    }

    private static function usageRowForUpdate(array $config, int $userId, string $today): array
    {
        $pdo = Database::pdo();
        $table = $config['db']['prefix'] . 'media_usage';

        $stmt = $pdo->prepare('SELECT total_bytes, total_files, daily_bytes, daily_date FROM ' . $table . ' WHERE user_id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            $snapshot = self::buildUsageSnapshot($config, $userId, $today);
            $insert = $pdo->prepare('INSERT INTO ' . $table . ' (user_id, total_bytes, total_files, daily_bytes, daily_date, updated_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $insert->execute([$userId, $snapshot['total_bytes'], $snapshot['total_files'], $snapshot['daily_bytes'], $today]);

            $stmt->execute([$userId]);
            $row = $stmt->fetch();
        }

        if ((string)$row['daily_date'] !== $today) {
            $row['daily_bytes'] = 0;
            $row['daily_date'] = $today;
        }

        return [
            'total_bytes' => (int)$row['total_bytes'],
            'total_files' => (int)$row['total_files'],
            'daily_bytes' => (int)$row['daily_bytes'],
            'daily_date' => (string)$row['daily_date'],
        ];
    }

    private static function buildUsageSnapshot(array $config, int $userId, string $today): array
    {
        $mediaTable = $config['db']['prefix'] . 'media_files';
        $pdo = Database::pdo();

        if ($userId > 0) {
            $sql = 'SELECT COALESCE(SUM(size_bytes), 0) AS total_bytes,
                           COUNT(*) AS total_files,
                           COALESCE(SUM(CASE WHEN created_at >= CURDATE() THEN size_bytes ELSE 0 END), 0) AS daily_bytes
                    FROM ' . $mediaTable . ' WHERE user_id = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
        } else {
            $sql = 'SELECT COALESCE(SUM(size_bytes), 0) AS total_bytes,
                           COUNT(*) AS total_files,
                           0 AS daily_bytes
                    FROM ' . $mediaTable;
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }

        $row = $stmt->fetch();
        return [
            'total_bytes' => (int)($row['total_bytes'] ?? 0),
            'total_files' => (int)($row['total_files'] ?? 0),
            'daily_bytes' => (int)($row['daily_bytes'] ?? 0),
            'daily_date' => $today,
        ];
    }

    private static function persistUsageRow(array $config, int $userId, int $totalBytes, int $totalFiles, int $dailyBytes, string $dailyDate): void
    {
        $table = $config['db']['prefix'] . 'media_usage';
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE ' . $table . '
            SET total_bytes = ?, total_files = ?, daily_bytes = ?, daily_date = ?, updated_at = NOW()
            WHERE user_id = ?');
        $stmt->execute([$totalBytes, $totalFiles, $dailyBytes, $dailyDate, $userId]);
    }

    private static function assertQuotaLimits(array $config, array $usage, int $incomingBytes, int $incomingFiles): void
    {
        $violation = self::quotaViolation($config, $usage, $incomingBytes, $incomingFiles);
        if ($violation !== null) {
            Response::json(['ok' => false, 'error' => $violation['error']], $violation['status']);
        }
    }

    private static function assertQuotaLimitsOrThrow(array $config, array $usage, int $incomingBytes, int $incomingFiles): void
    {
        $violation = self::quotaViolation($config, $usage, $incomingBytes, $incomingFiles);
        if ($violation !== null) {
            throw new QuotaExceededException($violation['error'], (int)$violation['status']);
        }
    }

    private static function quotaViolation(array $config, array $usage, int $incomingBytes, int $incomingFiles): ?array
    {
        $limits = self::limits($config);
        if ($incomingBytes > 0 && $limits['user_total_quota_bytes'] > 0 && ((int)$usage['user_total_bytes'] + $incomingBytes) > $limits['user_total_quota_bytes']) {
            return ['status' => 413, 'error' => 'سقف فضای فایل کاربر تکمیل شده است.'];
        }
        if ($incomingBytes > 0 && $limits['user_daily_quota_bytes'] > 0 && ((int)$usage['user_daily_bytes'] + $incomingBytes) > $limits['user_daily_quota_bytes']) {
            return ['status' => 413, 'error' => 'سقف آپلود روزانه کاربر تکمیل شده است.'];
        }
        if ($incomingBytes > 0 && $limits['app_total_quota_bytes'] > 0 && ((int)$usage['app_total_bytes'] + $incomingBytes) > $limits['app_total_quota_bytes']) {
            return ['status' => 503, 'error' => 'ظرفیت کل فضای آپلود اپلیکیشن تکمیل شده است.'];
        }
        if ($limits['app_total_files_limit'] > 0 && ((int)$usage['app_total_files'] + $incomingFiles) > $limits['app_total_files_limit']) {
            return ['status' => 503, 'error' => 'تعداد فایل‌های ذخیره‌شده از سقف مجاز عبور کرده است.'];
        }
        return null;
    }

    private static function enforceUploadQuotasLegacy(array $config, int $userId, int $incomingBytes, int $incomingFiles): void
    {
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
