<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
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

        $sql = 'SELECT m.id, m.body, m.sender_id, m.recipient_id, m.reply_to_message_id, m.created_at,
                su.full_name AS sender_name,
                ru.id AS reply_id, ru.body AS reply_body, ru.sender_id AS reply_sender_id,
                ruser.full_name AS reply_sender_name
                FROM ' . $config['db']['prefix'] . 'messages m
                JOIN ' . $config['db']['prefix'] . 'users su ON su.id = m.sender_id
                LEFT JOIN ' . $config['db']['prefix'] . 'messages ru ON ru.id = m.reply_to_message_id
                LEFT JOIN ' . $config['db']['prefix'] . 'users ruser ON ruser.id = ru.sender_id
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

        Response::json(['ok' => true, 'data' => $messages]);
    }

    public static function send(array $config): void
    {
        $user = Auth::requireUser($config);
        $data = Request::json();
        $conversationId = (int)($data['conversation_id'] ?? 0);
        $body = trim($data['body'] ?? '');
        $replyTo = isset($data['reply_to_message_id']) ? (int)$data['reply_to_message_id'] : null;

        if ($conversationId <= 0) {
            Response::json(['ok' => false, 'error' => 'گفتگو نامعتبر است.'], 422);
        }
        if (!Validator::messageBody($body)) {
            Response::json(['ok' => false, 'error' => 'متن پیام معتبر نیست.'], 422);
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT user_one_id, user_two_id FROM ' . $config['db']['prefix'] . 'conversations WHERE id = ? LIMIT 1');
        $stmt->execute([$conversationId]);
        $conv = $stmt->fetch();
        if (!$conv || ($conv['user_one_id'] != $user['id'] && $conv['user_two_id'] != $user['id'])) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }
        $recipientId = ($conv['user_one_id'] == $user['id']) ? $conv['user_two_id'] : $conv['user_one_id'];

        if ($replyTo) {
            $replyCheck = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'messages WHERE id = ? AND conversation_id = ? LIMIT 1');
            $replyCheck->execute([$replyTo, $conversationId]);
            if (!$replyCheck->fetch()) {
                Response::json(['ok' => false, 'error' => 'پیام مرجع یافت نشد.'], 422);
            }
        }

        $now = date('Y-m-d H:i:s');
        $insert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'messages (conversation_id, sender_id, recipient_id, body, reply_to_message_id, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $insert->execute([$conversationId, $user['id'], $recipientId, $body, $replyTo, $now]);
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
        $stmt = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'messages WHERE id = ? AND (sender_id = ? OR recipient_id = ?) LIMIT 1');
        $stmt->execute([$messageId, $user['id'], $user['id']]);
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
        $stmt = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'messages WHERE id = ? AND (sender_id = ? OR recipient_id = ?) LIMIT 1');
        $stmt->execute([$messageId, $user['id'], $user['id']]);
        if (!$stmt->fetch()) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }
        $update = $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'messages SET is_deleted_for_all = 1 WHERE id = ?');
        $update->execute([$messageId]);
        Response::json(['ok' => true]);
    }
}
