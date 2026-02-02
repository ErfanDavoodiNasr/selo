<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\LastSeenService;
use App\Core\PresenceService;
use App\Core\Request;
use App\Core\Response;

class ConversationController
{
    public static function list(array $config): void
    {
        $user = Auth::requireUser($config);
        $pdo = Database::pdo();
        $sql = 'SELECT c.id, c.created_at AS conv_created_at, c.last_message_at, m.body AS last_body, m.type AS last_type, m.sender_id AS last_sender_id,
                m.attachments_count AS last_attachments_count,
                mf.original_name AS last_file_name,
                u.id AS other_id, u.full_name AS other_name, u.username AS other_username, u.allow_voice_calls AS other_allow_voice_calls,
                u.last_seen_at AS other_last_seen_at, u.last_seen_privacy AS other_last_seen_privacy,
                up.id AS other_photo
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
        $directs = $stmt->fetchAll();
        $otherIds = [];
        foreach ($directs as $conv) {
            $otherIds[] = (int)$conv['other_id'];
        }
        $onlineMap = PresenceService::onlineMap($config, $otherIds);

        foreach ($directs as &$conv) {
            $conv['id'] = (int)$conv['id'];
            $conv['other_id'] = (int)$conv['other_id'];
            $conv['other_photo'] = $conv['other_photo'] !== null ? (int)$conv['other_photo'] : null;
            $conv['other_allow_voice_calls'] = (int)$conv['other_allow_voice_calls'] === 1;
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
                    SELECT group_id, MAX(id) AS last_message_id
                    FROM ' . $config['db']['prefix'] . 'messages
                    WHERE group_id IS NOT NULL AND is_deleted_for_all = 0
                    GROUP BY group_id
                ) lm ON lm.group_id = g.id
                LEFT JOIN ' . $config['db']['prefix'] . 'messages m ON m.id = lm.last_message_id
                LEFT JOIN ' . $config['db']['prefix'] . 'media_files mf ON mf.id = m.media_id
                LEFT JOIN ' . $config['db']['prefix'] . 'users su ON su.id = m.sender_id';
        $gStmt = $pdo->prepare($groupSql);
        $gStmt->execute([$user['id'], 'active']);
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
        usort($all, function ($a, $b) {
            return strcmp($b['sort_time'], $a['sort_time']);
        });
        foreach ($all as &$item) {
            unset($item['sort_time']);
        }

        Response::json(['ok' => true, 'data' => $all]);
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
}
