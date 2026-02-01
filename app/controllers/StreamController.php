<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\MessageAttachmentService;
use App\Core\MessageReceiptService;
use App\Core\MessageReactionService;
use App\Core\Request;
use App\Core\Response;

class StreamController
{
    public static function stream(array $config): void
    {
        $user = self::resolveUser($config);
        if (!$user) {
            Response::json(['ok' => false, 'error' => 'احراز هویت نامعتبر است.'], 401);
        }

        @set_time_limit(0);
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', '0');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $lastMessageId = max(0, (int)Request::param('last_message_id', 0));
        $lastReceiptId = max(0, (int)Request::param('last_receipt_id', 0));
        $lastEventId = Request::header('Last-Event-ID') ?? Request::param('last_event_id', null);
        if ($lastEventId) {
            self::applyLastEventId($lastEventId, $lastMessageId, $lastReceiptId);
        }

        $retryMs = (int)($config['realtime']['sse_retry_ms'] ?? 2000);
        $heartbeat = (int)($config['realtime']['sse_heartbeat_seconds'] ?? 20);
        $maxSeconds = (int)($config['realtime']['sse_max_seconds'] ?? 55);

        echo 'retry: ' . $retryMs . "\n\n";
        self::emitEvent('hello', null, [
            'server_time' => date('c'),
            'last_message_id' => $lastMessageId,
            'last_receipt_id' => $lastReceiptId,
        ]);

        $start = time();
        $lastPing = 0;

        while (true) {
            if (connection_aborted()) {
                break;
            }
            $events = self::collectEvents($config, (int)$user['id'], $lastMessageId, $lastReceiptId);

            if (!empty($events['messages'])) {
                foreach ($events['messages'] as $msg) {
                    $lastMessageId = max($lastMessageId, (int)$msg['id']);
                    self::emitEvent('message', 'm-' . $msg['id'], $msg);
                }
            }
            if (!empty($events['receipts'])) {
                foreach ($events['receipts'] as $receipt) {
                    $lastReceiptId = max($lastReceiptId, (int)$receipt['id']);
                    self::emitEvent('receipt', 'r-' . $receipt['id'], $receipt);
                }
            }

            if (empty($events['messages']) && empty($events['receipts'])) {
                if ((time() - $lastPing) >= $heartbeat) {
                    echo ": ping\n\n";
                    $lastPing = time();
                }
                usleep(200000);
            }

            if ((time() - $start) >= $maxSeconds) {
                break;
            }
        }
        exit;
    }

    public static function poll(array $config): void
    {
        $user = self::resolveUser($config);
        if (!$user) {
            Response::json(['ok' => false, 'error' => 'احراز هویت نامعتبر است.'], 401);
        }

        $lastMessageId = max(0, (int)Request::param('last_message_id', 0));
        $lastReceiptId = max(0, (int)Request::param('last_receipt_id', 0));
        $timeout = (int)Request::param('timeout', 25);
        $timeout = max(0, min(30, $timeout));
        $wait = Request::param('wait', '1') !== '0';

        $etag = 'm:' . $lastMessageId . '-r:' . $lastReceiptId;
        header('ETag: ' . $etag);
        $ifNoneMatch = Request::header('If-None-Match');
        if (!$wait && $ifNoneMatch && trim($ifNoneMatch) === $etag) {
            http_response_code(304);
            exit;
        }

        $start = time();
        $events = ['messages' => [], 'receipts' => []];
        do {
            $events = self::collectEvents($config, (int)$user['id'], $lastMessageId, $lastReceiptId);
            if (!empty($events['messages']) || !empty($events['receipts'])) {
                break;
            }
            if (!$wait || $timeout === 0) {
                break;
            }
            usleep(250000);
        } while ((time() - $start) < $timeout);

        if (empty($events['messages']) && empty($events['receipts'])) {
            http_response_code(204);
            exit;
        }

        if (!empty($events['messages'])) {
            foreach ($events['messages'] as $msg) {
                $lastMessageId = max($lastMessageId, (int)$msg['id']);
            }
        }
        if (!empty($events['receipts'])) {
            foreach ($events['receipts'] as $receipt) {
                $lastReceiptId = max($lastReceiptId, (int)$receipt['id']);
            }
        }

        Response::json([
            'ok' => true,
            'data' => [
                'messages' => $events['messages'],
                'receipts' => $events['receipts'],
                'last_message_id' => $lastMessageId,
                'last_receipt_id' => $lastReceiptId,
            ],
        ]);
    }

