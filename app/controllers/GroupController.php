<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\MessageReactionService;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\Logger;

class GroupController
{
    private const TITLE_MIN = 2;
    private const TITLE_MAX = 80;

    public static function create(array $config): void
    {
        $user = Auth::requireUser($config);
        $data = Request::json();
        $title = trim($data['title'] ?? '');
        $privacy = strtolower(trim($data['privacy_type'] ?? 'private'));
        $description = trim($data['description'] ?? '');
        $publicHandle = strtolower(trim($data['public_handle'] ?? ''));

        if (!self::validTitle($title)) {
            Response::json(['ok' => false, 'error' => 'عنوان گروه نامعتبر است.'], 422);
        }
        if ($description !== '' && mb_strlen($description) > 255) {
            Response::json(['ok' => false, 'error' => 'توضیحات بیش از حد طولانی است.'], 422);
        }
        if (!in_array($privacy, ['private', 'public'], true)) {
            Response::json(['ok' => false, 'error' => 'نوع گروه نامعتبر است.'], 422);
        }
        if ($privacy === 'public') {
            if (!Validator::groupHandle($publicHandle)) {
                Response::json(['ok' => false, 'error' => 'شناسه عمومی گروه نامعتبر است.'], 422);
            }
        } else {
            $publicHandle = '';
        }

        $pdo = Database::pdo();
        if ($privacy === 'public') {
            $checkHandle = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'groups WHERE public_handle = ? LIMIT 1');
            $checkHandle->execute([$publicHandle]);
            if ($checkHandle->fetch()) {
                Response::json(['ok' => false, 'error' => 'این شناسه قبلاً استفاده شده است.'], 409);
            }
        }

        $allowInvites = isset($data['allow_member_invites']) ? (int)!!$data['allow_member_invites'] : 1;
        $allowPhotos = isset($data['allow_photos']) ? (int)!!$data['allow_photos'] : 1;
        $allowVideos = isset($data['allow_videos']) ? (int)!!$data['allow_videos'] : 1;
        $allowVoice = isset($data['allow_voice']) ? (int)!!$data['allow_voice'] : 1;
        $allowFiles = isset($data['allow_files']) ? (int)!!$data['allow_files'] : 1;

        $token = null;
        if ($privacy === 'private') {
            $token = self::generateInviteToken($pdo, $config);
        }

        $now = date('Y-m-d H:i:s');
        $insert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'groups (owner_user_id, title, description, avatar_path, privacy_type, public_handle, private_invite_token, allow_member_invites, allow_photos, allow_videos, allow_voice, allow_files, created_at, updated_at)
            VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([
            $user['id'],
            $title,
            $description !== '' ? $description : null,
            $privacy,
            $publicHandle !== '' ? $publicHandle : null,
            $token,
            $allowInvites,
            $allowPhotos,
            $allowVideos,
            $allowVoice,
            $allowFiles,
            $now,
            $now,
        ]);
        $groupId = (int)$pdo->lastInsertId();

