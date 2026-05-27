<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\LastSeenService;
use App\Core\MessageReceiptService;
use App\Core\PresenceService;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use PDOException;

class ConversationController
{
    public static function list(array $config): void
    {
        $user = Auth::requireUser($config);
        PresenceService::ping($config, (int)$user['id']);
        $limit = (int)Request::param('limit', 50);
        $limit = max(20, min(200, $limit));
        $perTypeLimit = min(400, $limit * 2);
        $pdo = Database::pdo();
        $sql = 'SELECT c.id, c.created_at AS conv_created_at, c.last_message_at, m.body AS last_body, m.type AS last_type, m.sender_id AS last_sender_id,
                m.attachments_count AS last_attachments_count,
                mf.original_name AS last_file_name,
                u.id AS other_id, u.full_name AS other_name, u.username AS other_username,
                u.last_seen_at AS other_last_seen_at, u.last_seen_privacy AS other_last_seen_privacy,
                up.id AS other_photo
                FROM ' . $config['db']['prefix'] . 'conversations c
                JOIN ' . $config['db']['prefix'] . 'users u
                    ON u.id = CASE WHEN c.user_one_id = ? THEN c.user_two_id ELSE c.user_one_id END
                LEFT JOIN ' . $config['db']['prefix'] . 'messages m ON m.id = c.last_message_id
                LEFT JOIN ' . $config['db']['prefix'] . 'media_files mf ON mf.id = m.media_id
                LEFT JOIN ' . $config['db']['prefix'] . 'user_profile_photos up ON up.id = u.active_photo_id
                WHERE c.user_one_id = ? OR c.user_two_id = ?
                ORDER BY c.last_message_at DESC, c.id DESC
                LIMIT ' . $perTypeLimit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user['id'], $user['id'], $user['id']]);
        $directs = $stmt->fetchAll();
        $otherIds = [];
        foreach ($directs as $conv) {
            $otherIds[] = (int)$conv['other_id'];
        }
        $onlineMap = PresenceService::onlineMap($config, $otherIds);
        $unreadCounts = MessageReceiptService::unreadCounts($config, (int)$user['id']);
        $unreadMap = $unreadCounts['by_conversation'] ?? [];

        foreach ($directs as &$conv) {
            $conv['id'] = (int)$conv['id'];
            $conv['other_id'] = (int)$conv['other_id'];
            $conv['other_photo'] = $conv['other_photo'] !== null ? (int)$conv['other_photo'] : null;
            $conv['unread_count'] = (int)($unreadMap[$conv['id']] ?? 0);
            $isOnline = isset($onlineMap[$conv['other_id']]);
            $status = LastSeenService::statusFor(
                $conv['other_last_seen_at'] ?? null,
                $conv['other_last_seen_privacy'] ?? null,
                $config,
                $isOnline
            );
            $conv['status_text'] = $status['text'];
            $conv['chat_type'] = 'direct';
            $conv['last_preview'] = self::previewText($conv['last_type'] ?? 'text', $conv['last_body'] ?? '', $conv['last_file_name'] ?? '', (int)($conv['last_attachments_count'] ?? 0));
            $conv['sort_time'] = $conv['last_message_at'] ?? $conv['conv_created_at'];
            unset($conv['conv_created_at']);
            unset($conv['other_last_seen_at'], $conv['other_last_seen_privacy']);
        }

        $groupSql = 'SELECT g.id, g.title, g.avatar_path, g.privacy_type, g.public_handle, g.created_at,
                gm.role AS member_role,
                m.body AS last_body, m.type AS last_type, m.sender_id AS last_sender_id, m.created_at AS last_message_at,
                m.attachments_count AS last_attachments_count,
                mf.original_name AS last_file_name,
                su.full_name AS last_sender_name
                FROM ' . $config['db']['prefix'] . 'groups g
                JOIN ' . $config['db']['prefix'] . 'group_members gm
                    ON gm.group_id = g.id AND gm.user_id = ? AND gm.status = ?
                LEFT JOIN (
                    SELECT gm2.group_id, MAX(m2.id) AS last_message_id
                    FROM ' . $config['db']['prefix'] . 'group_members gm2
                    LEFT JOIN ' . $config['db']['prefix'] . 'messages m2
                        ON m2.group_id = gm2.group_id AND m2.is_deleted_for_all = 0
                    WHERE gm2.user_id = ? AND gm2.status = ?
                    GROUP BY gm2.group_id
                ) glast ON glast.group_id = g.id
                LEFT JOIN ' . $config['db']['prefix'] . 'messages m ON m.id = glast.last_message_id
                LEFT JOIN ' . $config['db']['prefix'] . 'media_files mf ON mf.id = m.media_id
                LEFT JOIN ' . $config['db']['prefix'] . 'users su ON su.id = m.sender_id
                ORDER BY COALESCE(m.created_at, g.created_at) DESC, g.id DESC
                LIMIT ' . $perTypeLimit;
        $gStmt = $pdo->prepare($groupSql);
        $gStmt->execute([$user['id'], 'active', $user['id'], 'active']);
        $groups = $gStmt->fetchAll();
        $groupItems = [];
        foreach ($groups as $group) {
            $preview = self::previewText($group['last_type'] ?? 'text', $group['last_body'] ?? '', $group['last_file_name'] ?? '', (int)($group['last_attachments_count'] ?? 0));
            if ($preview !== '' && !empty($group['last_sender_name'])) {
                $preview = $group['last_sender_name'] . ': ' . $preview;
            }
            $groupItems[] = [
                'id' => (int)$group['id'],
                'chat_type' => 'group',
                'title' => $group['title'],
                'avatar_path' => $group['avatar_path'],
                'privacy_type' => $group['privacy_type'],
                'public_handle' => $group['public_handle'],
                'member_role' => $group['member_role'],
                'last_message_at' => $group['last_message_at'],
                'last_preview' => $preview,
                'sort_time' => $group['last_message_at'] ?? $group['created_at'],
            ];
        }

        $all = array_merge($directs, $groupItems);
        self::hydrateSettings($config, $all, (int)$user['id']);
        usort($all, function ($a, $b) {
            return strcmp($b['sort_time'], $a['sort_time']);
        });
        if (count($all) > $limit) {
            $all = array_slice($all, 0, $limit);
        }
        foreach ($all as &$item) {
            unset($item['sort_time']);
        }

        Response::json(['ok' => true, 'data' => $all]);
    }

