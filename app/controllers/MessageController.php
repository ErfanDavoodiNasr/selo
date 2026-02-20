<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\MessageAttachmentService;
use App\Core\MessageMediaService;
use App\Core\MessageReceiptService;
use App\Core\MessageReactionService;
use App\Core\MediaLifecycleService;
use App\Core\LastSeenService;
use App\Core\Request;
use App\Core\Response;
use App\Core\RateLimiter;
use App\Core\Validator;
use App\Core\Logger;
use App\Core\LogContext;

class MessageController
{
    public static function list(array $config): void
    {
        $user = Auth::requireUser($config);
        LastSeenService::touch($config, (int)$user['id']);
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

        $sql = 'SELECT m.id, m.conversation_id, m.group_id, m.client_id, m.type, m.body, m.media_id, m.attachments_count, m.sender_id, m.recipient_id, m.reply_to_message_id, m.created_at,
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
            $row['conversation_id'] = $row['conversation_id'] !== null ? (int)$row['conversation_id'] : null;
            $row['group_id'] = $row['group_id'] !== null ? (int)$row['group_id'] : null;
            $row['sender_id'] = (int)$row['sender_id'];
            $row['recipient_id'] = (int)$row['recipient_id'];
            $row['attachments_count'] = (int)$row['attachments_count'];
            $row['client_id'] = $row['client_id'] !== null && $row['client_id'] !== '' ? $row['client_id'] : null;
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
        $messages = MessageAttachmentService::hydrate($config, $messages);
        $messages = MessageReceiptService::hydrate($config, $messages, (int)$user['id']);
        Response::json(['ok' => true, 'data' => $messages]);
    }

    public static function send(array $config): void
    {
        $user = Auth::requireUser($config);
        self::guardWrite($config, 'send', (int)$user['id']);

        LastSeenService::touch($config, (int)$user['id']);
        $data = Request::json();
        $conversationId = (int)($data['conversation_id'] ?? 0);
        $typeHint = strtolower(trim($data['type'] ?? 'text'));
        $body = trim($data['body'] ?? '');
        $clientId = trim((string)($data['client_id'] ?? ''));
        $mediaIds = MessageMediaService::normalizeMediaIds($data['media_ids'] ?? [], isset($data['media_id']) ? (int)$data['media_id'] : null);
        self::enforceArrayLimit($config, 'send_media_ids', $mediaIds);
        $replyTo = isset($data['reply_to_message_id']) ? (int)$data['reply_to_message_id'] : null;

        if ($conversationId <= 0) {
            Response::json(['ok' => false, 'error' => 'گفتگو نامعتبر است.'], 422);
        }
        $allowedTypes = ['text', 'voice', 'file', 'photo', 'video', 'media'];
        if (!in_array($typeHint, $allowedTypes, true)) {
            Response::json(['ok' => false, 'error' => 'نوع پیام نامعتبر است.'], 422);
        }

        if ($clientId !== '') {
            if (strlen($clientId) > 36 || !preg_match('/^[a-zA-Z0-9\\-]+$/', $clientId)) {
                Response::json(['ok' => false, 'error' => 'شناسه پیام نامعتبر است.'], 422);
            }
        }

        $pdo = Database::pdo();

        // Client-side de-duplication for retries.
        if ($clientId !== '') {
            $dupStmt = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'messages WHERE sender_id = ? AND client_id = ? AND conversation_id = ? LIMIT 1');
            $dupStmt->execute([$user['id'], $clientId, $conversationId]);
            $dup = $dupStmt->fetch();
            if ($dup) {
                Response::json(['ok' => true, 'data' => ['message_id' => (int)$dup['id'], 'deduped' => true]]);
            }
        }

