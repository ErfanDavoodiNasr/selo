<?php
namespace App\Core;

class MessageReceiptService
{
    public static function hydrate(array $config, array $messages, int $userId): array
    {
        if (empty($messages)) {
            return $messages;
        }

        $ids = [];
        foreach ($messages as $msg) {
            if ((int)$msg['sender_id'] === $userId) {
                $ids[] = (int)$msg['id'];
            }
        }
        if (empty($ids)) {
            return $messages;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo = Database::pdo();
        $sql = 'SELECT message_id, status, COUNT(*) AS cnt, MAX(created_at) AS last_at
            FROM ' . $config['db']['prefix'] . 'message_receipts
            WHERE message_id IN (' . $placeholders . ')
            GROUP BY message_id, status';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $mid = (int)$row['message_id'];
            if (!isset($map[$mid])) {
                $map[$mid] = ['delivered' => 0, 'seen' => 0, 'delivered_at' => null, 'seen_at' => null];
            }
            $status = $row['status'];
            $map[$mid][$status] = (int)$row['cnt'];
            if ($status === 'delivered') {
                $map[$mid]['delivered_at'] = $row['last_at'];
            } elseif ($status === 'seen') {
                $map[$mid]['seen_at'] = $row['last_at'];
            }
        }

        foreach ($messages as &$msg) {
            if ((int)$msg['sender_id'] !== $userId) {
                continue;
            }
            $mid = (int)$msg['id'];
            $receipt = $map[$mid] ?? ['delivered' => 0, 'seen' => 0, 'delivered_at' => null, 'seen_at' => null];
            $isGroup = !empty($msg['group_id']);

            if ($isGroup) {
                $msg['receipt'] = [
                    'delivered_count' => (int)$receipt['delivered'],
                    'seen_count' => (int)$receipt['seen'],
                ];
                continue;
            }

            $status = 'delivered';
            if (!empty($receipt['seen'])) {
                $status = 'seen';
            } elseif (!empty($receipt['delivered'])) {
                $status = 'delivered';
            }
            $msg['receipt'] = [
                'status' => $status,
                'delivered_at' => $receipt['delivered_at'],
                'seen_at' => $receipt['seen_at'],
            ];
        }
        unset($msg);

        return $messages;
    }

    public static function unreadCounts(array $config, int $userId): array
    {
        $pdo = Database::pdo();
        $totalSql = 'SELECT COUNT(*) AS total
            FROM ' . $config['db']['prefix'] . 'messages m
            LEFT JOIN ' . $config['db']['prefix'] . 'message_receipts mr
                ON mr.message_id = m.id AND mr.user_id = ? AND mr.status = ?
            WHERE m.conversation_id IS NOT NULL
              AND m.recipient_id = ?
              AND m.is_deleted_for_all = 0
              AND mr.id IS NULL
              AND NOT EXISTS (
                SELECT 1 FROM ' . $config['db']['prefix'] . 'message_deletions md
                WHERE md.message_id = m.id AND md.user_id = ?
              )';
        $stmt = $pdo->prepare($totalSql);
        $stmt->execute([$userId, 'seen', $userId, $userId]);
        $total = (int)($stmt->fetchColumn() ?: 0);

        $mapSql = 'SELECT m.conversation_id, COUNT(*) AS unread
            FROM ' . $config['db']['prefix'] . 'messages m
            LEFT JOIN ' . $config['db']['prefix'] . 'message_receipts mr
                ON mr.message_id = m.id AND mr.user_id = ? AND mr.status = ?
            WHERE m.conversation_id IS NOT NULL
              AND m.recipient_id = ?
              AND m.is_deleted_for_all = 0
              AND mr.id IS NULL
              AND NOT EXISTS (
                SELECT 1 FROM ' . $config['db']['prefix'] . 'message_deletions md
                WHERE md.message_id = m.id AND md.user_id = ?
              )
            GROUP BY m.conversation_id';
        $mapStmt = $pdo->prepare($mapSql);
        $mapStmt->execute([$userId, 'seen', $userId, $userId]);
        $mapRows = $mapStmt->fetchAll();
        $byConversation = [];
        foreach ($mapRows as $row) {
            $byConversation[(int)$row['conversation_id']] = (int)$row['unread'];
        }

        return ['total' => $total, 'by_conversation' => $byConversation];
    }

    public static function markReadForConversation(array $config, int $userId, int $conversationId, ?int $upToMessageId = null): int
    {
        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');

        $where = 'm.conversation_id = ? AND m.recipient_id = ? AND m.sender_id != ? AND m.is_deleted_for_all = 0
            AND NOT EXISTS (
                SELECT 1 FROM ' . $config['db']['prefix'] . 'message_deletions md
                WHERE md.message_id = m.id AND md.user_id = ?
            )';
        $paramsBase = [$conversationId, $userId, $userId, $userId];
        if ($upToMessageId && $upToMessageId > 0) {
            $where .= ' AND m.id <= ?';
            $paramsBase[] = $upToMessageId;
        }

        $deliveredSql = 'INSERT IGNORE INTO ' . $config['db']['prefix'] . 'message_receipts (message_id, user_id, status, created_at)
            SELECT m.id, ?, ?, ?
            FROM ' . $config['db']['prefix'] . 'messages m
            WHERE ' . $where;
        $deliveredParams = array_merge([$userId, 'delivered', $now], $paramsBase);
        $deliveredStmt = $pdo->prepare($deliveredSql);
        $deliveredStmt->execute($deliveredParams);

        $seenSql = 'INSERT IGNORE INTO ' . $config['db']['prefix'] . 'message_receipts (message_id, user_id, status, created_at)
            SELECT m.id, ?, ?, ?
            FROM ' . $config['db']['prefix'] . 'messages m
            WHERE ' . $where;
        $seenParams = array_merge([$userId, 'seen', $now], $paramsBase);
        $seenStmt = $pdo->prepare($seenSql);
        $seenStmt->execute($seenParams);

        return (int)$seenStmt->rowCount();
    }

    public static function statusForConversation(array $config, int $senderId, int $recipientId, int $conversationId, int $sinceId = 0): array
    {
        $pdo = Database::pdo();
        $params = [$recipientId, $conversationId, $senderId];
        $sinceSql = '';
        if ($sinceId > 0) {
            $sinceSql = ' AND m.id > ?';
            $params[] = $sinceId;
        }
        $sql = 'SELECT m.id,
                MAX(CASE WHEN mr.status = "delivered" THEN mr.created_at END) AS delivered_at,
                MAX(CASE WHEN mr.status = "seen" THEN mr.created_at END) AS seen_at
            FROM ' . $config['db']['prefix'] . 'messages m
            LEFT JOIN ' . $config['db']['prefix'] . 'message_receipts mr
                ON mr.message_id = m.id AND mr.user_id = ?
            WHERE m.conversation_id = ?
              AND m.sender_id = ?' . $sinceSql . '
            GROUP BY m.id
            ORDER BY m.id ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $status = 'delivered';
            if (!empty($row['seen_at'])) {
                $status = 'seen';
            } elseif (!empty($row['delivered_at'])) {
                $status = 'delivered';
            }
            $result[] = [
                'message_id' => (int)$row['id'],
                'status' => $status,
                'delivered_at' => $row['delivered_at'],
                'seen_at' => $row['seen_at'],
            ];
        }
        return $result;
    }
}