    public static function updateSettings(array $config): void
    {
        $user = Auth::requireUser($config);
        $data = Request::json();
        $chatType = (string)($data['chat_type'] ?? '');
        $chatId = (int)($data['chat_id'] ?? 0);
        if (!in_array($chatType, ['direct', 'group'], true) || $chatId <= 0) {
            Response::json(['ok' => false, 'error' => 'گفتگو نامعتبر است.'], 422);
        }
        $pdo = Database::pdo();
        self::ensureSettingsTable($config);
        if ($chatType === 'direct') {
            $check = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'conversations WHERE id = ? AND (user_one_id = ? OR user_two_id = ?) LIMIT 1');
            $check->execute([$chatId, $user['id'], $user['id']]);
        } else {
            $check = $pdo->prepare('SELECT group_id FROM ' . $config['db']['prefix'] . 'group_members WHERE group_id = ? AND user_id = ? AND status = ? LIMIT 1');
            $check->execute([$chatId, $user['id'], 'active']);
        }
        if (!$check->fetch()) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }

        $muted = !empty($data['muted']) ? 1 : 0;
        $pinnedMessageId = isset($data['pinned_message_id']) && (int)$data['pinned_message_id'] > 0 ? (int)$data['pinned_message_id'] : null;
        $pinnedPreview = trim((string)($data['pinned_preview'] ?? ''));
        if ($pinnedPreview !== '') {
            $pinnedPreview = mb_substr($pinnedPreview, 0, 255, 'UTF-8');
        } else {
            $pinnedPreview = null;
        }
        $now = date('Y-m-d H:i:s');
        $sql = 'INSERT INTO ' . $config['db']['prefix'] . 'user_conversation_settings (user_id, chat_type, chat_id, muted, pinned_message_id, pinned_preview, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE muted = VALUES(muted), pinned_message_id = VALUES(pinned_message_id), pinned_preview = VALUES(pinned_preview), updated_at = VALUES(updated_at)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([(int)$user['id'], $chatType, $chatId, $muted, $pinnedMessageId, $pinnedPreview, $now]);
        Response::json(['ok' => true, 'data' => ['muted' => (bool)$muted, 'pinned_message_id' => $pinnedMessageId, 'pinned_preview' => $pinnedPreview]]);
    }

    private static function hydrateSettings(array $config, array &$items, int $userId): void
    {
        if (empty($items)) return;
        self::ensureSettingsTable($config);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT chat_type, chat_id, muted, pinned_message_id, pinned_preview FROM ' . $config['db']['prefix'] . 'user_conversation_settings WHERE user_id = ?');
        $stmt->execute([$userId]);
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['chat_type'] . ':' . $row['chat_id']] = $row;
        }
        foreach ($items as &$item) {
            $key = $item['chat_type'] . ':' . $item['id'];
            $row = $settings[$key] ?? null;
            $item['muted'] = $row ? (bool)$row['muted'] : false;
            $item['pinned_message_id'] = $row && $row['pinned_message_id'] !== null ? (int)$row['pinned_message_id'] : null;
            $item['pinned_preview'] = $row['pinned_preview'] ?? null;
        }
    }

    private static function ensureSettingsTable(array $config): void
    {
        static $done = false;
        if ($done) return;
        $pdo = Database::pdo();
        $prefix = $config['db']['prefix'];
        $pdo->exec('CREATE TABLE IF NOT EXISTS `' . $prefix . 'user_conversation_settings` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `chat_type` ENUM(\'direct\',\'group\') NOT NULL,
            `chat_id` BIGINT UNSIGNED NOT NULL,
            `muted` TINYINT(1) NOT NULL DEFAULT 0,
            `pinned_message_id` BIGINT UNSIGNED NULL,
            `pinned_preview` VARCHAR(255) NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_user_chat` (`user_id`, `chat_type`, `chat_id`),
            KEY `idx_user_chat` (`user_id`, `chat_type`, `chat_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $done = true;
    }

    public static function start(array $config): void
    {
        $user = Auth::requireUser($config);
        self::guardWrite($config, 'conversation_start', (int)$user['id']);
        $data = Request::json();
        $otherId = (int)($data['user_id'] ?? 0);
        if ($otherId <= 0 || $otherId === (int)$user['id']) {
            Response::json(['ok' => false, 'error' => 'کاربر نامعتبر است.'], 422);
        }
        $pdo = Database::pdo();
        $checkUser = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'users WHERE id = ? LIMIT 1');
        $checkUser->execute([$otherId]);
        if (!$checkUser->fetch()) {
            Response::json(['ok' => false, 'error' => 'کاربر یافت نشد.'], 404);
        }
        $userOne = min($user['id'], $otherId);
        $userTwo = max($user['id'], $otherId);
        $stmt = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'conversations WHERE user_one_id = ? AND user_two_id = ? LIMIT 1');
        $stmt->execute([$userOne, $userTwo]);
        $row = $stmt->fetch();
        if ($row) {
            Response::json(['ok' => true, 'data' => ['conversation_id' => (int)$row['id']]]);
        }
        $now = date('Y-m-d H:i:s');
        try {
            $insert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'conversations (user_one_id, user_two_id, created_at) VALUES (?, ?, ?)');
            $insert->execute([$userOne, $userTwo, $now]);
            Response::json(['ok' => true, 'data' => ['conversation_id' => (int)$pdo->lastInsertId()]]);
        } catch (PDOException $e) {
            if (!self::isUniqueViolation($e)) {
                throw $e;
            }
            $recover = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'conversations WHERE user_one_id = ? AND user_two_id = ? LIMIT 1');
            $recover->execute([$userOne, $userTwo]);
            $existing = $recover->fetch();
            if ($existing) {
                Response::json(['ok' => true, 'data' => ['conversation_id' => (int)$existing['id']]]);
            }
            throw $e;
        }
    }

    private static function previewText(string $type, string $body, string $filename, int $attachmentsCount = 0): string
    {
        $type = $type ?: 'text';
        if ($type === 'media' || $attachmentsCount > 1) {
            return '📎 ' . ($attachmentsCount > 0 ? $attachmentsCount . ' پیوست' : 'پیوست');
        }
        if ($type === 'text') {
            return $body;
        }
        switch ($type) {
            case 'photo':
                return '📷 عکس';
            case 'video':
                return '🎬 ویدیو';
            case 'voice':
                return '🎤 پیام صوتی';
            case 'file':
                return $filename !== '' ? ('📎 ' . $filename) : '📎 فایل';
            default:
                return $body;
        }
    }

    private static function isUniqueViolation(PDOException $e): bool
    {
        $sqlState = (string)$e->getCode();
        $driverCode = (int)($e->errorInfo[1] ?? 0);
        return $sqlState === '23000' || $driverCode === 1062;
    }

    private static function guardWrite(array $config, string $endpoint, int $userId): void
    {
        self::enforceBodyLimit($config, $endpoint);
        if (RateLimiter::endpointIsLimited($config, $endpoint, $userId)) {
            Response::json(['ok' => false, 'error' => 'درخواست‌ها بیش از حد مجاز است. کمی بعد تلاش کنید.'], 429);
        }
        RateLimiter::hitEndpoint($config, $endpoint, $userId);
    }

    private static function enforceBodyLimit(array $config, string $endpoint): void
    {
        $max = (int)($config['rate_limits']['max_body_bytes'][$endpoint] ?? 0);
        if ($max <= 0) {
            return;
        }
        if (Request::contentLength() > $max) {
            Response::json(['ok' => false, 'error' => 'حجم درخواست بیش از حد مجاز است.'], 413);
        }
    }
}