        $member = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'group_members (group_id, user_id, role, status, joined_at, removed_at) VALUES (?, ?, ?, ?, ?, NULL)');
        $member->execute([$groupId, $user['id'], 'owner', 'active', $now]);

        Logger::info('group_created', [
            'group_id' => $groupId,
            'privacy' => $privacy,
            'allow_invites' => $allowInvites,
            'allow_photos' => $allowPhotos,
            'allow_videos' => $allowVideos,
            'allow_voice' => $allowVoice,
            'allow_files' => $allowFiles,
        ], 'group');

        Response::json(['ok' => true, 'data' => ['group_id' => $groupId]]);
    }

    public static function info(array $config, string $groupKey): void
    {
        $user = Auth::requireUser($config);
        $pdo = Database::pdo();
        $group = self::resolveGroup($pdo, $config, $groupKey);
        if (!$group) {
            Response::json(['ok' => false, 'error' => 'گروه یافت نشد.'], 404);
        }

        $membership = self::getMembership($pdo, $config, (int)$group['id'], (int)$user['id']);
        $isMember = $membership && $membership['status'] === 'active';
        if (!$isMember && $group['privacy_type'] === 'private') {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }

        $isOwner = ((int)$group['owner_user_id'] === (int)$user['id']);
        $canInvite = $isMember && ($isOwner || ((int)$group['allow_member_invites'] === 1));
        $inviteToken = null;
        if ($group['privacy_type'] === 'private' && $canInvite) {
            $inviteToken = $group['private_invite_token'];
        }

        $members = [];
        if ($isMember) {
            $membersStmt = $pdo->prepare('SELECT gm.user_id AS id, gm.role, u.full_name, u.username, up.id AS photo_id
                FROM ' . $config['db']['prefix'] . 'group_members gm
                JOIN ' . $config['db']['prefix'] . 'users u ON u.id = gm.user_id
                LEFT JOIN ' . $config['db']['prefix'] . 'user_profile_photos up ON up.id = u.active_photo_id
                WHERE gm.group_id = ? AND gm.status = ?
                ORDER BY gm.role DESC, u.full_name ASC');
            $membersStmt->execute([$group['id'], 'active']);
            $members = $membersStmt->fetchAll();
            foreach ($members as &$member) {
                $member['id'] = (int)$member['id'];
                if ($member['photo_id'] !== null) {
                    $member['photo_id'] = (int)$member['photo_id'];
                }
            }
        }

        Response::json([
            'ok' => true,
            'data' => [
                'group' => [
                    'id' => (int)$group['id'],
                    'owner_user_id' => (int)$group['owner_user_id'],
                    'title' => $group['title'],
                    'description' => $group['description'],
                    'avatar_path' => $group['avatar_path'],
                    'privacy_type' => $group['privacy_type'],
                    'public_handle' => $group['public_handle'],
                    'allow_member_invites' => (int)$group['allow_member_invites'],
                    'allow_photos' => (int)$group['allow_photos'],
                    'allow_videos' => (int)$group['allow_videos'],
                    'allow_voice' => (int)$group['allow_voice'],
                    'allow_files' => (int)$group['allow_files'],
                    'created_at' => $group['created_at'],
                    'updated_at' => $group['updated_at'],
                ],
                'is_owner' => $isOwner,
                'is_member' => $isMember,
                'can_invite' => $canInvite,
                'invite_token' => $inviteToken,
                'members' => $members,
            ],
        ]);
    }

    public static function update(array $config, int $groupId): void
    {
        $user = Auth::requireUser($config);
        $pdo = Database::pdo();
        $group = self::fetchGroupById($pdo, $config, $groupId);
        if (!$group) {
            Response::json(['ok' => false, 'error' => 'گروه یافت نشد.'], 404);
        }
        if ((int)$group['owner_user_id'] !== (int)$user['id']) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }

        $data = Request::json();
        $fields = [];
        $params = [];
        $changed = [];

        if (array_key_exists('title', $data)) {
            $title = trim((string)$data['title']);
            if (!self::validTitle($title)) {
                Response::json(['ok' => false, 'error' => 'عنوان گروه نامعتبر است.'], 422);
            }
            $fields[] = 'title = ?';
            $params[] = $title;
            $changed[] = 'title';
        }

        if (array_key_exists('description', $data)) {
            $desc = trim((string)$data['description']);
            if ($desc !== '' && mb_strlen($desc) > 255) {
                Response::json(['ok' => false, 'error' => 'توضیحات بیش از حد طولانی است.'], 422);
            }
            $fields[] = 'description = ?';
            $params[] = $desc !== '' ? $desc : null;
            $changed[] = 'description';
        }

        $flags = ['allow_member_invites', 'allow_photos', 'allow_videos', 'allow_voice', 'allow_files'];
        foreach ($flags as $flag) {
            if (array_key_exists($flag, $data)) {
                $fields[] = $flag . ' = ?';
                $params[] = (int)!!$data[$flag];
                $changed[] = $flag;
            }
        }

        if (empty($fields)) {
            Response::json(['ok' => true]);
        }

        $fields[] = 'updated_at = ?';
        $params[] = date('Y-m-d H:i:s');
        $params[] = $groupId;

        $stmt = $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'groups SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);

        Logger::info('group_updated', [
            'group_id' => $groupId,
            'fields' => implode(',', $changed),
        ], 'group');

        Response::json(['ok' => true]);
    }

    public static function join(array $config, string $groupKey): void
    {
        $user = Auth::requireUser($config);
        $pdo = Database::pdo();
        $group = self::resolveGroup($pdo, $config, $groupKey);
        if (!$group) {
            Response::json(['ok' => false, 'error' => 'گروه یافت نشد.'], 404);
        }

        $data = Request::json();
        if ($group['privacy_type'] === 'private') {
            $token = trim((string)($data['token'] ?? ''));
            if ($token === '' || $token !== (string)$group['private_invite_token']) {
                Response::json(['ok' => false, 'error' => 'لینک دعوت نامعتبر است.'], 403);
            }
        }

        if ($group['privacy_type'] === 'public' && $group['public_handle'] === null) {
            Response::json(['ok' => false, 'error' => 'شناسه گروه نامعتبر است.'], 422);
        }

        self::upsertMember($pdo, $config, (int)$group['id'], (int)$user['id']);
        Response::json(['ok' => true, 'data' => ['group_id' => (int)$group['id']]]);
    }

    public static function joinByLink(array $config): void
    {
        $user = Auth::requireUser($config);
        $data = Request::json();
        $token = trim((string)($data['token'] ?? ''));
        if ($token === '') {
            Response::json(['ok' => false, 'error' => 'لینک دعوت نامعتبر است.'], 422);
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM ' . $config['db']['prefix'] . 'groups WHERE private_invite_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $group = $stmt->fetch();
        if (!$group || $group['privacy_type'] !== 'private') {
            Response::json(['ok' => false, 'error' => 'لینک دعوت نامعتبر است.'], 404);
        }

        self::upsertMember($pdo, $config, (int)$group['id'], (int)$user['id']);
        Response::json(['ok' => true, 'data' => ['group_id' => (int)$group['id']]]);
    }

    public static function invite(array $config, int $groupId): void
    {
        $user = Auth::requireUser($config);
        $pdo = Database::pdo();
        $group = self::fetchGroupById($pdo, $config, $groupId);
        if (!$group) {
            Response::json(['ok' => false, 'error' => 'گروه یافت نشد.'], 404);
        }

        $membership = self::getMembership($pdo, $config, $groupId, (int)$user['id']);
        if (!$membership || $membership['status'] !== 'active') {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }

        $isOwner = ((int)$group['owner_user_id'] === (int)$user['id']);
        if (!$isOwner && (int)$group['allow_member_invites'] !== 1) {
            Response::json(['ok' => false, 'error' => 'دعوت اعضا غیرفعال است.'], 403);
        }

        $data = Request::json();
        $targetId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
        $username = strtolower(trim((string)($data['username'] ?? '')));

        if ($targetId <= 0 && $username === '') {
            Response::json(['ok' => false, 'error' => 'کاربر نامعتبر است.'], 422);
        }

        if ($targetId <= 0) {
            $uStmt = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'users WHERE username = ? LIMIT 1');
            $uStmt->execute([$username]);
            $userRow = $uStmt->fetch();
            if (!$userRow) {
                Response::json(['ok' => false, 'error' => 'کاربر یافت نشد.'], 404);
            }
            $targetId = (int)$userRow['id'];
        }

        if ($targetId === (int)$user['id']) {
            Response::json(['ok' => false, 'error' => 'نمی‌توانید خودتان را دعوت کنید.'], 422);
        }

        self::upsertMember($pdo, $config, $groupId, $targetId);
        Logger::info('group_invite', ['group_id' => $groupId, 'target_id' => $targetId], 'group');
        Response::json(['ok' => true]);
    }

    public static function removeMember(array $config, int $groupId, int $memberId): void
    {
        $user = Auth::requireUser($config);
        $pdo = Database::pdo();
        $group = self::fetchGroupById($pdo, $config, $groupId);
        if (!$group) {
            Response::json(['ok' => false, 'error' => 'گروه یافت نشد.'], 404);
        }
        if ((int)$group['owner_user_id'] !== (int)$user['id']) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }
        if ($memberId === (int)$group['owner_user_id']) {
            Response::json(['ok' => false, 'error' => 'امکان حذف مالک وجود ندارد.'], 422);
        }

        $stmt = $pdo->prepare('SELECT status FROM ' . $config['db']['prefix'] . 'group_members WHERE group_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$groupId, $memberId]);
        $row = $stmt->fetch();
        if (!$row || $row['status'] !== 'active') {
            Response::json(['ok' => false, 'error' => 'عضو یافت نشد.'], 404);
        }

        $now = date('Y-m-d H:i:s');
        $update = $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'group_members SET status = ?, removed_at = ? WHERE group_id = ? AND user_id = ?');
        $update->execute(['removed', $now, $groupId, $memberId]);
        Logger::info('group_remove_member', ['group_id' => $groupId, 'member_id' => $memberId], 'group');
        Response::json(['ok' => true]);
    }

    public static function listMessages(array $config, int $groupId): void
    {
        $user = Auth::requireUser($config);
        if ($groupId <= 0) {
            Response::json(['ok' => false, 'error' => 'گروه نامعتبر است.'], 422);
        }
        $pdo = Database::pdo();
        self::requireMember($pdo, $config, $groupId, (int)$user['id']);

        $limit = (int) Request::param('limit', 30);
        $limit = max(1, min(100, $limit));
        $beforeId = (int) Request::param('cursor', 0);

        $params = [$groupId, $user['id']];
        $beforeSql = '';
        if ($beforeId > 0) {
            $beforeSql = ' AND m.id < ?';
            $params[] = $beforeId;
        }

        $sql = 'SELECT m.id, m.type, m.body, m.media_id, m.sender_id, m.reply_to_message_id, m.created_at,
                su.full_name AS sender_name,
                sup.id AS sender_photo_id,
                ru.id AS reply_id, ru.type AS reply_type, ru.body AS reply_body, ru.sender_id AS reply_sender_id,
                ruser.full_name AS reply_sender_name,
                rphoto.id AS reply_sender_photo_id,
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
                LEFT JOIN ' . $config['db']['prefix'] . 'user_profile_photos rphoto ON rphoto.id = ruser.active_photo_id
                LEFT JOIN ' . $config['db']['prefix'] . 'media_files rmedia ON rmedia.id = ru.media_id
                LEFT JOIN ' . $config['db']['prefix'] . 'media_files mf ON mf.id = m.media_id
                WHERE m.group_id = ?
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
            $row['media_id'] = $row['media_id'] !== null ? (int)$row['media_id'] : null;
            $row['sender_photo_id'] = $row['sender_photo_id'] !== null ? (int)$row['sender_photo_id'] : null;
            if ($row['reply_id'] !== null) {
                $row['reply_id'] = (int)$row['reply_id'];
            }
            if ($row['reply_sender_id'] !== null) {
                $row['reply_sender_id'] = (int)$row['reply_sender_id'];
            }
            if ($row['reply_sender_photo_id'] !== null) {
                $row['reply_sender_photo_id'] = (int)$row['reply_sender_photo_id'];
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

    public static function sendMessage(array $config, int $groupId): void
    {
        $user = Auth::requireUser($config);
        if ($groupId <= 0) {
            Response::json(['ok' => false, 'error' => 'گروه نامعتبر است.'], 422);
        }
        $data = Request::json();
        $type = strtolower(trim($data['type'] ?? 'text'));
        $body = trim($data['body'] ?? '');
        $mediaId = isset($data['media_id']) ? (int)$data['media_id'] : null;
        $replyTo = isset($data['reply_to_message_id']) ? (int)$data['reply_to_message_id'] : null;

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
        $group = self::fetchGroupById($pdo, $config, $groupId);
        if (!$group) {
            Response::json(['ok' => false, 'error' => 'گروه یافت نشد.'], 404);
        }
        self::requireMember($pdo, $config, $groupId, (int)$user['id']);

        if ($type === 'photo' && (int)$group['allow_photos'] !== 1) {
            Response::json(['ok' => false, 'error' => 'ارسال عکس در این گروه مجاز نیست.'], 403);
        }
        if ($type === 'video' && (int)$group['allow_videos'] !== 1) {
            Response::json(['ok' => false, 'error' => 'ارسال ویدیو در این گروه مجاز نیست.'], 403);
        }
        if ($type === 'voice' && (int)$group['allow_voice'] !== 1) {
            Response::json(['ok' => false, 'error' => 'ارسال پیام صوتی در این گروه مجاز نیست.'], 403);
        }
        if ($type === 'file' && (int)$group['allow_files'] !== 1) {
            Response::json(['ok' => false, 'error' => 'ارسال فایل در این گروه مجاز نیست.'], 403);
        }

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
            $replyCheck = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'messages WHERE id = ? AND group_id = ? LIMIT 1');
            $replyCheck->execute([$replyTo, $groupId]);
            if (!$replyCheck->fetch()) {
                Response::json(['ok' => false, 'error' => 'پیام مرجع یافت نشد.'], 422);
            }
        }

        $now = date('Y-m-d H:i:s');
        $bodyValue = ($type === 'text') ? $body : null;
        $insert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'messages (conversation_id, group_id, sender_id, recipient_id, type, body, media_id, reply_to_message_id, created_at) VALUES (NULL, ?, ?, NULL, ?, ?, ?, ?, ?)');
        $insert->execute([$groupId, $user['id'], $type, $bodyValue, $mediaId, $replyTo, $now]);
        $messageId = (int)$pdo->lastInsertId();

        $update = $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'groups SET updated_at = ? WHERE id = ?');
        $update->execute([$now, $groupId]);

        Response::json(['ok' => true, 'data' => ['message_id' => $messageId]]);
    }

    private static function validTitle(string $title): bool
    {
        $len = mb_strlen($title);
        return $len >= self::TITLE_MIN && $len <= self::TITLE_MAX;
    }

    private static function fetchGroupById($pdo, array $config, int $groupId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM ' . $config['db']['prefix'] . 'groups WHERE id = ? LIMIT 1');
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();
        return $group ?: null;
    }

    private static function fetchGroupByHandle($pdo, array $config, string $handle): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM ' . $config['db']['prefix'] . 'groups WHERE public_handle = ? LIMIT 1');
        $stmt->execute([$handle]);
        $group = $stmt->fetch();
        return $group ?: null;
    }

    private static function resolveGroup($pdo, array $config, string $groupKey): ?array
    {
        if (ctype_digit($groupKey)) {
            return self::fetchGroupById($pdo, $config, (int)$groupKey);
        }
        $handle = strtolower(trim($groupKey));
        if (!Validator::groupHandle($handle)) {
            return null;
        }
        return self::fetchGroupByHandle($pdo, $config, $handle);
    }

    private static function getMembership($pdo, array $config, int $groupId, int $userId): ?array
    {
        $stmt = $pdo->prepare('SELECT role, status FROM ' . $config['db']['prefix'] . 'group_members WHERE group_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$groupId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function requireMember($pdo, array $config, int $groupId, int $userId): void
    {
        $membership = self::getMembership($pdo, $config, $groupId, $userId);
        if (!$membership || $membership['status'] !== 'active') {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }
    }

    private static function upsertMember($pdo, array $config, int $groupId, int $userId): void
    {
        $stmt = $pdo->prepare('SELECT role FROM ' . $config['db']['prefix'] . 'group_members WHERE group_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$groupId, $userId]);
        $row = $stmt->fetch();
        $now = date('Y-m-d H:i:s');

        if ($row) {
            if ($row['role'] === 'owner') {
                $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'group_members SET status = ?, removed_at = NULL WHERE group_id = ? AND user_id = ?')
                    ->execute(['active', $groupId, $userId]);
                return;
            }
            $update = $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'group_members SET role = ?, status = ?, joined_at = ?, removed_at = NULL WHERE group_id = ? AND user_id = ?');
            $update->execute(['member', 'active', $now, $groupId, $userId]);
            return;
        }

        $insert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'group_members (group_id, user_id, role, status, joined_at, removed_at) VALUES (?, ?, ?, ?, ?, NULL)');
        $insert->execute([$groupId, $userId, 'member', 'active', $now]);
    }

    private static function generateInviteToken($pdo, array $config): string
    {
        for ($i = 0; $i < 5; $i++) {
            $token = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'groups WHERE private_invite_token = ? LIMIT 1');
            $stmt->execute([$token]);
            if (!$stmt->fetch()) {
                return $token;
            }
        }
        return bin2hex(random_bytes(16));
    }
}
