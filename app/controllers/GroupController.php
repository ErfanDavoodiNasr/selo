<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\LastSeenService;
use App\Core\MessageAttachmentService;
use App\Core\MessageMediaService;
use App\Core\MessageReceiptService;
use App\Core\MessageReactionService;
use App\Core\MediaLifecycleService;
use App\Core\Request;
use App\Core\RateLimiter;
use App\Core\Response;
use App\Core\Validator;
use App\Core\Logger;

class GroupController
{
    private const TITLE_MIN = 2;
    private const TITLE_MAX = 80;
    private const INVITE_TOKEN_TTL_SECONDS = 900;
    private static $inviteTokenTableEnsured = false;

    public static function create(array $config): void
    {
        $user = Auth::requireUser($config);
        self::guardWrite($config, 'group_create', (int)$user['id']);
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

        try {
            $pdo->beginTransaction();
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
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::error('group_create_failed', [
                'user_id' => (int)$user['id'],
                'privacy' => $privacy,
                'error' => $e->getMessage(),
            ], 'group');
            Response::json(['ok' => false, 'error' => 'ایجاد گروه ممکن نیست.'], 500);
        }

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
            Response::json(['ok' => false, 'error' => 'گروه یافت نشد.'], 404);
        }

        $isOwner = ((int)$group['owner_user_id'] === (int)$user['id']);
        $canInvite = $isMember && ($isOwner || ((int)$group['allow_member_invites'] === 1));
        $inviteToken = null;
        if ($group['privacy_type'] === 'private' && $canInvite) {
            $inviteToken = self::issuePrivateInviteToken($pdo, $config, (int)$group['id'], (int)$user['id']);
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
        self::guardWrite($config, 'group_update', (int)$user['id']);
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
        self::guardWrite($config, 'group_join', (int)$user['id']);
        $pdo = Database::pdo();
        $group = self::resolveGroup($pdo, $config, $groupKey);
        if (!$group) {
            Response::json(['ok' => false, 'error' => 'گروه یافت نشد.'], 404);
        }

        $data = Request::json();
        if ($group['privacy_type'] === 'private') {
            $token = trim((string)($data['token'] ?? ''));
            if ($token === '') {
                Response::json(['ok' => false, 'error' => 'لینک دعوت نامعتبر است.'], 403);
            }
            $tokenGroup = self::consumePrivateInviteToken($pdo, $config, $token, (int)$user['id'], (int)$group['id']);
            if (!$tokenGroup) {
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
        self::guardWrite($config, 'group_join', (int)$user['id']);
        $data = Request::json();
        $token = trim((string)($data['token'] ?? ''));
        if ($token === '') {
            Response::json(['ok' => false, 'error' => 'لینک دعوت نامعتبر است.'], 422);
        }

        $pdo = Database::pdo();
        $group = self::consumePrivateInviteToken($pdo, $config, $token, (int)$user['id']);
        if (!$group) {
            Response::json(['ok' => false, 'error' => 'لینک دعوت نامعتبر است.'], 404);
        }

        self::upsertMember($pdo, $config, (int)$group['id'], (int)$user['id']);
        Response::json(['ok' => true, 'data' => ['group_id' => (int)$group['id']]]);
    }

    public static function invite(array $config, int $groupId): void
    {
        $user = Auth::requireUser($config);
        self::guardWrite($config, 'group_invite', (int)$user['id']);
        $pdo = Database::pdo();
        $group = self::fetchGroupById($pdo, $config, $groupId);
        if (!$group) {
            Response::json(['ok' => false, 'error' => 'گروه یافت نشد.'], 404);
        }

        $membership = self::getMembership($pdo, $config, $groupId, (int)$user['id']);
        if (!$membership || $membership['status'] !== 'active') {
            Response::json(['ok' => false, 'error' => 'گروه یافت نشد.'], 404);
        }

        $isOwner = ((int)$group['owner_user_id'] === (int)$user['id']);
        if (!$isOwner && (int)$group['allow_member_invites'] !== 1) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
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
        self::guardWrite($config, 'group_remove_member', (int)$user['id']);
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
        LastSeenService::touch($config, (int)$user['id']);
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

        $sql = 'SELECT m.id, m.group_id, m.client_id, m.type, m.body, m.media_id, m.attachments_count, m.sender_id, m.reply_to_message_id, m.created_at,
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
            $row['group_id'] = $row['group_id'] !== null ? (int)$row['group_id'] : null;
            $row['sender_id'] = (int)$row['sender_id'];
            $row['media_id'] = $row['media_id'] !== null ? (int)$row['media_id'] : null;
            $row['sender_photo_id'] = $row['sender_photo_id'] !== null ? (int)$row['sender_photo_id'] : null;
            $row['attachments_count'] = (int)$row['attachments_count'];
            $row['client_id'] = $row['client_id'] !== null && $row['client_id'] !== '' ? $row['client_id'] : null;
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
        $messages = MessageAttachmentService::hydrate($config, $messages);
        $messages = MessageReceiptService::hydrate($config, $messages, (int)$user['id']);
        Response::json(['ok' => true, 'data' => $messages]);
    }

    public static function sendMessage(array $config, int $groupId): void
    {
        $user = Auth::requireUser($config);
        self::guardWrite($config, 'send', (int)$user['id']);

        LastSeenService::touch($config, (int)$user['id']);
        if ($groupId <= 0) {
            Response::json(['ok' => false, 'error' => 'گروه نامعتبر است.'], 422);
        }
        $data = Request::json();
        $typeHint = strtolower(trim($data['type'] ?? 'text'));
        $body = trim($data['body'] ?? '');
        $clientId = trim((string)($data['client_id'] ?? ''));
        $mediaIds = MessageMediaService::normalizeMediaIds($data['media_ids'] ?? [], isset($data['media_id']) ? (int)$data['media_id'] : null);
        self::enforceArrayLimit($config, 'send_media_ids', $mediaIds);
        $replyTo = isset($data['reply_to_message_id']) ? (int)$data['reply_to_message_id'] : null;

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
        $group = self::fetchGroupById($pdo, $config, $groupId);
        if (!$group) {
            Response::json(['ok' => false, 'error' => 'گروه یافت نشد.'], 404);
        }
        self::requireMember($pdo, $config, $groupId, (int)$user['id']);

        // Client-side de-duplication for retries.
        if ($clientId !== '') {
            $dupStmt = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'messages WHERE sender_id = ? AND client_id = ? AND group_id = ? LIMIT 1');
            $dupStmt->execute([$user['id'], $clientId, $groupId]);
            $dup = $dupStmt->fetch();
            if ($dup) {
                Response::json(['ok' => true, 'data' => ['message_id' => (int)$dup['id'], 'deduped' => true]]);
            }
        }

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

        foreach ($mediaMap as $mediaType) {
            if ($mediaType === 'photo' && (int)$group['allow_photos'] !== 1) {
                Response::json(['ok' => false, 'error' => 'ارسال عکس در این گروه مجاز نیست.'], 403);
            }
            if ($mediaType === 'video' && (int)$group['allow_videos'] !== 1) {
                Response::json(['ok' => false, 'error' => 'ارسال ویدیو در این گروه مجاز نیست.'], 403);
            }
            if ($mediaType === 'voice' && (int)$group['allow_voice'] !== 1) {
                Response::json(['ok' => false, 'error' => 'ارسال پیام صوتی در این گروه مجاز نیست.'], 403);
            }
            if ($mediaType === 'file' && (int)$group['allow_files'] !== 1) {
                Response::json(['ok' => false, 'error' => 'ارسال فایل در این گروه مجاز نیست.'], 403);
            }
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
            $replyCheck = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'messages WHERE id = ? AND group_id = ? LIMIT 1');
            $replyCheck->execute([$replyTo, $groupId]);
            if (!$replyCheck->fetch()) {
                Response::json(['ok' => false, 'error' => 'پیام مرجع یافت نشد.'], 422);
            }
        }

        $now = date('Y-m-d H:i:s');
        $bodyValue = ($hasMedia || $body !== '') ? $body : null;
        $attachmentsCount = $hasMedia ? count($mediaIds) : 0;
        try {
            $pdo->beginTransaction();
            $insert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'messages (conversation_id, group_id, sender_id, recipient_id, client_id, type, body, media_id, attachments_count, reply_to_message_id, created_at) VALUES (NULL, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([$groupId, $user['id'], $clientId !== '' ? $clientId : null, $messageType, $bodyValue, $primaryMediaId, $attachmentsCount, $replyTo, $now]);
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

            $update = $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'groups SET updated_at = ? WHERE id = ?');
            $update->execute([$now, $groupId]);
            $pdo->commit();
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($clientId !== '' && self::isUniqueViolation($e)) {
                $dupStmt = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'messages WHERE sender_id = ? AND client_id = ? AND group_id = ? LIMIT 1');
                $dupStmt->execute([$user['id'], $clientId, $groupId]);
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

    private static function issuePrivateInviteToken($pdo, array $config, int $groupId, int $issuedByUserId): string
    {
        self::ensureInviteTokenTable($pdo, $config);
        self::cleanupExpiredInviteTokens($pdo, $config);
        $table = $config['db']['prefix'] . 'group_invite_tokens';
        $groupsTable = $config['db']['prefix'] . 'groups';
        $reuseStmt = $pdo->prepare('SELECT g.private_invite_token, t.expires_at
            FROM ' . $groupsTable . ' g
            LEFT JOIN ' . $table . ' t
              ON t.group_id = g.id
             AND t.token_hash = SHA2(g.private_invite_token, 256)
             AND t.used_at IS NULL
             AND t.expires_at > NOW()
            WHERE g.id = ?
            LIMIT 1');
        $reuseStmt->execute([$groupId]);
        $existing = $reuseStmt->fetch();
        if ($existing && !empty($existing['private_invite_token']) && !empty($existing['expires_at'])) {
            return (string)$existing['private_invite_token'];
        }
        $rawToken = bin2hex(random_bytes(24));
        $tokenHash = hash('sha256', $rawToken);
        $ttl = (int)($config['groups']['invite_token_ttl_seconds'] ?? self::INVITE_TOKEN_TTL_SECONDS);
        if ($ttl <= 0) {
            $ttl = self::INVITE_TOKEN_TTL_SECONDS;
        }
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
        $insert = $pdo->prepare('INSERT INTO ' . $table . ' (group_id, token_hash, issued_by_user_id, expires_at, used_at, used_by_user_id, created_at) VALUES (?, ?, ?, ?, NULL, NULL, NOW())');
        $insert->execute([$groupId, $tokenHash, $issuedByUserId, $expiresAt]);
        $pdo->prepare('UPDATE ' . $groupsTable . ' SET private_invite_token = ?, updated_at = NOW() WHERE id = ?')->execute([$rawToken, $groupId]);
        return $rawToken;
    }

    private static function consumePrivateInviteToken($pdo, array $config, string $token, int $userId, int $expectedGroupId = 0): ?array
    {
        self::ensureInviteTokenTable($pdo, $config);
        $table = $config['db']['prefix'] . 'group_invite_tokens';
        $tokenHash = hash('sha256', $token);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id, group_id FROM ' . $table . ' WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1 FOR UPDATE');
            $stmt->execute([$tokenHash]);
            $tokenRow = $stmt->fetch();
            if (!$tokenRow) {
                $retrySql = 'SELECT group_id FROM ' . $table . ' WHERE token_hash = ? AND used_by_user_id = ?';
                $retryParams = [$tokenHash, $userId];
                if ($expectedGroupId > 0) {
                    $retrySql .= ' AND group_id = ?';
                    $retryParams[] = $expectedGroupId;
                }
                $retrySql .= ' LIMIT 1';
                $retryStmt = $pdo->prepare($retrySql);
                $retryStmt->execute($retryParams);
                $retryRow = $retryStmt->fetch();
                if ($retryRow) {
                    $groupStmt = $pdo->prepare('SELECT * FROM ' . $config['db']['prefix'] . 'groups WHERE id = ? AND privacy_type = ? LIMIT 1');
                    $groupStmt->execute([(int)$retryRow['group_id'], 'private']);
                    $group = $groupStmt->fetch();
                    $pdo->rollBack();
                    return $group ?: null;
                }
                $pdo->rollBack();
                return null;
            }
            if ($expectedGroupId > 0 && (int)$tokenRow['group_id'] !== $expectedGroupId) {
                $pdo->rollBack();
                return null;
            }

            $groupStmt = $pdo->prepare('SELECT * FROM ' . $config['db']['prefix'] . 'groups WHERE id = ? AND privacy_type = ? LIMIT 1');
            $groupStmt->execute([(int)$tokenRow['group_id'], 'private']);
            $group = $groupStmt->fetch();
            if (!$group) {
                $pdo->rollBack();
                return null;
            }

            $use = $pdo->prepare('UPDATE ' . $table . ' SET used_at = NOW(), used_by_user_id = ? WHERE id = ? AND used_at IS NULL');
            $use->execute([$userId, (int)$tokenRow['id']]);
            if ((int)$use->rowCount() !== 1) {
                $pdo->rollBack();
                return null;
            }
            $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'groups SET private_invite_token = NULL, updated_at = NOW() WHERE id = ? AND private_invite_token = ?')
                ->execute([(int)$tokenRow['group_id'], $token]);

            $pdo->commit();
            return $group;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return null;
        }
    }

    private static function ensureInviteTokenTable($pdo, array $config): void
    {
        if (self::$inviteTokenTableEnsured) {
            return;
        }
        $table = $config['db']['prefix'] . 'group_invite_tokens';
        if (!Database::tableExists($table)) {
            Response::json(['ok' => false, 'error' => 'ساختار پایگاه‌داده ناقص است. لطفاً migration نصب را اجرا کنید.'], 500);
        }
        self::$inviteTokenTableEnsured = true;
    }

    private static function cleanupExpiredInviteTokens($pdo, array $config): void
    {
        if (mt_rand(1, 30) !== 1) {
            return;
        }
        $table = $config['db']['prefix'] . 'group_invite_tokens';
        $pdo->exec('DELETE FROM `' . $table . '` WHERE expires_at < NOW() OR used_at IS NOT NULL LIMIT 300');
    }

    private static function isUniqueViolation(\PDOException $e): bool
    {
        $sqlState = (string)$e->getCode();
        $driverCode = (int)($e->errorInfo[1] ?? 0);
        return $sqlState === '23000' || $driverCode === 1062;
    }
}
