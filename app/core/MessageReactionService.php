<?php
namespace App\Core;

class MessageReactionService
{
    private const ALLOWED = [
        '😂', '😜', '👍', '😘', '😁', '🤣', '😍', '🥰', '🤩',
        '😐', '😑', '🙄', '😬', '🤮', '😎', '🥳', '👎', '🙏'
    ];

    public static function allowedEmojis(): array
    {
        return self::ALLOWED;
    }

    public static function isAllowed(string $emoji): bool
    {
        return in_array($emoji, self::ALLOWED, true);
    }

    public static function hydrate(array $config, array $messages, int $userId): array
    {
        if (empty($messages)) {
            return $messages;
        }
        $ids = [];
        foreach ($messages as $msg) {
            $ids[] = (int)$msg['id'];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo = Database::pdo();

        $countSql = 'SELECT message_id, reaction_emoji, COUNT(*) AS cnt
            FROM ' . $config['db']['prefix'] . 'message_reactions
            WHERE message_id IN (' . $placeholders . ')
            GROUP BY message_id, reaction_emoji';
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($ids);
        $counts = $countStmt->fetchAll();

        $countMap = [];
        foreach ($counts as $row) {
            $mid = (int)$row['message_id'];
            if (!isset($countMap[$mid])) {
                $countMap[$mid] = [];
            }
            $countMap[$mid][] = [
                'emoji' => $row['reaction_emoji'],
                'count' => (int)$row['cnt'],
            ];
        }

        $userSql = 'SELECT message_id, reaction_emoji
            FROM ' . $config['db']['prefix'] . 'message_reactions
            WHERE user_id = ? AND message_id IN (' . $placeholders . ')';
        $userStmt = $pdo->prepare($userSql);
        $userStmt->execute(array_merge([$userId], $ids));
        $userRows = $userStmt->fetchAll();
        $userMap = [];
        foreach ($userRows as $row) {
            $userMap[(int)$row['message_id']] = $row['reaction_emoji'];
        }

        foreach ($messages as &$msg) {
            $mid = (int)$msg['id'];
            $msg['reactions'] = $countMap[$mid] ?? [];
            $msg['current_user_reaction'] = $userMap[$mid] ?? null;
        }
        return $messages;
    }

    public static function summary(array $config, int $messageId, int $userId, ?string $emojiFilter = null, int $limit = 20): array
    {
        $pdo = Database::pdo();
        $limit = max(1, min(50, $limit));
        $emojiFilter = $emojiFilter ? trim($emojiFilter) : null;
        if ($emojiFilter !== null && $emojiFilter !== '' && !self::isAllowed($emojiFilter)) {
            return ['ok' => false, 'error' => 'ایموجی نامعتبر است.'];
        }

        $params = [$messageId];
        $filterSql = '';
        if ($emojiFilter) {
            $filterSql = ' AND reaction_emoji = ?';
            $params[] = $emojiFilter;
        }

        $countSql = 'SELECT reaction_emoji, COUNT(*) AS cnt
            FROM ' . $config['db']['prefix'] . 'message_reactions
            WHERE message_id = ?' . $filterSql . '
            GROUP BY reaction_emoji';
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $counts = $countStmt->fetchAll();

        $result = [];
        if ($emojiFilter === null || $emojiFilter === '') {
            foreach ($counts as $row) {
                $result[] = [
                    'emoji' => $row['reaction_emoji'],
                    'count' => (int)$row['cnt'],
                    'users' => [],
                    'total' => (int)$row['cnt'],
                ];
            }
        } else {
            $usersByEmoji = [];
            if (!empty($counts)) {
                $userSql = 'SELECT mr.reaction_emoji, u.id, u.full_name, u.username, up.id AS photo_id
                    FROM ' . $config['db']['prefix'] . 'message_reactions mr
                    JOIN ' . $config['db']['prefix'] . 'users u ON u.id = mr.user_id
                    LEFT JOIN ' . $config['db']['prefix'] . 'user_profile_photos up ON up.id = u.active_photo_id
                    WHERE mr.message_id = ? AND mr.reaction_emoji = ?
                    ORDER BY mr.created_at DESC
                    LIMIT ' . $limit;
                $userStmt = $pdo->prepare($userSql);
                $userStmt->execute([$messageId, $emojiFilter]);
                foreach ($userStmt->fetchAll() as $user) {
                    $emoji = (string)$user['reaction_emoji'];
                    if (!isset($usersByEmoji[$emoji])) {
                        $usersByEmoji[$emoji] = [];
                    }
                    $usersByEmoji[$emoji][] = [
                        'id' => (int)$user['id'],
                        'full_name' => $user['full_name'],
                        'username' => $user['username'],
                        'photo_id' => $user['photo_id'] !== null ? (int)$user['photo_id'] : null,
                    ];
                }
            }
            foreach ($counts as $row) {
                $emoji = (string)$row['reaction_emoji'];
                $result[] = [
                    'emoji' => $emoji,
                    'count' => (int)$row['cnt'],
                    'users' => $usersByEmoji[$emoji] ?? [],
                    'total' => (int)$row['cnt'],
                ];
            }
        }

        $current = null;
        $currentStmt = $pdo->prepare('SELECT reaction_emoji FROM ' . $config['db']['prefix'] . 'message_reactions WHERE message_id = ? AND user_id = ? LIMIT 1');
        $currentStmt->execute([$messageId, $userId]);
        $currentRow = $currentStmt->fetch();
        if ($currentRow) {
            $current = $currentRow['reaction_emoji'];
        }

        return [
            'ok' => true,
            'data' => [
                'message_id' => $messageId,
                'reactions' => $result,
                'current_user_reaction' => $current,
            ],
        ];
    }
}
