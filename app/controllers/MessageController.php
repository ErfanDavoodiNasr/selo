<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\MessageReactionService;
use App\Core\Request;
use App\Core\Response;
use App\Core\RateLimiter;
use App\Core\Validator;

class MessageController
{
    public static function list(array $config): void
    {
        $user = Auth::requireUser($config);
        $conversationId = (int) Request::param('conversation_id', 0);
        if ($conversationId <= 0) {
            Response::json(['ok' => false, 'error' => 'گفتگو نامعتبر است.'], 422);
        }
        $limit = (int) Request::param('limit', 30);
        $limit = max(1, min(100, $limit));
        $beforeId = (int) Request::param('before_id', 0);

        $pdo = Database::pdo();
        $check = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'conversations WHERE id = ? AND (user_one_id = ? OR user_two_id = ?) LIMIT 1');
        $check->execute([$conversationId, $user['id'], $user['id']]);
        if (!$check->fetch()) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }

        $params = [$conversationId, $user['id']];
        $beforeSql = '';
        if ($beforeId > 0) {
            $beforeSql = ' AND m.id < ?';
            $params[] = $beforeId;
        }

        $sql = 'SELECT m.id, m.type, m.body, m.media_id, m.sender_id, m.recipient_id, m.reply_to_message_id, m.created_at,
                su.full_name AS sender_name,
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
                LEFT JOIN ' . $config['db']['prefix'] . 'messages ru ON ru.id = m.reply_to_message_id
                LEFT JOIN ' . $config['db']['prefix'] . 'users ruser ON ruser.id = ru.sender_id
                LEFT JOIN ' . $config['db']['prefix'] . 'media_files rmedia ON rmedia.id = ru.media_id
                LEFT JOIN ' . $config['db']['prefix'] . 'media_files mf ON mf.id = m.media_id
                WHERE m.conversation_id = ?
                AND m.is_deleted_for_all = 0
                AND NOT EXISTS (
                    SELECT 1 FROM ' . $config['db']['prefix'] . 'message_deletions md
                    WHERE md.message_id = m.id AND md.user_id = ?
                )' . $beforeSql . '
                ORDER BY m.id DESC
                LIMIT ' . $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $messages = array_reverse($stmt->fetchAll());
        foreach ($messages as &$row) {
            $row['id'] = (int)$row['id'];
            $row['sender_id'] = (int)$row['sender_id'];
            $row['recipient_id'] = (int)$row['recipient_id'];
            $row['media_id'] = $row['media_id'] !== null ? (int)$row['media_id'] : null;
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
        $messages = MessageReactionService::hydrate($config, $messages, (int)$user['id']);
        Response::json(['ok' => true, 'data' => $messages]);
    }

    public static function send(array $config): void
    {
        $user = Auth::requireUser($config);
        $data = Request::json();
        $conversationId = (int)($data['conversation_id'] ?? 0);
        $type = strtolower(trim($data['type'] ?? 'text'));
        $body = trim($data['body'] ?? '');
        $mediaId = isset($data['media_id']) ? (int)$data['media_id'] : null;
        $replyTo = isset($data['reply_to_message_id']) ? (int)$data['reply_to_message_id'] : null;

        if ($conversationId <= 0) {
            Response::json(['ok' => false, 'error' => 'گفتگو نامعتبر است.'], 422);
        }
        $allowedTypes = ['text', 'voice', 'file', 'photo', 'video'];
        if (!in_array($type, $allowedTypes, true)) {
            Response::json(['ok' => false, 'error' => 'نوع پیام نامعتبر است.'], 422);
        }
        if ($type === 'text') {
            if (!Validator::messageBody($body)) {
                Response::json(['ok' => false, 'error' => 'متن پیام معتبر نیست.'], 422);
            }
        } else {
            if ($mediaId === null || $mediaId <= 0) {
                Response::json(['ok' => false, 'error' => 'فایل پیام ارسال نشده است.'], 422);
            }
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT user_one_id, user_two_id FROM ' . $config['db']['prefix'] . 'conversations WHERE id = ? LIMIT 1');
        $stmt->execute([$conversationId]);
        $conv = $stmt->fetch();
        if (!$conv || ($conv['user_one_id'] != $user['id'] && $conv['user_two_id'] != $user['id'])) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }
        $recipientId = ($conv['user_one_id'] == $user['id']) ? $conv['user_two_id'] : $conv['user_one_id'];

        if ($type !== 'text') {
            $mediaStmt = $pdo->prepare('SELECT id, type FROM ' . $config['db']['prefix'] . 'media_files WHERE id = ? AND user_id = ? LIMIT 1');
            $mediaStmt->execute([$mediaId, $user['id']]);
            $media = $mediaStmt->fetch();
            if (!$media) {
                Response::json(['ok' => false, 'error' => 'فایل یافت نشد.'], 404);
            }
            if ($media['type'] !== $type) {
                Response::json(['ok' => false, 'error' => 'نوع فایل با پیام مطابقت ندارد.'], 422);
            }
        }

        if ($replyTo) {
            $replyCheck = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'messages WHERE id = ? AND conversation_id = ? LIMIT 1');
            $replyCheck->execute([$replyTo, $conversationId]);
            if (!$replyCheck->fetch()) {
                Response::json(['ok' => false, 'error' => 'پیام مرجع یافت نشد.'], 422);
            }
        }

        $now = date('Y-m-d H:i:s');
        $bodyValue = ($type === 'text') ? $body : null;
        $insert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'messages (conversation_id, sender_id, recipient_id, type, body, media_id, reply_to_message_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([$conversationId, $user['id'], $recipientId, $type, $bodyValue, $mediaId, $replyTo, $now]);
        $messageId = (int)$pdo->lastInsertId();

        $update = $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'conversations SET last_message_id = ?, last_message_at = ? WHERE id = ?');
        $update->execute([$messageId, $now, $conversationId]);

        Response::json(['ok' => true, 'data' => ['message_id' => $messageId]]);
    }

    public static function deleteForMe(array $config): void
    {
        $user = Auth::requireUser($config);
        $data = Request::json();
        $messageId = (int)($data['message_id'] ?? 0);
        if ($messageId <= 0) {
            Response::json(['ok' => false, 'error' => 'پیام نامعتبر است.'], 422);
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT m.id
            FROM ' . $config['db']['prefix'] . 'messages m
            LEFT JOIN ' . $config['db']['prefix'] . 'conversations c ON c.id = m.conversation_id
            LEFT JOIN ' . $config['db']['prefix'] . 'group_members gm ON gm.group_id = m.group_id AND gm.user_id = ? AND gm.status = ?
            WHERE m.id = ?
              AND (
                (m.conversation_id IS NOT NULL AND (c.user_one_id = ? OR c.user_two_id = ?))
                OR (m.group_id IS NOT NULL AND gm.user_id IS NOT NULL)
              )
            LIMIT 1');
        $stmt->execute([$user['id'], 'active', $messageId, $user['id'], $user['id']]);
        if (!$stmt->fetch()) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }
        $now = date('Y-m-d H:i:s');
        $insert = $pdo->prepare('INSERT IGNORE INTO ' . $config['db']['prefix'] . 'message_deletions (message_id, user_id, deleted_at) VALUES (?, ?, ?)');
        $insert->execute([$messageId, $user['id'], $now]);
        Response::json(['ok' => true]);
    }

    public static function deleteForEveryone(array $config): void
    {
        $user = Auth::requireUser($config);
        $data = Request::json();
        $messageId = (int)($data['message_id'] ?? 0);
        if ($messageId <= 0) {
            Response::json(['ok' => false, 'error' => 'پیام نامعتبر است.'], 422);
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT m.id, m.conversation_id, m.group_id, m.sender_id, m.recipient_id,
                g.owner_user_id,
                gm.user_id AS member_id
            FROM ' . $config['db']['prefix'] . 'messages m
            LEFT JOIN ' . $config['db']['prefix'] . 'groups g ON g.id = m.group_id
            LEFT JOIN ' . $config['db']['prefix'] . 'group_members gm ON gm.group_id = m.group_id AND gm.user_id = ? AND gm.status = ?
            WHERE m.id = ? LIMIT 1');
        $stmt->execute([$user['id'], 'active', $messageId]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }
        $allowed = false;
        if ($row['conversation_id'] !== null) {
            if ((int)$row['sender_id'] === (int)$user['id'] || (int)$row['recipient_id'] === (int)$user['id']) {
                $allowed = true;
            }
        } elseif ($row['group_id'] !== null) {
            if (!empty($row['member_id']) && (((int)$row['sender_id'] === (int)$user['id']) || ((int)$row['owner_user_id'] === (int)$user['id']))) {
                $allowed = true;
            }
        }
        if (!$allowed) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }
        $update = $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'messages SET is_deleted_for_all = 1 WHERE id = ?');
        $update->execute([$messageId]);
        $pdo->prepare('DELETE FROM ' . $config['db']['prefix'] . 'message_reactions WHERE message_id = ?')->execute([$messageId]);
        Response::json(['ok' => true]);
    }

    public static function react(array $config, int $messageId): void
    {
        $user = Auth::requireUser($config);
        $data = Request::json();
        $emoji = trim((string)($data['emoji'] ?? ''));
        if ($messageId <= 0) {
            Response::json(['ok' => false, 'error' => 'پیام نامعتبر است.'], 422);
        }
        if (!MessageReactionService::isAllowed($emoji)) {
            Response::json(['ok' => false, 'error' => 'ایموجی نامعتبر است.'], 422);
        }

        $pdo = Database::pdo();
        $message = self::requireMessageAccess($pdo, $config, $messageId, (int)$user['id']);
        if (!$message) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $identifier = 'reaction:' . $user['id'];
        if (RateLimiter::tooManyAttemptsCustom($ip, $identifier, $config, 20, 1, 1)) {
            Response::json(['ok' => false, 'error' => 'تعداد درخواست‌ها زیاد است. کمی بعد تلاش کنید.'], 429);
        }
        RateLimiter::hitCustom($ip, $identifier, $config, 20, 1, 1);

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'message_reactions (message_id, user_id, reaction_emoji, created_at, updated_at)\n            VALUES (?, ?, ?, ?, ?)\n            ON DUPLICATE KEY UPDATE reaction_emoji = VALUES(reaction_emoji), updated_at = VALUES(updated_at)');
        $stmt->execute([$messageId, $user['id'], $emoji, $now, $now]);

        Response::json(['ok' => true]);
    }

    public static function removeReaction(array $config, int $messageId): void
    {
        $user = Auth::requireUser($config);
        if ($messageId <= 0) {
            Response::json(['ok' => false, 'error' => 'پیام نامعتبر است.'], 422);
        }
        $pdo = Database::pdo();
        $message = self::requireMessageAccess($pdo, $config, $messageId, (int)$user['id']);
        if (!$message) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }
        $stmt = $pdo->prepare('DELETE FROM ' . $config['db']['prefix'] . 'message_reactions WHERE message_id = ? AND user_id = ?');
        $stmt->execute([$messageId, $user['id']]);
        Response::json(['ok' => true]);
    }

    public static function reactions(array $config, int $messageId): void
    {
        $user = Auth::requireUser($config);
        if ($messageId <= 0) {
            Response::json(['ok' => false, 'error' => 'پیام نامعتبر است.'], 422);
        }
        $pdo = Database::pdo();
        $message = self::requireMessageAccess($pdo, $config, $messageId, (int)$user['id']);
        if (!$message) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }
        $emoji = Request::param('emoji', null);
        $limit = (int)Request::param('limit', 20);
        $result = MessageReactionService::summary($config, $messageId, (int)$user['id'], $emoji, $limit);
        if (!$result['ok']) {
            Response::json(['ok' => false, 'error' => $result['error']], 422);
        }
        Response::json($result);
    }

    private static function requireMessageAccess($pdo, array $config, int $messageId, int $userId): ?array
    {
        $stmt = $pdo->prepare('SELECT m.id, m.conversation_id, m.group_id, m.is_deleted_for_all,
                c.user_one_id, c.user_two_id,
                gm.user_id AS member_id
            FROM ' . $config['db']['prefix'] . 'messages m
            LEFT JOIN ' . $config['db']['prefix'] . 'conversations c ON c.id = m.conversation_id
            LEFT JOIN ' . $config['db']['prefix'] . 'group_members gm ON gm.group_id = m.group_id AND gm.user_id = ? AND gm.status = ?
            WHERE m.id = ? LIMIT 1');
        $stmt->execute([$userId, 'active', $messageId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        if ((int)$row['is_deleted_for_all'] === 1) {
            return null;
        }
        $del = $pdo->prepare('SELECT 1 FROM ' . $config['db']['prefix'] . 'message_deletions WHERE message_id = ? AND user_id = ? LIMIT 1');
        $del->execute([$messageId, $userId]);
        if ($del->fetch()) {
            return null;
        }
        if ($row['conversation_id'] !== null) {
            if ((int)$row['user_one_id'] !== $userId && (int)$row['user_two_id'] !== $userId) {
                return null;
            }
        } elseif ($row['group_id'] !== null) {
            if (empty($row['member_id'])) {
                return null;
            }
        } else {
            return null;
        }
        return $row;
    }
}
