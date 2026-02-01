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

            $status = 'sent';
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
}
