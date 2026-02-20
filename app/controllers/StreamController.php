<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\PresenceService;
use App\Core\MessageAttachmentService;
use App\Core\MessageReceiptService;
use App\Core\MessageReactionService;
use App\Core\Request;
use App\Core\Response;
use App\Core\Filesystem;
use App\Core\LogContext;

class StreamController
{
    public static function stream(array $config): void
    {
        $user = self::resolveUser($config);
        if (!$user) {
            Response::json(['ok' => false, 'error' => 'احراز هویت نامعتبر است.'], 401);
        }
        $sseEnabled = (bool)($config['realtime']['sse_enabled'] ?? false);
        if (!$sseEnabled) {
            http_response_code(204);
            exit;
        }
        Response::json(['ok' => false, 'error' => 'SSE در این محیط غیرفعال است.'], 409);
    }

    public static function poll(array $config): void
    {
        $user = self::resolveUser($config);
        if (!$user) {
            Response::json(['ok' => false, 'error' => 'احراز هویت نامعتبر است.'], 401);
        }
        PresenceService::ping($config, (int)$user['id']);

        $ip = LogContext::getIp() ?: 'unknown';
        $userId = (int)$user['id'];
        $perUserLimit = max(1, min(4, (int)($config['realtime']['poll_per_user_concurrency'] ?? 1)));
        $perIpLimit = max(1, min(12, (int)($config['realtime']['poll_per_ip_concurrency'] ?? 4)));

        $userLock = self::acquireConcurrencyLock('user', (string)$userId, $perUserLimit);
        if ($userLock === null) {
            Response::json(['ok' => false, 'error' => 'تعداد درخواست‌های همزمان زیاد است.'], 429);
        }
        $ipLock = self::acquireConcurrencyLock('ip', $ip, $perIpLimit);
        if ($ipLock === null) {
            self::releaseLock($userLock);
            Response::json(['ok' => false, 'error' => 'فشار درخواست از این IP زیاد است.'], 429);
        }

        $status = 200;
        $payload = null;
        try {
            $lastMessageId = max(0, (int)Request::param('last_message_id', 0));
            $lastReceiptId = max(0, (int)Request::param('last_receipt_id', 0));
            $events = self::collectEvents($config, $userId, $lastMessageId, $lastReceiptId, true);

            if (empty($events['messages']) && empty($events['receipts'])) {
                $status = 204;
            } else {
                foreach ($events['messages'] as $msg) {
                    $lastMessageId = max($lastMessageId, (int)$msg['id']);
                }
                foreach ($events['receipts'] as $receipt) {
                    $lastReceiptId = max($lastReceiptId, (int)$receipt['id']);
                }

                $payload = [
                    'ok' => true,
                    'data' => [
                        'messages' => $events['messages'],
                        'receipts' => $events['receipts'],
                        'last_message_id' => $lastMessageId,
                        'last_receipt_id' => $lastReceiptId,
                    ],
                ];
            }
        } finally {
            self::releaseLock($ipLock);
            self::releaseLock($userLock);
        }

        if ($status === 204) {
            http_response_code(204);
            exit;
        }
        Response::json($payload, 200);
    }

    private static function acquireConcurrencyLock(string $scope, string $id, int $limit): ?array
    {
        $dir = rtrim(sys_get_temp_dir(), '/\\') . '/selo-realtime-locks';
        if (!Filesystem::ensureDir($dir)) {
            return null;
        }
        $key = sha1($scope . ':' . $id);
        for ($slot = 0; $slot < $limit; $slot++) {
            $path = $dir . '/' . $scope . '-' . $key . '-' . $slot . '.lock';
            $fh = @fopen($path, 'c');
            if (!is_resource($fh)) {
                continue;
            }
            if (@flock($fh, LOCK_EX | LOCK_NB)) {
                @ftruncate($fh, 0);
                @fwrite($fh, (string)getmypid());
                return ['handle' => $fh];
            }
            @fclose($fh);
        }
        return null;
    }