        $stmt = $pdo->prepare('SELECT user_one_id, user_two_id FROM ' . $config['db']['prefix'] . 'conversations WHERE id = ? LIMIT 1');
        $stmt->execute([$conversationId]);
        $conv = $stmt->fetch();
        if (!$conv || ($conv['user_one_id'] != $user['id'] && $conv['user_two_id'] != $user['id'])) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }
        $recipientId = ($conv['user_one_id'] == $user['id']) ? $conv['user_two_id'] : $conv['user_one_id'];

        $hasMedia = !empty($mediaIds);
        $maxAttachments = (int)($config['uploads']['max_files_per_request'] ?? 10);
        if ($hasMedia && count($mediaIds) > $maxAttachments) {
            Response::json(['ok' => false, 'error' => 'تعداد پیوست‌ها بیش از حد مجاز است.'], 422);
        }
        if (!$hasMedia) {
            if (!Validator::messageBody($body)) {
                Response::json(['ok' => false, 'error' => 'متن پیام معتبر نیست.'], 422);
            }
        } else {
            if ($body !== '' && !Validator::messageBody($body)) {
                Response::json(['ok' => false, 'error' => 'متن پیام معتبر نیست.'], 422);
            }
        }

        $mediaMap = MessageMediaService::loadUserMedia($config, (int)$user['id'], $mediaIds);
        if ($hasMedia && count($mediaMap) !== count($mediaIds)) {
            Response::json(['ok' => false, 'error' => 'فایل یافت نشد.'], 404);
        }

        $primaryMediaId = $hasMedia ? (int)$mediaIds[0] : null;
        $messageType = 'text';
        if ($hasMedia) {
            if (count($mediaIds) === 1) {
                $messageType = $mediaMap[$primaryMediaId] ?? $typeHint;
                if ($typeHint !== 'media' && $typeHint !== 'text' && $typeHint !== $messageType) {
                    Response::json(['ok' => false, 'error' => 'نوع فایل با پیام مطابقت ندارد.'], 422);
                }
            } else {
                $messageType = 'media';
            }
        } elseif ($typeHint !== 'text') {
            Response::json(['ok' => false, 'error' => 'فایل پیام ارسال نشده است.'], 422);
        }

        if ($replyTo) {
            $replyCheck = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'messages WHERE id = ? AND conversation_id = ? LIMIT 1');
            $replyCheck->execute([$replyTo, $conversationId]);
            if (!$replyCheck->fetch()) {
                Response::json(['ok' => false, 'error' => 'پیام مرجع یافت نشد.'], 422);
            }
        }

        $now = date('Y-m-d H:i:s');
        $bodyValue = ($hasMedia || $body !== '') ? $body : null;
        $attachmentsCount = $hasMedia ? count($mediaIds) : 0;
        try {
            $pdo->beginTransaction();
            $insert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'messages (conversation_id, sender_id, recipient_id, client_id, type, body, media_id, attachments_count, reply_to_message_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([$conversationId, $user['id'], $recipientId, $clientId !== '' ? $clientId : null, $messageType, $bodyValue, $primaryMediaId, $attachmentsCount, $replyTo, $now]);
            $messageId = (int)$pdo->lastInsertId();

            if ($hasMedia) {
                $attInsert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'message_attachments (message_id, media_id, sort_order, created_at) VALUES (?, ?, ?, ?)');
                $sort = 0;
                foreach ($mediaIds as $mid) {
                    $attInsert->execute([$messageId, $mid, $sort, $now]);
                    $sort++;
                }
                MediaLifecycleService::markAttached($config, $mediaIds);
            }

            $update = $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'conversations
                SET last_message_at = IF(last_message_id IS NULL OR last_message_id < ?, ?, last_message_at),
                    last_message_id = IF(last_message_id IS NULL OR last_message_id < ?, ?, last_message_id)
                WHERE id = ?');
            $update->execute([$messageId, $now, $messageId, $messageId, $conversationId]);

            $receiptInsert = $pdo->prepare('INSERT IGNORE INTO ' . $config['db']['prefix'] . 'message_receipts (message_id, user_id, status, created_at) VALUES (?, ?, ?, ?)');
            $receiptInsert->execute([$messageId, (int)$recipientId, 'delivered', $now]);
            $pdo->commit();
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($clientId !== '' && self::isUniqueViolation($e)) {
                $dupStmt = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'messages WHERE sender_id = ? AND client_id = ? AND conversation_id = ? LIMIT 1');
                $dupStmt->execute([$user['id'], $clientId, $conversationId]);
                $dup = $dupStmt->fetch();
                if ($dup) {
                    Response::json(['ok' => true, 'data' => ['message_id' => (int)$dup['id'], 'deduped' => true]]);
                }
            }
            Response::json(['ok' => false, 'error' => 'ارسال پیام ناموفق بود.'], 500);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['ok' => false, 'error' => 'ارسال پیام ناموفق بود.'], 500);
        }

        Response::json(['ok' => true, 'data' => ['message_id' => $messageId]]);
    }

    public static function ack(array $config): void
    {
        $user = Auth::requireUser($config);
        self::enforceBodyLimit($config, 'ack');
        if (RateLimiter::endpointIsLimited($config, 'ack', (int)$user['id'])) {
            Response::json(['ok' => false, 'error' => 'درخواست‌ها بیش از حد مجاز است. کمی بعد تلاش کنید.'], 429);
        }
        RateLimiter::hitEndpoint($config, 'ack', (int)$user['id']);

        $data = Request::json();
        $messageIds = $data['message_ids'] ?? [];
        if (!is_array($messageIds)) {
            Response::json(['ok' => false, 'error' => 'لیست پیام نامعتبر است.'], 422);
        }
        self::enforceArrayLimit($config, 'ack_message_ids', $messageIds);
        $status = strtolower(trim($data['status'] ?? ''));
        if (!in_array($status, ['delivered', 'seen'], true)) {
            Response::json(['ok' => false, 'error' => 'وضعیت نامعتبر است.'], 422);
        }

        $ids = [];
        foreach ($messageIds as $id) {
            $intId = (int)$id;
            if ($intId > 0) {
                $ids[] = $intId;
            }
        }
        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            Response::json(['ok' => true, 'data' => ['acknowledged' => []]]);
        }

        $pdo = Database::pdo();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = [$user['id'], 'active'];
        $params = array_merge($params, $ids);
        $params[] = $user['id'];
        $params[] = $user['id'];
        $params[] = $user['id'];

        $sql = 'SELECT m.id
            FROM ' . $config['db']['prefix'] . 'messages m
            LEFT JOIN ' . $config['db']['prefix'] . 'group_members gm
                ON gm.group_id = m.group_id AND gm.user_id = ? AND gm.status = ?
            WHERE m.id IN (' . $placeholders . ')
              AND m.sender_id != ?
              AND m.is_deleted_for_all = 0
              AND (
                (m.conversation_id IS NOT NULL AND m.recipient_id = ?)
                OR (m.group_id IS NOT NULL AND gm.user_id IS NOT NULL)
              )
              AND NOT EXISTS (
                SELECT 1 FROM ' . $config['db']['prefix'] . 'message_deletions md
                WHERE md.message_id = m.id AND md.user_id = ?
              )';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $validIds = [];
        foreach ($rows as $row) {
            $validIds[] = (int)$row['id'];
        }
        if (empty($validIds)) {
            Response::json(['ok' => true, 'data' => ['acknowledged' => []]]);
        }

        $now = date('Y-m-d H:i:s');
        $insert = $pdo->prepare('INSERT IGNORE INTO ' . $config['db']['prefix'] . 'message_receipts (message_id, user_id, status, created_at) VALUES (?, ?, ?, ?)');

        if ($status === 'seen') {
            foreach ($validIds as $mid) {
                $insert->execute([$mid, $user['id'], 'delivered', $now]);
            }
        }
        foreach ($validIds as $mid) {
            $insert->execute([$mid, $user['id'], $status, $now]);
        }

        Response::json(['ok' => true, 'data' => ['acknowledged' => $validIds]]);
    }

    public static function edit(array $config): void
    {
        $user = Auth::requireUser($config);
        self::guardWrite($config, 'send', (int)$user['id']);
        LastSeenService::touch($config, (int)$user['id']);

        $data = Request::json();
        $messageId = (int)($data['message_id'] ?? 0);
        $body = trim((string)($data['body'] ?? ''));
        if ($messageId <= 0) {
            Response::json(['ok' => false, 'error' => 'پیام نامعتبر است.'], 422);
        }
        if ($body === '' || !Validator::messageBody($body)) {
            Response::json(['ok' => false, 'error' => 'متن پیام معتبر نیست.'], 422);
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, sender_id, type, is_deleted_for_all FROM ' . $config['db']['prefix'] . 'messages WHERE id = ? LIMIT 1');
        $stmt->execute([$messageId]);
        $msg = $stmt->fetch();
        if (!$msg || (int)$msg['sender_id'] !== (int)$user['id']) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }
        if ((int)$msg['is_deleted_for_all'] === 1) {
            Response::json(['ok' => false, 'error' => 'این پیام قابل ویرایش نیست.'], 422);
        }
        if ((string)$msg['type'] !== 'text') {
            Response::json(['ok' => false, 'error' => 'فقط پیام متنی قابل ویرایش است.'], 422);
        }

        $update = $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'messages SET body = ? WHERE id = ? AND sender_id = ? AND type = ? AND is_deleted_for_all = 0');
        $update->execute([$body, $messageId, (int)$user['id'], 'text']);

        Response::json([
            'ok' => true,
            'data' => [
                'message_id' => $messageId,
                'body' => $body,
            ],
        ]);
    }

    public static function unreadCount(array $config): void
    {
        $user = Auth::requireUser($config);
        $counts = MessageReceiptService::unreadCounts($config, (int)$user['id']);
        Response::json([
            'ok' => true,
            'data' => [
                'total_unread' => (int)$counts['total'],
                'by_conversation' => $counts['by_conversation'],
            ],
        ]);
    }

    public static function markRead(array $config): void
    {
        $user = Auth::requireUser($config);
        self::guardWrite($config, 'mark_read', (int)$user['id']);
        $data = Request::json();
        $conversationId = (int)($data['conversation_id'] ?? 0);
        $upTo = isset($data['up_to_message_id']) ? (int)$data['up_to_message_id'] : null;
        if ($conversationId <= 0) {
            Response::json(['ok' => false, 'error' => 'گفتگو نامعتبر است.'], 422);
        }

        $pdo = Database::pdo();
        $check = $pdo->prepare('SELECT user_one_id, user_two_id FROM ' . $config['db']['prefix'] . 'conversations WHERE id = ? LIMIT 1');
        $check->execute([$conversationId]);
        $conv = $check->fetch();
        if (!$conv || ($conv['user_one_id'] != $user['id'] && $conv['user_two_id'] != $user['id'])) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }

        $marked = MessageReceiptService::markReadForConversation($config, (int)$user['id'], $conversationId, $upTo);
        $counts = MessageReceiptService::unreadCounts($config, (int)$user['id']);
        Response::json([
            'ok' => true,
            'data' => [
                'marked' => $marked,
                'total_unread' => (int)$counts['total'],
                'by_conversation' => $counts['by_conversation'],
            ],
        ]);
    }

    public static function status(array $config): void
    {
        $user = Auth::requireUser($config);
        $conversationId = (int)Request::param('conversation_id', 0);
        if ($conversationId <= 0) {
            Response::json(['ok' => false, 'error' => 'گفتگو نامعتبر است.'], 422);
        }
        $sinceId = (int)Request::param('since_id', 0);

        $pdo = Database::pdo();
        $check = $pdo->prepare('SELECT user_one_id, user_two_id FROM ' . $config['db']['prefix'] . 'conversations WHERE id = ? LIMIT 1');
        $check->execute([$conversationId]);
        $conv = $check->fetch();
        if (!$conv || ($conv['user_one_id'] != $user['id'] && $conv['user_two_id'] != $user['id'])) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }
        $otherId = ($conv['user_one_id'] == $user['id']) ? (int)$conv['user_two_id'] : (int)$conv['user_one_id'];
        $data = MessageReceiptService::statusForConversation($config, (int)$user['id'], $otherId, $conversationId, $sinceId);
        Response::json(['ok' => true, 'data' => $data]);
    }

    public static function deleteForMe(array $config): void
    {
        $user = Auth::requireUser($config);
        self::guardWrite($config, 'delete_for_me', (int)$user['id']);
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
        self::guardWrite($config, 'delete_for_everyone', (int)$user['id']);
        $data = Request::json();
        $messageId = (int)($data['message_id'] ?? 0);
        if ($messageId <= 0) {
            Response::json(['ok' => false, 'error' => 'پیام نامعتبر است.'], 422);
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT m.id, m.conversation_id, m.group_id, m.sender_id, m.recipient_id, m.media_id,
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
            // In private chats, "delete for everyone" is only allowed for the sender.
            if ((int)$row['sender_id'] === (int)$user['id']) {
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

        $mediaIds = [];
        if (!empty($row['media_id'])) {
            $mediaIds[] = (int)$row['media_id'];
        }
        $attStmt = $pdo->prepare('SELECT media_id FROM ' . $config['db']['prefix'] . 'message_attachments WHERE message_id = ?');
        $attStmt->execute([$messageId]);
        foreach ($attStmt->fetchAll() as $att) {
            $mid = (int)($att['media_id'] ?? 0);
            if ($mid > 0) {
                $mediaIds[] = $mid;
            }
        }
        $mediaIds = array_values(array_unique($mediaIds));

        try {
            $pdo->beginTransaction();
            $update = $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'messages
                SET is_deleted_for_all = 1, body = NULL, media_id = NULL, attachments_count = 0
                WHERE id = ?');
            $update->execute([$messageId]);
            $pdo->prepare('DELETE FROM ' . $config['db']['prefix'] . 'message_attachments WHERE message_id = ?')->execute([$messageId]);
            $pdo->prepare('DELETE FROM ' . $config['db']['prefix'] . 'message_reactions WHERE message_id = ?')->execute([$messageId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['ok' => false, 'error' => 'حذف پیام ناموفق بود.'], 500);
        }

        foreach ($mediaIds as $mediaId) {
            MediaLifecycleService::deleteIfUnreferenced($config, $mediaId);
        }
        Response::json(['ok' => true]);
    }

    public static function react(array $config, int $messageId): void
    {
        $user = Auth::requireUser($config);
        self::guardWrite($config, 'react', (int)$user['id']);
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

        $reactionCfg = $config['reactions'] ?? [];
        $cooldownSeconds = (int)($reactionCfg['cooldown_seconds'] ?? 2);
        $rateCfg = $reactionCfg['rate_limit'] ?? [];
        $maxAttempts = (int)($rateCfg['max_attempts'] ?? 12);
        $windowMinutes = (int)($rateCfg['window_minutes'] ?? 1);
        $lockMinutes = (int)($rateCfg['lock_minutes'] ?? 1);

        $ip = LogContext::getIp() ?: 'unknown';
        $identifier = 'reaction:' . $user['id'] . ':' . $messageId;
        if (RateLimiter::tooManyReactionAttempts($ip, $identifier, $config, $maxAttempts, $windowMinutes, $lockMinutes)) {
            Response::json(['ok' => false, 'error' => 'تعداد درخواست‌ها زیاد است. کمی بعد تلاش کنید.'], 429);
        }
        RateLimiter::hitReactionAttempt($ip, $identifier, $config, $maxAttempts, $windowMinutes, $lockMinutes);

        if ($cooldownSeconds > 0) {
            $cooldownStmt = $pdo->prepare('SELECT reaction_emoji, updated_at FROM ' . $config['db']['prefix'] . 'message_reactions WHERE message_id = ? AND user_id = ? LIMIT 1');
            $cooldownStmt->execute([$messageId, $user['id']]);
            $cooldownRow = $cooldownStmt->fetch();
            if ($cooldownRow && isset($cooldownRow['reaction_emoji']) && $cooldownRow['reaction_emoji'] === $emoji) {
                Response::json(['ok' => true]);
            }
            if ($cooldownRow && !empty($cooldownRow['updated_at'])) {
                $lastUpdate = strtotime($cooldownRow['updated_at']);
                if ($lastUpdate && (time() - $lastUpdate) < $cooldownSeconds) {
                    Response::json(['ok' => false, 'error' => 'لطفاً کمی صبر کنید.'], 429);
                }
            }
        }

        $now = date('Y-m-d H:i:s');
        try {
            $stmt = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'message_reactions (message_id, user_id, reaction_emoji, created_at, updated_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE reaction_emoji = VALUES(reaction_emoji), updated_at = VALUES(updated_at)');
            $stmt->execute([$messageId, $user['id'], $emoji, $now, $now]);
        } catch (\PDOException $e) {
            $msg = $e->getMessage();
            $code = $e->getCode();
            $info = $e->errorInfo ?? [];
            $driverCode = isset($info[1]) ? (int)$info[1] : 0;
            Logger::error('reaction_failed', ['message_id' => $messageId, 'user_id' => (int)$user['id'], 'error' => $msg], 'reaction');
            if ($driverCode === 1366 || strpos($msg, 'Incorrect string value') !== false) {
                Response::json(['ok' => false, 'error' => 'پایگاه داده باید با utf8mb4 تنظیم شود.'], 500);
            }
            if ($driverCode === 1146 || strpos($msg, 'doesn\'t exist') !== false) {
                Response::json(['ok' => false, 'error' => 'جدول واکنش‌ها یافت نشد. لطفاً migration را اجرا کنید.'], 500);
            }
            if ($driverCode === 1452 || strpos($msg, 'foreign key constraint fails') !== false) {
                Response::json(['ok' => false, 'error' => 'پیام یا کاربر نامعتبر است.'], 422);
            }
            Response::json(['ok' => false, 'error' => 'خطا در ذخیره واکنش.'], 500);
        }

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

    private static function enforceArrayLimit(array $config, string $key, array $items): void
    {
        $max = (int)($config['rate_limits']['max_array_items'][$key] ?? 0);
        if ($max <= 0) {
            return;
        }
        if (count($items) > $max) {
            Response::json(['ok' => false, 'error' => 'تعداد آیتم‌های درخواست بیش از حد مجاز است.'], 422);
        }
    }

    private static function guardWrite(array $config, string $endpoint, int $userId): void
    {
        self::enforceBodyLimit($config, $endpoint);
        if (RateLimiter::endpointIsLimited($config, $endpoint, $userId)) {
            Response::json(['ok' => false, 'error' => 'درخواست‌ها بیش از حد مجاز است. کمی بعد تلاش کنید.'], 429);
        }
        RateLimiter::hitEndpoint($config, $endpoint, $userId);
    }

    private static function isUniqueViolation(\PDOException $e): bool
    {
        $sqlState = (string)$e->getCode();
        $driverCode = (int)($e->errorInfo[1] ?? 0);
        return $sqlState === '23000' || $driverCode === 1062;
    }
}
