<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class ConversationController
{
    public static function list(array $config): void
    {
        $user = Auth::requireUser($config);
        $pdo = Database::pdo();
        $sql = 'SELECT c.id, c.last_message_at, m.body AS last_body, m.type AS last_type, m.sender_id AS last_sender_id,
                mf.original_name AS last_file_name,
                u.id AS other_id, u.full_name AS other_name, u.username AS other_username, up.file_name AS other_photo
                FROM ' . $config['db']['prefix'] . 'conversations c
                JOIN ' . $config['db']['prefix'] . 'users u
                    ON u.id = CASE WHEN c.user_one_id = ? THEN c.user_two_id ELSE c.user_one_id END
                LEFT JOIN ' . $config['db']['prefix'] . 'messages m ON m.id = c.last_message_id
                LEFT JOIN ' . $config['db']['prefix'] . 'media_files mf ON mf.id = m.media_id
                LEFT JOIN ' . $config['db']['prefix'] . 'user_profile_photos up ON up.id = u.active_photo_id
                WHERE c.user_one_id = ? OR c.user_two_id = ?
                ORDER BY c.last_message_at DESC, c.id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user['id'], $user['id'], $user['id']]);
        $conversations = $stmt->fetchAll();
        foreach ($conversations as &$conv) {
            $conv['last_preview'] = self::previewText($conv['last_type'] ?? 'text', $conv['last_body'] ?? '', $conv['last_file_name'] ?? '');
        }
        Response::json(['ok' => true, 'data' => $conversations]);
    }

    public static function start(array $config): void
    {
        $user = Auth::requireUser($config);
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
        $insert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'conversations (user_one_id, user_two_id, created_at) VALUES (?, ?, ?)');
        $insert->execute([$userOne, $userTwo, $now]);
        Response::json(['ok' => true, 'data' => ['conversation_id' => (int)$pdo->lastInsertId()]]);
    }

    private static function previewText(string $type, string $body, string $filename): string
    {
        $type = $type ?: 'text';
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
}