    private static function releaseLock(?array $lock): void
    {
        if ($lock === null || !isset($lock['handle']) || !is_resource($lock['handle'])) {
            return;
        }
        @flock($lock['handle'], LOCK_UN);
        @fclose($lock['handle']);
    }

    private static function resolveUser(array $config): ?array
    {
        $user = Auth::user($config);
        if ($user) {
            return $user;
        }
        $cookieToken = $_COOKIE['selo_token'] ?? null;
        if (!is_string($cookieToken)) {
            return null;
        }
        $cookieToken = trim($cookieToken);
        if ($cookieToken === '') {
            return null;
        }
        return Auth::userFromToken($config, $cookieToken);
    }

    private static function collectEvents(array $config, int $userId, int $lastMessageId, int $lastReceiptId, bool $includeReceipts = true): array
    {
        $messages = self::fetchMessages($config, $userId, $lastMessageId);
        $receipts = $includeReceipts ? self::fetchReceipts($config, $userId, $lastReceiptId) : [];
        return ['messages' => $messages, 'receipts' => $receipts];
    }

    private static function fetchReceipts(array $config, int $userId, int $lastReceiptId): array
    {
        $pdo = Database::pdo();
        $limit = max(20, min(100, (int)($config['realtime']['stream_receipt_limit'] ?? 80)));
        $sql = 'SELECT mr.id, mr.message_id, mr.user_id, mr.status, mr.created_at
            FROM ' . $config['db']['prefix'] . 'message_receipts mr
            JOIN ' . $config['db']['prefix'] . 'messages m ON m.id = mr.message_id
            WHERE mr.id > ? AND m.sender_id = ?
            ORDER BY mr.id ASC
            LIMIT ' . $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$lastReceiptId, $userId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['message_id'] = (int)$row['message_id'];
            $row['user_id'] = (int)$row['user_id'];
        }
        unset($row);
        return $rows;
    }

    private static function fetchMessages(array $config, int $userId, int $lastMessageId): array
    {
        $pdo = Database::pdo();
        $limit = max(20, min(100, (int)($config['realtime']['stream_message_limit'] ?? 60)));
        $baseSelect = 'SELECT m.id, m.conversation_id, m.group_id, m.client_id, m.type, m.body, m.media_id, m.attachments_count, m.sender_id, m.recipient_id, m.reply_to_message_id, m.created_at,
                su.full_name AS sender_name,
                sup.id AS sender_photo_id,
                ru.id AS reply_id, ru.type AS reply_type, ru.body AS reply_body, ru.sender_id AS reply_sender_id,
                ruser.full_name AS reply_sender_name,
                rmedia.original_name AS reply_media_name,
                mf.file_name AS media_file_name,
                mf.original_name AS media_original_name,
                mf.mime_type AS media_mime_type,
                mf.size_bytes AS media_size_bytes,
                mf.duration AS media_duration,
                mf.width AS media_width,
                mf.height AS media_height,
                mf.thumbnail_name AS media_thumbnail_name,
                mf.type AS media_type
                FROM ' . $config['db']['prefix'] . 'messages m
                JOIN ' . $config['db']['prefix'] . 'users su ON su.id = m.sender_id
                LEFT JOIN ' . $config['db']['prefix'] . 'user_profile_photos sup ON sup.id = su.active_photo_id
                LEFT JOIN ' . $config['db']['prefix'] . 'messages ru ON ru.id = m.reply_to_message_id
                LEFT JOIN ' . $config['db']['prefix'] . 'users ruser ON ruser.id = ru.sender_id
                LEFT JOIN ' . $config['db']['prefix'] . 'media_files rmedia ON rmedia.id = ru.media_id
                LEFT JOIN ' . $config['db']['prefix'] . 'media_files mf ON mf.id = m.media_id';

        $sql = 'SELECT * FROM (
                    ' . $baseSelect . '
                    JOIN ' . $config['db']['prefix'] . 'conversations c ON c.id = m.conversation_id
                    WHERE m.id > ?
                      AND m.is_deleted_for_all = 0
                      AND (c.user_one_id = ? OR c.user_two_id = ?)
                      AND NOT EXISTS (
                        SELECT 1 FROM ' . $config['db']['prefix'] . 'message_deletions md
                        WHERE md.message_id = m.id AND md.user_id = ?
                      )
                    UNION ALL
                    ' . $baseSelect . '
                    JOIN ' . $config['db']['prefix'] . 'group_members gm
                      ON gm.group_id = m.group_id AND gm.user_id = ? AND gm.status = ?
                    WHERE m.id > ?
                      AND m.is_deleted_for_all = 0
                      AND NOT EXISTS (
                        SELECT 1 FROM ' . $config['db']['prefix'] . 'message_deletions md
                        WHERE md.message_id = m.id AND md.user_id = ?
                      )
                ) q
                ORDER BY q.id ASC
                LIMIT ' . $limit;
        $params = [
            $lastMessageId, $userId, $userId, $userId,
            $userId, 'active', $lastMessageId, $userId,
        ];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll();

        foreach ($messages as &$row) {
            $row['id'] = (int)$row['id'];
            $row['conversation_id'] = $row['conversation_id'] !== null ? (int)$row['conversation_id'] : null;
            $row['group_id'] = $row['group_id'] !== null ? (int)$row['group_id'] : null;
            $row['sender_id'] = (int)$row['sender_id'];
            $row['recipient_id'] = $row['recipient_id'] !== null ? (int)$row['recipient_id'] : null;
            $row['media_id'] = $row['media_id'] !== null ? (int)$row['media_id'] : null;
            $row['attachments_count'] = (int)$row['attachments_count'];
            $row['client_id'] = $row['client_id'] !== null && $row['client_id'] !== '' ? $row['client_id'] : null;
            $row['sender_photo_id'] = $row['sender_photo_id'] !== null ? (int)$row['sender_photo_id'] : null;
            if ($row['reply_id'] !== null) {
                $row['reply_id'] = (int)$row['reply_id'];
            }
            if ($row['reply_sender_id'] !== null) {
                $row['reply_sender_id'] = (int)$row['reply_sender_id'];
            }

            if (!empty($row['media_id'])) {
                $row['media'] = [
                    'id' => (int)$row['media_id'],
                    'type' => $row['media_type'],
                    'file_name' => $row['media_file_name'],
                    'original_name' => $row['media_original_name'],
                    'mime_type' => $row['media_mime_type'],
                    'size_bytes' => (int)$row['media_size_bytes'],
                    'duration' => $row['media_duration'] !== null ? (int)$row['media_duration'] : null,
                    'width' => $row['media_width'] !== null ? (int)$row['media_width'] : null,
                    'height' => $row['media_height'] !== null ? (int)$row['media_height'] : null,
                    'thumbnail_name' => $row['media_thumbnail_name'],
                ];
            } else {
                $row['media'] = null;
            }

            unset($row['media_type'], $row['media_file_name'], $row['media_original_name'], $row['media_mime_type'], $row['media_size_bytes'], $row['media_duration'], $row['media_width'], $row['media_height'], $row['media_thumbnail_name']);
        }
        unset($row);

        $messages = MessageAttachmentService::hydrate($config, $messages);
        $hydrateMax = max(10, min(100, (int)($config['realtime']['stream_hydrate_max_messages'] ?? 40)));
        if (count($messages) <= $hydrateMax) {
            $messages = MessageReactionService::hydrate($config, $messages, $userId);
            $messages = MessageReceiptService::hydrate($config, $messages, $userId);
        } else {
            foreach ($messages as &$msg) {
                $msg['reactions'] = [];
                $msg['current_user_reaction'] = null;
                if (!isset($msg['receipt'])) {
                    $msg['receipt'] = null;
                }
            }
            unset($msg);
        }

        return $messages;
    }
}