    private static function resolveUser(array $config): ?array
    {
        $user = Auth::user($config);
        if ($user) {
            return $user;
        }
        $token = trim((string)Request::param('token', ''));
        if ($token === '') {
            return null;
        }
        return Auth::userFromToken($config, $token);
    }

    private static function applyLastEventId(string $lastEventId, int &$lastMessageId, int &$lastReceiptId): void
    {
        if (preg_match('/^m-(\d+)$/', $lastEventId, $matches)) {
            $lastMessageId = max($lastMessageId, (int)$matches[1]);
            return;
        }
        if (preg_match('/^r-(\d+)$/', $lastEventId, $matches)) {
            $lastReceiptId = max($lastReceiptId, (int)$matches[1]);
        }
    }

    private static function emitEvent(string $event, ?string $id, array $payload): void
    {
        if ($id) {
            echo 'id: ' . $id . "\n";
        }
        echo 'event: ' . $event . "\n";
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
        @ob_flush();
        @flush();
    }

    private static function collectEvents(array $config, int $userId, int $lastMessageId, int $lastReceiptId): array
    {
        $messages = self::fetchMessages($config, $userId, $lastMessageId);
        $receipts = self::fetchReceipts($config, $userId, $lastReceiptId);
        return ['messages' => $messages, 'receipts' => $receipts];
    }

    private static function fetchReceipts(array $config, int $userId, int $lastReceiptId): array
    {
        $pdo = Database::pdo();
        $sql = 'SELECT mr.id, mr.message_id, mr.user_id, mr.status, mr.created_at
            FROM ' . $config['db']['prefix'] . 'message_receipts mr
            JOIN ' . $config['db']['prefix'] . 'messages m ON m.id = mr.message_id
            WHERE mr.id > ? AND m.sender_id = ?
            ORDER BY mr.id ASC
            LIMIT 200';
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
        $params = [$userId, 'active', $lastMessageId, $userId, $userId, $userId];
        $sql = 'SELECT m.id, m.conversation_id, m.group_id, m.client_id, m.type, m.body, m.media_id, m.attachments_count, m.sender_id, m.recipient_id, m.reply_to_message_id, m.created_at,
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
                LEFT JOIN ' . $config['db']['prefix'] . 'media_files mf ON mf.id = m.media_id
                LEFT JOIN ' . $config['db']['prefix'] . 'conversations c ON c.id = m.conversation_id
                LEFT JOIN ' . $config['db']['prefix'] . 'group_members gm ON gm.group_id = m.group_id AND gm.user_id = ? AND gm.status = ?
                WHERE m.id > ?
                  AND m.is_deleted_for_all = 0
                  AND (
                    (m.conversation_id IS NOT NULL AND (c.user_one_id = ? OR c.user_two_id = ?))
                    OR (m.group_id IS NOT NULL AND gm.user_id IS NOT NULL)
                  )
                  AND NOT EXISTS (
                    SELECT 1 FROM ' . $config['db']['prefix'] . 'message_deletions md
                    WHERE md.message_id = m.id AND md.user_id = ?
                  )
                ORDER BY m.id ASC
                LIMIT 200';
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

        $messages = MessageReactionService::hydrate($config, $messages, $userId);
        $messages = MessageAttachmentService::hydrate($config, $messages);
        $messages = MessageReceiptService::hydrate($config, $messages, $userId);

        return $messages;
    }
}
